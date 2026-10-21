<?php

const DEFAULTS = [
	'OUTPUT_DIR' => './docs',
	'TEMPLATE_DIR' => './templates',
	'STATIC_DIR' => './static',
	'PAGES_DIR' => './pages',
	'HELPERS_DIR' => './helpers',
	'INHERIT_CORE_STATIC' => 0,
	'CORE_ASSETS' => [],
	'DEV_PORT' => 8000,
	'BASE_URL' => '',
	'PRODUCT_NAME' => '',
	'ORGANIZATION' => '',
	'TAGLINE' => '',
	'TITLE_PREFIX' => '',
	'DEFAULT_THEME' => 'theme-default',
	'HEADER' => 'templates/header.php',
	'FOOTER' => 'templates/footer.php',
	'STYLES' => [],
	'INLINE_STYLES' => [],
	'SCRIPTS' => [],
	'DEFER_SCRIPTS' => [],
	'BODY_SCRIPTS' => [],
	'INLINE_SCRIPTS' => [],
	'INLINE_BODY_SCRIPTS' => [],
	'HIGHLIGHT_STYLE' => 'zenburn',
	'SEARCH_ENABLED' => 0,
	'SEARCH_READER' => '/vendor/smgen-search/SearchReader.mjs',
	'SMG_SEARCH' => 'smgen-search',
	'ARCHIVE_TITLE' => 'Archive',
	'NAVIGATION_WIDGETS' => ['tree'],
];

const LIST_CONFIG_KEYS = [
	'STYLES',
	'INLINE_STYLES',
	'SCRIPTS',
	'DEFER_SCRIPTS',
	'BODY_SCRIPTS',
	'INLINE_SCRIPTS',
	'INLINE_BODY_SCRIPTS',
	'CORE_ASSETS',
	'NAVIGATION_WIDGETS',
];

function normalizeConfigValue($name, $value)
{
	if(in_array($name, LIST_CONFIG_KEYS, true))
	{
		if(is_array($value))
		{
			return $value;
		}

		$value = rtrim((string) $value);
		return $value === '' ? [] : explode("\n", $value);
	}

	return is_string($value) ? rtrim($value) : $value;
}

function readYamlConfig($path)
{
	if(!is_file($path))
	{
		return [];
	}

	$value = yaml_parse_file($path);
	if($value === false || $value === null)
	{
		return [];
	}

	if(!is_array($value))
	{
		throw new \RuntimeException('Configuration must be a YAML mapping: ' . $path);
	}

	return $value;
}

function getConf($name)
{
	static $loaded = false;
	static $config = [];
	static $environmentConfig = [];

	if(!$loaded)
	{
		$config = readYamlConfig('./.smgen.yaml');
		$environment = getenv('SMGEN_ENVIRONMENT');
		if($environment === false || $environment === '')
		{
			$environment = getenv('environment');
		}

		if($environment !== false && $environment !== '')
		{
			if(!preg_match('/^[A-Za-z0-9._-]+$/', $environment))
			{
				throw new \RuntimeException('Invalid SMGEN_ENVIRONMENT: ' . $environment);
			}
			$environmentConfig = readYamlConfig('./.smgen.' . $environment . '.yaml');
		}
		$loaded = true;
	}

	$environmentValue = getenv($name);
	if($environmentValue !== false)
	{
		return normalizeConfigValue($name, $environmentValue);
	}

	if(array_key_exists($name, $environmentConfig))
	{
		return normalizeConfigValue($name, $environmentConfig[$name]);
	}

	if(array_key_exists($name, $config))
	{
		return normalizeConfigValue($name, $config[$name]);
	}

	return array_key_exists($name, DEFAULTS) ? DEFAULTS[$name] : null;
}
