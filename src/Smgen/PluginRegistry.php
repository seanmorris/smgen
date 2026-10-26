<?php

namespace Smgen;

/**
 * Resolves, validates, selects, and orders plugins activated by one project.
 */
final class PluginRegistry
{
	private array $plugins = [];

	/** Load each activation and reject duplicate plugin identifiers. */
	public function __construct(private readonly string $projectDir, array $activations)
	{
		foreach($activations as $activation)
		{
			$this->plugins[] = $this->loadActivation($activation);
		}
		$ids = [];
		foreach($this->plugins as $plugin)
		{
			if(isset($ids[$plugin->id]))
			{
				throw new \RuntimeException('Duplicate plugin ID: ' . $plugin->id);
			}
			$ids[$plugin->id] = true;
		}
	}

	/** Return plugins in their configured activation order. */
	public function all(): array
	{
		return $this->plugins;
	}

	/** Return canonical manifest paths for the legacy navigation compiler. */
	public function manifestPaths(): array
	{
		return array_map(static fn(Plugin $plugin): string => $plugin->manifestPath, $this->plugins);
	}

	/** Report whether navigation must run in isolation for executable alter code. */
	public function hasExecutableMenuAlter(string $helpersDir): bool
	{
		foreach($this->plugins as $plugin)
		{
			if(isset($plugin->manifest['alter']))
			{
				return true;
			}
		}
		return is_file(rtrim($helpersDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'menu-alter.php');
	}

	/** Select plugins for one page after inherited frontmatter overrides. */
	public function pluginsForPage(PageRecord $page): array
	{
		$selected = [];
		foreach($this->plugins as $plugin)
		{
			$enabled = $this->matches($plugin, $page);
			if(array_key_exists($plugin->id, $page->pluginFlags))
			{
				$enabled = $page->pluginFlags[$plugin->id];
			}
			if($enabled && $plugin->pageContribution() !== null)
			{
				$selected[] = $plugin;
			}
		}
		return $selected;
	}

	/** Return each plugin selected by at least one page, preserving plugin order. */
	public function selectedPlugins(array $pages): array
	{
		$selected = [];
		foreach($pages as $page)
		{
			foreach($this->pluginsForPage($page) as $plugin)
			{
				$selected[$plugin->id] = $plugin;
			}
		}
		return array_values($selected);
	}

	/** Resolve existing manifest and declared watch paths inside their allowed roots. */
	public function watchPaths(): array
	{
		$paths = [];
		foreach($this->plugins as $plugin)
		{
			$paths[] = $plugin->manifestPath;
			$watchGroups = [
				[$plugin->manifest['watch'] ?? [], 'plugin'],
				[$plugin->activationWatch, 'project'],
			];
			foreach($watchGroups as [$entries, $defaultBase])
			{
				foreach($entries as $entry)
				{
					$base = $defaultBase;
					$path = $entry;
					if(is_array($entry))
					{
						$base = (string) ($entry['base'] ?? $defaultBase);
						$path = $entry['path'] ?? null;
					}
					if(!is_string($path) || $path === '' || !in_array($base, ['project', 'plugin'], true))
					{
						throw new \RuntimeException('Invalid watch entry for plugin ' . $plugin->id . '.');
					}
					$root = $base === 'plugin' ? $plugin->root : $this->projectDir;
					$candidate = Filesystem::resolveProjectPath($root, $path);
					if(file_exists($candidate) || is_link($candidate))
					{
						$paths[] = Filesystem::canonicalEntryWithin($candidate, $root, 'Watch path for plugin ' . $plugin->id);
					}
				}
			}
		}
		return array_values(array_unique($paths));
	}

	/** Resolve a plugin-owned file without allowing it to escape the package root. */
	public function resolvePluginPath(Plugin $plugin, string $path, string $label): string
	{
		return Filesystem::canonicalFileWithin(
			Filesystem::resolveProjectPath($plugin->root, $path),
			$plugin->root,
			$label . ' for plugin ' . $plugin->id,
		);
	}

	/** Normalize one scalar or structured activation into a validated plugin. */
	private function loadActivation(mixed $activation): Plugin
	{
		$options = [];
		$pageOverride = null;
		$watch = [];
		if(is_string($activation))
		{
			$use = $activation;
		}
		elseif(is_array($activation) && !self::isList($activation))
		{
			$allowed = ['use', 'options', 'pages', 'watch'];
			foreach(array_keys($activation) as $key)
			{
				if(!is_string($key) || !in_array($key, $allowed, true))
				{
					throw new \RuntimeException('Unknown structured PLUGINS field: ' . (string) $key);
				}
			}
			$use = $activation['use'] ?? null;
			$options = $activation['options'] ?? [];
			$pageOverride = $activation['pages'] ?? null;
			$watch = $activation['watch'] ?? [];
			if(!is_array($options) || ($options !== [] && self::isList($options)))
			{
				throw new \RuntimeException('Plugin options must be a mapping.');
			}
			if($pageOverride !== null && (!is_array($pageOverride) || ($pageOverride !== [] && self::isList($pageOverride))))
			{
				throw new \RuntimeException('Plugin pages override must be a mapping.');
			}
			if(is_array($pageOverride))
			{
				$this->validateActivationPages($pageOverride);
			}
			if(!is_array($watch) || ($watch !== [] && !self::isList($watch)))
			{
				throw new \RuntimeException('Plugin watch override must be a list.');
			}
			$this->validateWatchEntries($watch, 'plugin activation');
		}
		else
		{
			throw new \RuntimeException('Each PLUGINS entry must be a manifest path, package name, or mapping.');
		}
		if(!is_string($use) || trim($use) === '')
		{
			throw new \RuntimeException('Structured PLUGINS entries require a nonempty use field.');
		}

		$manifestPath = $this->resolveManifest(trim($use));
		$manifest = $this->readManifest($manifestPath);
		$legacy = !array_key_exists('apiVersion', $manifest);
		if($legacy)
		{
			$extra = array_diff(array_keys($manifest), ['id', 'menus', 'alter']);
			if($extra !== [])
			{
				throw new \RuntimeException('Plugin manifest must declare apiVersion: smgen/v1 before using ' . implode(', ', $extra) . ': ' . $manifestPath);
			}
		}
		elseif($manifest['apiVersion'] !== 'smgen/v1')
		{
			throw new \RuntimeException('Unsupported plugin API version in ' . $manifestPath . ': ' . (string) $manifest['apiVersion']);
		}
		else
		{
			$this->validateVersionedManifest($manifest, $manifestPath);
		}

		$id = $manifest['id'] ?? basename(dirname($manifestPath));
		if(!is_string($id) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $id))
		{
			throw new \RuntimeException('Invalid plugin ID in ' . $manifestPath . ': ' . (string) $id);
		}
		return new Plugin($id, $manifestPath, dirname($manifestPath), $manifest, $options, $pageOverride, $watch, $legacy);
	}

	/** Resolve an explicit manifest or discover one from an installed npm package. */
	private function resolveManifest(string $use): string
	{
		$candidate = Filesystem::resolveProjectPath($this->projectDir, $use);
		if(is_file($candidate))
		{
			$resolved = realpath($candidate);
			if($resolved === false)
			{
				throw new \RuntimeException('Unable to resolve plugin manifest: ' . $use);
			}
			return $resolved;
		}

		if(str_starts_with($use, '.') || str_starts_with($use, '/') || str_ends_with($use, '.yaml') || str_ends_with($use, '.yml'))
		{
			throw new \RuntimeException('Plugin manifest does not exist: ' . $use);
		}

		$directory = $this->projectDir;
		while(true)
		{
			$packageRoot = $directory . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $use);
			$packageJson = $packageRoot . DIRECTORY_SEPARATOR . 'package.json';
			if(is_file($packageJson))
			{
				$data = json_decode((string) file_get_contents($packageJson), true, 512, JSON_THROW_ON_ERROR);
				$entry = $data['smgen']['plugin'] ?? 'smgen.plugin.yaml';
				if(!is_string($entry) || $entry === '')
				{
					throw new \RuntimeException('Invalid smgen.plugin package field: ' . $packageJson);
				}
				return Filesystem::canonicalFileWithin($packageRoot . DIRECTORY_SEPARATOR . $entry, $packageRoot, 'Plugin manifest');
			}
			$parent = dirname($directory);
			if($parent === $directory)
			{
				break;
			}
			$directory = $parent;
		}
		throw new \RuntimeException('Local plugin package is not installed: ' . $use);
	}

	/** Parse a plugin YAML document and require a top-level mapping. */
	private function readManifest(string $path): array
	{
		if(!function_exists('yaml_parse_file'))
		{
			throw new \RuntimeException('The PHP YAML extension is required to read plugin manifests.');
		}
		set_error_handler(static function(int $severity, string $message) use ($path): never {
			throw new \RuntimeException('Invalid plugin YAML in ' . $path . ': ' . $message);
		});
		try
		{
			$value = yaml_parse_file($path);
		}
		finally
		{
			restore_error_handler();
		}
		if(!is_array($value) || self::isList($value))
		{
			throw new \RuntimeException('Plugin manifest must be a YAML mapping: ' . $path);
		}
		return $value;
	}

	/** Test a page against the plugin's ordered declarative selectors. */
	private function matches(Plugin $plugin, PageRecord $page): bool
	{
		foreach($plugin->selectors() as $selector)
		{
			if(!is_array($selector) || self::isList($selector))
			{
				throw new \RuntimeException('Plugin selectors must be mappings for ' . $plugin->id . '.');
			}
			$this->rejectUnknownFields($selector, ['include', 'exclude', 'where'], 'plugin selector for ' . $plugin->id);
			$include = $selector['include'] ?? ['**/*'];
			$exclude = $selector['exclude'] ?? [];
			$where = $selector['where'] ?? [];
			$include = is_array($include) ? $include : [$include];
			$exclude = is_array($exclude) ? $exclude : [$exclude];
			if(!$this->matchesAny($page->relative, $include) || $this->matchesAny($page->relative, $exclude))
			{
				continue;
			}
			if(!is_array($where) || ($where !== [] && self::isList($where)))
			{
				throw new \RuntimeException('Plugin selector where must be a mapping for ' . $plugin->id . '.');
			}
			$matches = true;
			foreach($where as $key => $expected)
			{
				if(!array_key_exists($key, $page->frontmatter) || $page->frontmatter[$key] !== $expected)
				{
					$matches = false;
					break;
				}
			}
			if($matches)
			{
				return true;
			}
		}
		return false;
	}

	/** Validate the fields and value shapes defined by the smgen/v1 manifest. */
	private function validateVersionedManifest(array $manifest, string $path): void
	{
		$allowed = ['apiVersion', 'id', 'menus', 'alter', 'pages', 'assets', 'hooks', 'watch'];
		$this->rejectUnknownFields($manifest, $allowed, 'plugin manifest ' . $path);
		if(isset($manifest['alter']) && !is_string($manifest['alter']))
		{
			throw new \RuntimeException('Plugin alter must be a path in ' . $path . '.');
		}
		if(isset($manifest['pages']))
		{
			if(!is_array($manifest['pages']) || ($manifest['pages'] !== [] && self::isList($manifest['pages'])))
			{
				throw new \RuntimeException('Plugin pages must be a mapping in ' . $path . '.');
			}
			$this->rejectUnknownFields($manifest['pages'], ['selectors', 'styles', 'scripts', 'filters'], 'plugin pages in ' . $path);
			$this->validateSelectors($manifest['pages']['selectors'] ?? [], 'plugin pages in ' . $path);
			$this->validateStringList($manifest['pages']['styles'] ?? [], 'plugin page styles in ' . $path);
			$this->validateStringList($manifest['pages']['filters'] ?? [], 'plugin page filters in ' . $path);
			$scripts = $manifest['pages']['scripts'] ?? [];
			if(!is_array($scripts) || ($scripts !== [] && self::isList($scripts)))
			{
				throw new \RuntimeException('Plugin page scripts must be a mapping in ' . $path . '.');
			}
			$this->rejectUnknownFields($scripts, ['head', 'defer', 'body'], 'plugin page scripts in ' . $path);
			foreach(['head', 'defer', 'body'] as $placement)
			{
				$this->validateStringList($scripts[$placement] ?? [], 'plugin ' . $placement . ' scripts in ' . $path);
			}
		}
		if(isset($manifest['assets']) && (!is_array($manifest['assets']) || ($manifest['assets'] !== [] && !self::isList($manifest['assets']))))
		{
			throw new \RuntimeException('Plugin assets must be a list in ' . $path . '.');
		}
		foreach($manifest['assets'] ?? [] as $asset)
		{
			if(!is_array($asset) || self::isList($asset))
			{
				throw new \RuntimeException('Each plugin asset must be a mapping in ' . $path . '.');
			}
			$this->rejectUnknownFields($asset, ['from', 'to'], 'plugin asset in ' . $path);
			foreach(['from', 'to'] as $field)
			{
				if(!isset($asset[$field]) || !is_string($asset[$field]) || trim($asset[$field]) === '')
				{
					throw new \RuntimeException('Plugin assets require nonempty string from and to fields in ' . $path . '.');
				}
			}
		}
		if(isset($manifest['hooks']))
		{
			if(!is_array($manifest['hooks']) || ($manifest['hooks'] !== [] && self::isList($manifest['hooks'])))
			{
				throw new \RuntimeException('Plugin hooks must be a mapping in ' . $path . '.');
			}
			$this->rejectUnknownFields($manifest['hooks'], ['prepare', 'finalize'], 'plugin hooks in ' . $path);
			foreach(['prepare', 'finalize'] as $phase)
			{
				if(!array_key_exists($phase, $manifest['hooks'])) continue;
				$hook = $manifest['hooks'][$phase];
				if(is_array($hook) && !self::isList($hook))
				{
					$this->rejectUnknownFields($hook, ['run'], 'plugin ' . $phase . ' hook in ' . $path);
					$hook = $hook['run'] ?? null;
				}
				if(!is_array($hook) || $hook === [] || !self::isList($hook))
				{
					throw new \RuntimeException('Plugin ' . $phase . ' hook must be a nonempty run list in ' . $path . '.');
				}
				foreach($hook as $argument)
				{
					if(!is_string($argument) && !is_int($argument) && !is_float($argument))
					{
						throw new \RuntimeException('Plugin hook arguments must be scalar values in ' . $path . '.');
					}
				}
			}
		}
		if(isset($manifest['watch']) && (!is_array($manifest['watch']) || ($manifest['watch'] !== [] && !self::isList($manifest['watch']))))
		{
			throw new \RuntimeException('Plugin watch must be a list in ' . $path . '.');
		}
		$this->validateWatchEntries($manifest['watch'] ?? [], 'plugin manifest ' . $path);
	}

	/** Validate project-specific selector overrides from a structured activation. */
	private function validateActivationPages(array $pages): void
	{
		$this->rejectUnknownFields($pages, ['selectors', 'include', 'exclude', 'where'], 'plugin activation pages');
		if(isset($pages['selectors']) && array_intersect(array_keys($pages), ['include', 'exclude', 'where']) !== [])
		{
			throw new \RuntimeException('Plugin pages override cannot mix selectors with include, exclude, or where.');
		}
		if(isset($pages['selectors']))
		{
			$this->validateSelectors($pages['selectors'], 'plugin activation pages');
			return;
		}
		$this->validateSelector($pages, 'plugin activation pages');
	}

	/** Validate a selector mapping or list of selector mappings. */
	private function validateSelectors(mixed $selectors, string $context): void
	{
		if($selectors === []) return;
		if(!is_array($selectors))
		{
			throw new \RuntimeException('Plugin selectors must be a list in ' . $context . '.');
		}
		if(!self::isList($selectors)) $selectors = [$selectors];
		foreach($selectors as $selector)
		{
			if(!is_array($selector) || self::isList($selector))
			{
				throw new \RuntimeException('Plugin selectors must be mappings in ' . $context . '.');
			}
			$this->validateSelector($selector, $context);
		}
	}

	/** Validate include, exclude, and frontmatter conditions for one selector. */
	private function validateSelector(array $selector, string $context): void
	{
		$this->rejectUnknownFields($selector, ['include', 'exclude', 'where'], $context);
		foreach(['include', 'exclude'] as $field)
		{
			$value = $selector[$field] ?? [];
			if(!is_array($value)) $value = [$value];
			$this->validateStringList($value, 'plugin selector ' . $field . ' in ' . $context);
		}
		$where = $selector['where'] ?? [];
		if(!is_array($where) || ($where !== [] && self::isList($where)))
		{
			throw new \RuntimeException('Plugin selector where must be a mapping in ' . $context . '.');
		}
	}

	/** Require a sequential list containing only nonempty strings. */
	private function validateStringList(mixed $value, string $context): void
	{
		if(!is_array($value) || ($value !== [] && !self::isList($value)))
		{
			throw new \RuntimeException(ucfirst($context) . ' must be a list.');
		}
		foreach($value as $entry)
		{
			if(!is_string($entry) || $entry === '')
			{
				throw new \RuntimeException(ucfirst($context) . ' must contain nonempty strings.');
			}
		}
	}

	/** Validate string and structured watch entries before the watcher consumes them. */
	private function validateWatchEntries(array $entries, string $context): void
	{
		foreach($entries as $entry)
		{
			if(is_string($entry) && $entry !== '')
			{
				$this->validateWatchPath($entry, $context);
				continue;
			}
			if(!is_array($entry) || self::isList($entry))
			{
				throw new \RuntimeException('Watch entries must be paths or mappings in ' . $context . '.');
			}
			$this->rejectUnknownFields($entry, ['base', 'path'], 'watch entry in ' . $context);
			if(!isset($entry['path']) || !is_string($entry['path']) || $entry['path'] === '')
			{
				throw new \RuntimeException('Watch mappings require a nonempty path in ' . $context . '.');
			}
			if(isset($entry['base']) && !in_array($entry['base'], ['project', 'plugin'], true))
			{
				throw new \RuntimeException('Watch base must be project or plugin in ' . $context . '.');
			}
			$this->validateWatchPath($entry['path'], $context);
		}
	}

	/** Reject absolute, parent-traversing, and control-character watch paths. */
	private function validateWatchPath(string $path, string $context): void
	{
		$segments = explode('/', str_replace('\\', '/', $path));
		if(
			Filesystem::isAbsolute($path)
			|| in_array('..', $segments, true)
			|| str_contains($path, "\0")
			|| str_contains($path, "\n")
			|| str_contains($path, "\r")
		)
		{
			throw new \RuntimeException('Watch paths must stay inside their declared root in ' . $context . ': ' . $path);
		}
	}

	/** Reject mapping keys outside the versioned schema for a given context. */
	private function rejectUnknownFields(array $value, array $allowed, string $context): void
	{
		foreach(array_keys($value) as $key)
		{
			if(!is_string($key) || !in_array($key, $allowed, true))
			{
				throw new \RuntimeException('Unknown field ' . (string) $key . ' in ' . $context . '.');
			}
		}
	}

	/** Report whether at least one glob pattern matches a source-relative page path. */
	private function matchesAny(string $path, array $patterns): bool
	{
		foreach($patterns as $pattern)
		{
			if(!is_string($pattern))
			{
				throw new \RuntimeException('Plugin selector patterns must be strings.');
			}
			if(preg_match($this->globRegex($pattern), $path) === 1)
			{
				return true;
			}
		}
		return false;
	}

	/** Compile the supported glob syntax into an anchored Unicode regular expression. */
	private function globRegex(string $glob): string
	{
		$glob = str_replace('\\', '/', $glob);
		$regex = '';
		$length = strlen($glob);
		for($index = 0; $index < $length; $index++)
		{
			$character = $glob[$index];
			if($character === '*')
			{
				if($index + 1 < $length && $glob[$index + 1] === '*')
				{
					$index++;
					if($index + 1 < $length && $glob[$index + 1] === '/')
					{
						$index++;
						$regex .= '(?:.*/)?';
					}
					else
					{
						$regex .= '.*';
					}
				}
				else
				{
					$regex .= '[^/]*';
				}
			}
			elseif($character === '?')
			{
				$regex .= '[^/]';
			}
			else
			{
				$regex .= preg_quote($character, '~');
			}
		}
		return '~^' . $regex . '$~u';
	}

	/** Distinguish sequential YAML lists from associative mappings. */
	private static function isList(array $value): bool
	{
		return $value === [] || array_keys($value) === range(0, count($value) - 1);
	}
}
