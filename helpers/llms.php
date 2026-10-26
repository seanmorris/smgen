<?php

require_once(__DIR__ . '/llms-lib.php');

if(realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__)
{
	generateLlmsFile(
		(string) ($argv[1] ?? ''),
		(string) ($argv[2] ?? ''),
		(string) ($argv[3] ?? ''),
		(string) ($argv[4] ?? (getConf('PAGES_DIR') ?: './pages'))
	);
}
