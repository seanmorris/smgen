<?php

namespace Smgen;

/**
 * Resolves project configuration for both the PHP engine and Bash bridge.
 */
final class Config
{
	public const SCALAR_KEYS = [
		'BASE_URL', 'OUTPUT_DIR', 'PAGES_DIR', 'TEMPLATE_DIR', 'STATIC_DIR', 'HELPERS_DIR',
		'DEFAULT_THEME', 'PRODUCT_NAME', 'ORGANIZATION', 'TAGLINE', 'TITLE_PREFIX', 'HIGHLIGHT_STYLE',
		'HEADER', 'FOOTER', 'DEV_PORT', 'INHERIT_CORE_STATIC', 'SEARCH_ENABLED', 'SEARCH_READER',
		'PHP', 'PANDOC', 'YQ', 'SMG_SEARCH', 'ASPELL', 'ASPELL_DICT',
		'SMGEN_ENVIRONMENT', 'environment', 'SMGEN_ENGINE',
	];

	public const LIST_KEYS = [
		'STYLES', 'INLINE_STYLES', 'SCRIPTS', 'DEFER_SCRIPTS', 'INLINE_SCRIPTS',
		'BODY_SCRIPTS', 'INLINE_BODY_SCRIPTS', 'CORE_ASSETS', 'NAVIGATION_WIDGETS', 'PLUGINS',
	];

	public const DEFAULTS = [
		'BASE_URL' => '',
		'OUTPUT_DIR' => './docs',
		'PAGES_DIR' => './pages',
		'TEMPLATE_DIR' => './templates',
		'STATIC_DIR' => './static',
		'HELPERS_DIR' => './helpers',
		'DEFAULT_THEME' => 'theme-default',
		'PRODUCT_NAME' => '',
		'ORGANIZATION' => '',
		'TAGLINE' => '',
		'TITLE_PREFIX' => '',
		'HIGHLIGHT_STYLE' => 'zenburn',
		'HEADER' => 'templates/header.php',
		'FOOTER' => 'templates/footer.php',
		'DEV_PORT' => '8000',
		'INHERIT_CORE_STATIC' => '0',
		'SEARCH_ENABLED' => 'auto',
		'SEARCH_READER' => '/vendor/smgen-search/SearchReader.mjs',
		'PHP' => 'php',
		'PANDOC' => 'pandoc',
		'YQ' => 'yq',
		'SMG_SEARCH' => 'smgen-search',
		'ASPELL' => 'aspell',
		'ASPELL_DICT' => 'aspell.txt',
		'SMGEN_ENVIRONMENT' => '',
		'environment' => '',
		'SMGEN_ENGINE' => 'php',
		'STYLES' => [],
		'INLINE_STYLES' => [],
		'SCRIPTS' => [],
		'DEFER_SCRIPTS' => [],
		'INLINE_SCRIPTS' => [],
		'BODY_SCRIPTS' => [],
		'INLINE_BODY_SCRIPTS' => [],
		'CORE_ASSETS' => [],
		'NAVIGATION_WIDGETS' => ['tree'],
		'PLUGINS' => [],
		'MENUS' => [],
		'MENU_REGIONS' => [],
		'RSS_FEEDS' => [],
		'LLMS' => [],
	];

	/** Fully resolved configuration values keyed by setting name. */
	private array $values;

	/** Store a complete resolved configuration snapshot. */
	private function __construct(array $values)
	{
		$this->values = $values;
	}

	/** Resolve defaults, YAML files, and optional process-environment overrides. */
	public static function load(string $projectDir, bool $readEnvironment = true, ?string $environment = null): self
	{
		$values = self::DEFAULTS;
		$base = self::readYaml($projectDir . '/.smgen.yaml');
		$values = array_replace($values, $base);

		if($environment === null)
		{
			$environment = $readEnvironment ? self::environmentValue('SMGEN_ENVIRONMENT') : null;
			$environment ??= $readEnvironment ? self::environmentValue('environment') : null;
			$environment ??= self::scalarOrNull($base['SMGEN_ENVIRONMENT'] ?? null);
			$environment ??= self::scalarOrNull($base['environment'] ?? null);
		}

		if($environment !== null && $environment !== '')
		{
			if(!preg_match('/^[A-Za-z0-9._-]+$/', $environment))
			{
				throw new \RuntimeException('Invalid SMGEN_ENVIRONMENT: ' . $environment);
			}
			$values = array_replace($values, self::readYaml($projectDir . '/.smgen.' . $environment . '.yaml'));
		}

		if($readEnvironment)
		{
			foreach(array_unique(array_merge(array_keys(self::DEFAULTS), self::SCALAR_KEYS, self::LIST_KEYS)) as $name)
			{
				$value = getenv($name);
				if($value === false)
				{
					continue;
				}
				$values[$name] = in_array($name, self::LIST_KEYS, true)
					? self::normalizeListEnvironment($value)
					: rtrim($value);
			}
		}

		foreach(self::LIST_KEYS as $name)
		{
			if(!array_key_exists($name, $values) || $values[$name] === null || $values[$name] === '')
			{
				$values[$name] = [];
			}
			elseif(!is_array($values[$name]))
			{
				$values[$name] = [(string) $values[$name]];
			}
			elseif(!self::isList($values[$name]))
			{
				throw new \RuntimeException($name . ' must be a list.');
			}
		}
		foreach(self::SCALAR_KEYS as $name)
		{
			$value = $values[$name] ?? null;
			if($value !== null && !is_scalar($value))
			{
				throw new \RuntimeException($name . ' must be a scalar value.');
			}
		}

		return new self($values);
	}

	/** Convert resolved settings into NUL-safe records consumed by the shell entry point. */
	public static function shellRecords(string $projectDir, ?string $environment, ?string $engineOverride = null): array
	{
		$config = self::load($projectDir, false, $environment);
		if($engineOverride !== null && $engineOverride !== '')
		{
			$config = $config->with('SMGEN_ENGINE', $engineOverride);
		}
		$records = [];
		foreach(array_merge(self::SCALAR_KEYS, self::LIST_KEYS) as $name)
		{
			$value = $config->get($name);
			if(in_array($name, self::LIST_KEYS, true))
			{
				$scalar = true;
				foreach($value as $item)
				{
					if(!is_string($item) && !is_int($item) && !is_float($item) && !is_bool($item))
					{
						$scalar = false;
						break;
					}
				}
				if(!$scalar && $name === 'PLUGINS' && $config->get('SMGEN_ENGINE') === 'bash')
				{
					$legacyPaths = (new PluginRegistry($projectDir, $value))->manifestPaths();
					$records[] = ['set', $name, implode("\n", $legacyPaths)];
				}
				else
				{
					$records[] = [$scalar ? 'set' : 'unset', $name, $scalar ? implode("\n", array_map('strval', $value)) : ''];
				}
				continue;
			}
			$records[] = ['set', $name, self::shellScalar($value, $name)];
		}
		return $records;
	}

	/** Read one setting without exposing the internal configuration array. */
	public function get(string $name, mixed $default = null): mixed
	{
		return array_key_exists($name, $this->values) ? $this->values[$name] : $default;
	}

	/** Return a configuration snapshot with one setting replaced. */
	public function with(string $name, mixed $value): self
	{
		$values = $this->values;
		$values[$name] = $value;
		return new self($values);
	}

	/** Return all resolved values for diagnostics and integration code. */
	public function all(): array
	{
		return $this->values;
	}

	/** Build the scalar environment passed to templates, helpers, and hooks. */
	public function environment(): array
	{
		$environment = getenv();
		if(!is_array($environment))
		{
			$environment = [];
		}
		foreach(array_merge(self::SCALAR_KEYS, self::LIST_KEYS) as $name)
		{
			$value = $this->get($name);
			if(in_array($name, self::LIST_KEYS, true))
			{
				if(self::isScalarList($value))
				{
					$environment[$name] = implode("\n", array_map('strval', $value));
				}
				continue;
			}
			if(is_scalar($value) || $value === null)
			{
				$environment[$name] = (string) ($value ?? '');
			}
		}
		return $environment;
	}

	/** Parse one optional YAML configuration file as a mapping. */
	private static function readYaml(string $path): array
	{
		if(!is_file($path))
		{
			return [];
		}
		if(!function_exists('yaml_parse_file'))
		{
			throw new \RuntimeException('The PHP YAML extension is required to read ' . $path . '.');
		}
		set_error_handler(static function(int $severity, string $message) use ($path): never {
			throw new \RuntimeException('Invalid YAML in ' . $path . ': ' . $message);
		});
		try
		{
			$value = yaml_parse_file($path);
		}
		finally
		{
			restore_error_handler();
		}
		if($value === false || $value === null)
		{
			return [];
		}
		if(!is_array($value) || ($value !== [] && array_keys($value) === range(0, count($value) - 1)))
		{
			throw new \RuntimeException('Configuration must be a YAML mapping: ' . $path);
		}
		return $value;
	}

	/** Read a nonempty process-environment value when it exists. */
	private static function environmentValue(string $name): ?string
	{
		$value = getenv($name);
		return $value === false || $value === '' ? null : $value;
	}

	/** Convert a scalar configuration value into its environment-name form. */
	private static function scalarOrNull(mixed $value): ?string
	{
		return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
	}

	/** Split a newline-delimited shell value into a configuration list. */
	private static function normalizeListEnvironment(string $value): array
	{
		$value = rtrim($value);
		return $value === '' ? [] : explode("\n", $value);
	}

	/** Encode a scalar value for the Bash configuration bridge. */
	private static function shellScalar(mixed $value, string $name): string
	{
		if($value === null)
		{
			return '';
		}
		if(is_bool($value))
		{
			return $value ? 'true' : 'false';
		}
		if(!is_string($value) && !is_int($value) && !is_float($value))
		{
			throw new \RuntimeException($name . ' must be a scalar value.');
		}
		return (string) $value;
	}

	/** Report whether a value is a list that can cross the environment boundary. */
	private static function isScalarList(mixed $value): bool
	{
		if(!is_array($value))
		{
			return false;
		}
		foreach($value as $item)
		{
			if(!is_scalar($item) && $item !== null)
			{
				return false;
			}
		}
		return true;
	}

	/** Distinguish sequential YAML lists from associative mappings. */
	private static function isList(array $value): bool
	{
		return $value === [] || array_keys($value) === range(0, count($value) - 1);
	}
}
