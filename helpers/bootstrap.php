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

function resolveTemplateInclude($path)
{
	$templateRoot = realpath(getConf('TEMPLATE_DIR') ?: 'templates');
	$resolvedPath = realpath($path);

	if(!$templateRoot || !$resolvedPath)
	{
		throw new \RuntimeException('Template include path does not exist: ' . $path);
	}

	if($resolvedPath !== $templateRoot && strpos($resolvedPath, $templateRoot . DIRECTORY_SEPARATOR) !== 0)
	{
		throw new \RuntimeException('Template include path must stay inside TEMPLATE_DIR: ' . $path);
	}

	return $resolvedPath;
}

function resolveAssetUrl($path)
{
	if(preg_match('/^(?:[a-z]+:)?\\/\\//i', $path) || preg_match('/^(?:data|mailto|tel):/i', $path))
	{
		return $path;
	}

	$baseUrl = rtrim((string) (getConf('BASE_URL') ?? ''), '/');
	$assetPath = ltrim((string) $path, '/');

	if($assetPath === '')
	{
		return $baseUrl ?: '/';
	}

	return $baseUrl
		? $baseUrl . '/' . $assetPath
		: '/' . $assetPath;
}
