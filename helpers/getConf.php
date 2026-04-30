<?php

const DEFAULTS = [
	// Directories
	'OUTPUT_DIR' => './docs',
	'TEMPLATE_DIR' => './templates',
	'STATIC_DIR' => './static',
	'PAGES_DIR' => './pages',
	'HELPERS_DIR' => './helpers',
	'INHERIT_CORE_STATIC' => 0,
	'CORE_ASSETS' => [],
	'DEV_PORT' => 8000,

	// Commands
	'PANDOC' => 'pandoc',
	'SMG_SEARCH' => 'pandoc',

	//
	'BASE_URL' => '',
	'PRODUCT_NAME' => '',
	'ORGANIZATION' => '',
	'TAGLINE' => '',
	'TITLE_PREFIX' => '',

	'HEADER' => 'templates/header.php',
	'FOOTER' => 'templates/footer.php',

	'STYLES' => [],
	'INLINE_STYLES' => [],

	'SCRIPTS' => [],
	'BODY_SCRIPTS' => [],
	'INLINE_SCRIPTS' => [],
	'INLINE_BODY_SCRIPTS' => [],

	'HIGHLIGHT_STYLE' => '',
];

const LIST_CONFIG_KEYS = [
	'STYLES',
	'INLINE_STYLES',
	'SCRIPTS',
	'BODY_SCRIPTS',
	'INLINE_SCRIPTS',
	'INLINE_BODY_SCRIPTS',
	'CORE_ASSETS',
];

function normalizeConfigValue($name, $val)
{
	if(in_array($name, LIST_CONFIG_KEYS, true))
	{
		if(is_array($val))
		{
			return $val;
		}

		$val = rtrim((string) $val);

		if($val === '')
		{
			return [];
		}

		return explode("\n", $val);
	}

	if(is_string($val))
	{
		return rtrim($val);
	}

	return $val;
}

function getConf($name)
{
	static $config;
	static $envConfig;

	if(!$config && file_exists('./.smgen.yaml'))
	{
		$config = $config ?? yaml_parse_file('./.smgen.yaml');
	}
	else
	{
		$config = $config ?? [];
	}

	if(!$envConfig && array_key_exists('environment', $_ENV) && file_exists('./.smgen.' . $_ENV['environment'] . '.yaml'))
	{
		$envConfig = yaml_parse_file('./.smgen.' . $_ENV['environment'] . '.yaml');
	}
	else
	{
		$envConfig = $envConfig ?? [];
	}

	if(array_key_exists($name, $envConfig))
	{
		return normalizeConfigValue($name, $envConfig[$name]);
	}

	if(array_key_exists($name, $config))
	{
		return normalizeConfigValue($name, $config[$name]);
	}

	if(array_key_exists($name, $_ENV))
	{
		return normalizeConfigValue($name, $_ENV[$name]);
	}

	$envVal = getenv($name);
	if($envVal !== false)
	{
		return normalizeConfigValue($name, $envVal);
	}

	if(array_key_exists($name, DEFAULTS))
	{
		return DEFAULTS[$name];
	}

	return NULL;
}
