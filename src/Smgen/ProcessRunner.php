<?php

namespace Smgen;

/**
 * Runs argument-vector subprocesses while streaming output and forwarding signals.
 */
final class ProcessRunner
{
	private mixed $activeProcess = null;
	private ?int $pendingSignal = null;

	/** Install signal handlers that terminate the currently active child process. */
	public function __construct()
	{
		if(function_exists('pcntl_async_signals') && function_exists('pcntl_signal'))
		{
			pcntl_async_signals(true);
			foreach([SIGINT, SIGTERM] as $signal)
			{
				pcntl_signal($signal, function(int $received): void {
					$this->pendingSignal = $received;
					if(is_resource($this->activeProcess))
					{
						proc_terminate($this->activeProcess, $received);
					}
				});
			}
		}
	}

	/** Require an executable before a build stage tries to invoke it. */
	public function requireCommand(string $command, ?string $description = null): void
	{
		if($this->findExecutable($command) === null)
		{
			throw new \RuntimeException(($description ?: $command) . ' is required.');
		}
	}

	/** Report whether an executable path or PATH entry can be invoked. */
	public function commandExists(string $command): bool
	{
		return $this->findExecutable($command) !== null;
	}

	/** Run one command without a shell, optionally reading stdin from a file, and stream or capture its output. */
	public function run(
		array $command,
		string $cwd,
		array $environment = [],
		bool $captureStdout = false,
		bool $quietStderr = false,
		?string $stdinFile = null,
	): ProcessResult {
		if($command === [])
		{
			throw new \InvalidArgumentException('Process command must not be empty.');
		}
		$command = array_map(static fn(mixed $part): string => (string) $part, $command);
		$descriptorSpec = [
			0 => ['file', $stdinFile ?? 'php://stdin', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$baseEnvironment = getenv();
		$baseEnvironment = is_array($baseEnvironment) ? $baseEnvironment : [];
		$emptyAssignments = [];
		foreach($environment as $name => $value)
		{
			$name = (string) $name;
			$value = (string) $value;
			if($value === '')
			{
				unset($baseEnvironment[$name]);
				$emptyAssignments[] = $name . '=';
			}
			else
			{
				$baseEnvironment[$name] = $value;
			}
		}
		if($emptyAssignments !== [])
		{
			$environmentCommand = $this->findExecutable('env');
			if($environmentCommand === null)
			{
				throw new \RuntimeException('env is required to pass empty configuration values to child processes.');
			}
			$command = array_merge([$environmentCommand], $emptyAssignments, $command);
		}

		$process = proc_open($command, $descriptorSpec, $pipes, $cwd, $baseEnvironment, ['bypass_shell' => true]);
		if(!is_resource($process))
		{
			throw new \RuntimeException('Unable to start command: ' . implode(' ', $command));
		}
		$this->activeProcess = $process;
		foreach([1, 2] as $index)
		{
			stream_set_blocking($pipes[$index], false);
		}

		$stdout = '';
		$stderr = '';
		$lastExitCode = null;
		while(true)
		{
			$read = [];
			foreach([1, 2] as $index)
			{
				if(!feof($pipes[$index]))
				{
					$read[] = $pipes[$index];
				}
			}
			if($read !== [])
			{
				$write = null;
				$except = null;
				@stream_select($read, $write, $except, 0, 200000);
				foreach($read as $stream)
				{
					$chunk = stream_get_contents($stream);
					if($chunk === false || $chunk === '')
					{
						continue;
					}
					if($stream === $pipes[1])
					{
						$stdout .= $chunk;
						if(!$captureStdout)
						{
							fwrite(STDOUT, $chunk);
						}
					}
					else
					{
						$stderr .= $chunk;
						if(!$quietStderr)
						{
							fwrite(STDERR, $chunk);
						}
					}
				}
			}
			$status = proc_get_status($process);
			if(!$status['running'])
			{
				$lastExitCode = $status['exitcode'];
				break;
			}
		}

		foreach([1, 2] as $index)
		{
			$chunk = stream_get_contents($pipes[$index]);
			if($chunk !== false && $chunk !== '')
			{
				if($index === 1)
				{
					$stdout .= $chunk;
					if(!$captureStdout) fwrite(STDOUT, $chunk);
				}
				else
				{
					$stderr .= $chunk;
					if(!$quietStderr) fwrite(STDERR, $chunk);
				}
			}
			fclose($pipes[$index]);
		}
		$closed = proc_close($process);
		$this->activeProcess = null;
		$exitCode = $lastExitCode !== null && $lastExitCode >= 0 ? $lastExitCode : $closed;
		if($this->pendingSignal !== null)
		{
			$signal = $this->pendingSignal;
			$this->pendingSignal = null;
			throw new \RuntimeException('Build interrupted by signal ' . $signal . '.', 128 + $signal);
		}
		return new ProcessResult($exitCode, $stdout, $stderr);
	}

	/** Run a command and convert a nonzero exit status into a build failure. */
	public function mustRun(array $command, string $cwd, array $environment = [], bool $captureStdout = false): ProcessResult
	{
		$result = $this->run($command, $cwd, $environment, $captureStdout);
		if($result->exitCode !== 0)
		{
			throw new \RuntimeException('Command failed with status ' . $result->exitCode . ': ' . implode(' ', $command));
		}
		return $result;
	}

	/** Resolve an executable path using the current PATH when necessary. */
	private function findExecutable(string $command): ?string
	{
		if(str_contains($command, DIRECTORY_SEPARATOR))
		{
			return is_file($command) && is_executable($command) ? $command : null;
		}
		foreach(explode(PATH_SEPARATOR, getenv('PATH') ?: '') as $directory)
		{
			$candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $command;
			if(is_file($candidate) && is_executable($candidate))
			{
				return $candidate;
			}
		}
		return null;
	}
}
