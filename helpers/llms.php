<?php

require_once(__DIR__ . '/llms-lib.php');

generateLlmsFile(
	(string) ($argv[1] ?? ''),
	(string) ($argv[2] ?? ''),
	(string) ($argv[3] ?? ''),
	(string) ($argv[4] ?? (getConf('PAGES_DIR') ?: './pages'))
);
