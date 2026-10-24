<?php
require(__DIR__ . '/getConf.php');
require(__DIR__ . '/frontmatter.php');
require(__DIR__ . '/rss-lib.php');

$pageFile = getenv('SMGEN_PAGE_FILE') ?: ($argv[1] ?? null);
$frontmatter = $pageFile && is_file($pageFile) ? parsePageFrontmatter($pageFile) : [];

$projectHelpersDir = getenv('SMGEN_PROJECT_HELPERS_DIR') ?: './helpers';
$projectNavbar = rtrim($projectHelpersDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'navbar.php';
$coreNavbar = __DIR__ . '/navbar.php';

if(is_file($projectNavbar) && realpath($projectNavbar) !== realpath($coreNavbar))
{
	require($projectNavbar);
}
require_once($coreNavbar);

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

function escapeHtml($value)
{
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function staticAssetExists($path)
{
	$relativePath = ltrim((string) $path, '/');
	$staticDir = rtrim((string) getConf('STATIC_DIR'), DIRECTORY_SEPARATOR);
	if(is_file($staticDir . DIRECTORY_SEPARATOR . $relativePath))
	{
		return true;
	}

	$coreDir = rtrim((string) (getenv('SMGEN_CORE_DIR') ?: dirname(__DIR__)), DIRECTORY_SEPARATOR);
	if((string) getConf('INHERIT_CORE_STATIC') === '1')
	{
		return is_file($coreDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . $relativePath);
	}

	foreach(getConf('CORE_ASSETS') ?: [] as $asset)
	{
		if(ltrim((string) $asset, '/') === $relativePath)
		{
			return is_file($coreDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . $relativePath);
		}
	}

	return false;
}
