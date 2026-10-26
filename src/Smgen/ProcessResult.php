<?php

namespace Smgen;

/**
 * Captures the exit status and output streams from one child process.
 */
final class ProcessResult
{
	/** Store the completed process result without further interpretation. */
	public function __construct(
		public readonly int $exitCode,
		public readonly string $stdout,
		public readonly string $stderr,
	) {
	}
}
