<?php

const DEFAULTS = [
	// Directories
	'OUTPUT_DIR' => './docs',
	'TEMPLATE_DIR' => './templates',
	'STATIC_DIR' => './static',
	'PAGES_DIR' => './pages',
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
		return $envConfig[$name];
	}

	if(array_key_exists($name, $config))
	{
		return $config[$name];
	}

	if(array_key_exists($name, $_ENV))
	{
		$val = rtrim($_ENV[$name]);

		if(strpos($val, "\n") > -1)
		{
			return explode("\n", $val);
		}

		return $val;
	}

	$envVal = getenv($name);
	if($envVal !== false)
	{
		$val = rtrim($envVal);

		if(strpos($val, "\n") > -1)
		{
			return explode("\n", $val);
		}

		return $val;
	}

	if(array_key_exists($name, DEFAULTS))
	{
		return DEFAULTS[$name];
	}

	return NULL;
}
