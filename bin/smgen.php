#!/usr/bin/env php
<?php

/** Dispatch internal PHP-engine commands used by the public Bash entry point. */

require_once dirname(__DIR__) . '/src/autoload.php';

use Smgen\BuildEngine;
use Smgen\Config;

try
{
	$command = $argv[1] ?? 'help';
	$projectDir = getcwd();
	if($projectDir === false)
	{
		throw new RuntimeException('Unable to determine the project directory.');
	}

	switch($command)
	{
		case 'config-export':
			$environment = isset($argv[2]) && $argv[2] !== '' ? $argv[2] : null;
			$engineOverride = isset($argv[3]) && $argv[3] !== '' ? $argv[3] : null;
			foreach(Config::shellRecords($projectDir, $environment, $engineOverride) as [$action, $name, $value])
			{
				fwrite(STDOUT, $action . "\0" . $name . "\0" . $value . "\0");
			}
			break;

		case 'build':
			$config = Config::load($projectDir);
			(new BuildEngine($config, dirname(__DIR__), $projectDir))->build($argv[2] ?? null);
			break;

		case 'watch-paths':
			$config = Config::load($projectDir);
			foreach((new BuildEngine($config, dirname(__DIR__), $projectDir))->pluginWatchPaths() as $path)
			{
				fwrite(STDOUT, $path . "\0");
			}
			break;

		default:
			throw new RuntimeException('Unknown PHP engine command: ' . $command);
	}
}
catch(Throwable $exception)
{
	fwrite(STDERR, $exception->getMessage() . "\n");
	exit(1);
}
