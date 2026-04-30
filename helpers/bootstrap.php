<?php
require(__DIR__ . '/getConf.php');

$projectHelpersDir = getenv('SMGEN_PROJECT_HELPERS_DIR') ?: './helpers';
$projectNavbar = rtrim($projectHelpersDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'navbar.php';

if(is_file($projectNavbar))
{
	require($projectNavbar);
}
else
{
	require(__DIR__ . '/navbar.php');
}
