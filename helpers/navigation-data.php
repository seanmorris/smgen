<?php

require_once(__DIR__ . '/frontmatter.php');
require_once(__DIR__ . '/getConf.php');

function navigationOutputPath($pathname, $rootPath)
{
	$relative = substr($pathname, strlen($rootPath) + 1);
	return '/' . preg_replace('/\.[^.]+$/', '.html', str_replace(DIRECTORY_SEPARATOR, '/', $relative));
}

function navigationTitle($filename, $metadata)
{
	return $metadata['title'] ?? ucwords(preg_replace(['/\.[^.]+$/', '/-/'], ['', ' '], $filename));
}

function navigationArrayIsList($value)
{
	return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
}

function navigationToken($value, $context)
{
	$value = (string) $value;
	if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/', $value))
	{
		throw new \RuntimeException('Invalid ' . $context . ': ' . $value);
	}
	return $value;
}

function navigationLinkId($value)
{
	$value = (string) $value;
	if(!preg_match('~^[A-Za-z0-9][A-Za-z0-9._:/-]*$~', $value))
	{
		throw new \RuntimeException('Invalid menu link id: ' . $value);
	}
	return $value;
}

function navigationList($value, $context)
{
	if($value === null || $value === '')
	{
		return [];
	}
	if(is_string($value))
	{
		return [$value];
	}
	if(!navigationArrayIsList($value))
	{
		throw new \RuntimeException($context . ' must be a list.');
	}
	return $value;
}

function normalizeArchiveDate($value, $path)
{
	if(!is_string($value) && !is_int($value) && !is_float($value))
	{
		throw new \RuntimeException('Archive date must be a string or number: ' . $path);
	}
	$value = trim((string) $value);
	if($value === '')
	{
		throw new \RuntimeException('Archive date must not be empty: ' . $path);
	}
	try
	{
		$date = new \DateTimeImmutable($value);
	}
	catch(\Exception $exception)
	{
		throw new \RuntimeException('Invalid archive date in ' . $path . ': ' . $value, 0, $exception);
	}
	return ['date' => $date->format('Y-m-d'), 'timestamp' => $date->getTimestamp()];
}

function navigationScanCatalog($path, $rootPath, $relativeDirectory, &$pages, &$directories, $ancestorVisible = true)
{
	$entries = scandir($path);
	if($entries === false)
	{
		throw new \RuntimeException('Unable to read pages directory: ' . $path);
	}
	foreach($entries as $filename)
	{
		if($filename === '' || $filename[0] === '.')
		{
			continue;
		}
		$pathname = $path . DIRECTORY_SEPARATOR . $filename;
		$entry = new \SplFileInfo($pathname);
		if($entry->isLink())
		{
			continue;
		}
		$relative = ltrim($relativeDirectory . '/' . $filename, '/');
		if($entry->isDir())
		{
			$metadata = parseDirectoryFrontmatter($pathname . DIRECTORY_SEPARATOR . '.fm.yaml');
			$visible = $ancestorVisible && (bool) ($metadata['leftBarLink'] ?? true);
			$directories[$relative] = [
				'id' => 'group:' . $relative,
				'source' => $relative,
				'absolute' => $pathname,
				'title' => $metadata['title'] ?? ucwords(trim(str_replace('-', ' ', $filename))),
				'metadata' => $metadata,
				'legacyVisible' => $visible,
			];
			navigationScanCatalog($pathname, $rootPath, $relative, $pages, $directories, $visible);
			continue;
		}
		$metadata = parsePageFrontmatter($pathname);
		$pages[$relative] = [
			'id' => 'page:' . $relative,
			'source' => $relative,
			'absolute' => $pathname,
			'filename' => $filename,
			'path' => navigationOutputPath($pathname, $rootPath),
			'title' => navigationTitle($filename, $metadata),
			'metadata' => $metadata,
			'legacyVisible' => $ancestorVisible,
		];
	}
}

function navigationReadYamlMapping($path, $context)
{
	set_error_handler(function($severity, $message) use ($context) {
		throw new \RuntimeException('Invalid YAML in ' . $context . ': ' . $message);
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
	if(!is_array($value) || navigationArrayIsList($value))
	{
		throw new \RuntimeException($context . ' must contain a YAML mapping.');
	}
	return $value;
}

function navigationResolvePluginPath($configuredPath, $basePath, $context)
{
	$candidate = preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $configuredPath)
		? $configuredPath
		: $basePath . DIRECTORY_SEPARATOR . $configuredPath;
	$resolved = realpath($candidate);
	if($resolved === false || !is_file($resolved))
	{
		throw new \RuntimeException($context . ' not found: ' . $configuredPath);
	}
	return $resolved;
}

function navigationMergeMenuDefinitions(&$definitions, &$contributions, $menus, $provider)
{
	if($menus === null || $menus === [])
	{
		return;
	}
	if(!is_array($menus) || navigationArrayIsList($menus))
	{
		throw new \RuntimeException('Menus from ' . $provider . ' must be a mapping.');
	}
	foreach($menus as $name => $config)
	{
		$name = navigationToken($name, 'menu name');
		$config = $config ?? [];
		if(!is_array($config) || ($config !== [] && navigationArrayIsList($config)))
		{
			throw new \RuntimeException('Menu ' . $name . ' from ' . $provider . ' must be a mapping.');
		}
		$definition = $definitions[$name] ?? [
			'name' => $name,
			'label' => ucwords(str_replace(['-', '_'], ' ', $name)),
			'renderer' => 'flat',
			'hierarchy' => 'manual',
			'showLabel' => false,
		];
		foreach(['label', 'renderer', 'hierarchy', 'showLabel'] as $key)
		{
			if(array_key_exists($key, $config))
			{
				$definition[$key] = $config[$key];
			}
		}
		$definitions[$name] = $definition;
		$contributions[] = [
			'provider' => $provider,
			'menu' => $name,
			'links' => $config['links'] ?? [],
			'selectors' => $config['selectors'] ?? [],
		];
	}
}

function navigationMemberships($value, $context)
{
	if(navigationArrayIsList($value))
	{
		$result = [];
		foreach($value as $name)
		{
			$result[navigationToken($name, 'menu name')] = true;
		}
		return $result;
	}
	if(!is_array($value))
	{
		throw new \RuntimeException('menus must be a mapping or list in ' . $context);
	}
	return $value;
}

function navigationApplyLinkSettings($link, $settings, $context)
{
	if($settings === true || $settings === null)
	{
		$settings = [];
	}
	if(!is_array($settings) || ($settings !== [] && navigationArrayIsList($settings)))
	{
		throw new \RuntimeException('Menu link settings must be a mapping in ' . $context);
	}
	$allowed = ['title', 'parent', 'weight', 'expanded', 'variant', 'icon', 'description'];
	foreach($settings as $key => $value)
	{
		if(!in_array($key, $allowed, true))
		{
			throw new \RuntimeException('Unknown menu link field ' . $key . ' in ' . $context);
		}
		$link[$key] = $value;
	}
	$link['weight'] = (float) ($link['weight'] ?? 0);
	$link['expanded'] = (bool) ($link['expanded'] ?? false);
	$link['variant'] = navigationToken($link['variant'] ?? 'default', 'menu link variant');
	if(!in_array($link['variant'], ['default', 'cta', 'icon-only'], true))
	{
		throw new \RuntimeException('Unknown menu link variant in ' . $context . ': ' . $link['variant']);
	}
	if(isset($link['icon']) && $link['icon'] !== '')
	{
		$link['icon'] = navigationToken($link['icon'], 'menu link icon');
	}
	$link['parent'] = isset($link['parent']) && $link['parent'] !== '' ? (string) $link['parent'] : null;
	return $link;
}

function navigationPageLink($page, $settings = [], $context = '')
{
	return navigationApplyLinkSettings([
		'id' => $page['id'],
		'type' => 'page',
		'source' => $page['source'],
		'path' => $page['path'],
		'title' => $page['title'],
		'weight' => 0,
		'expanded' => false,
		'variant' => 'default',
		'parent' => null,
	], $settings, $context ?: $page['source']);
}

function navigationPutPageLink(&$links, $menu, $page, $settings, $context)
{
	$link = navigationPageLink($page, $settings, $context);
	$id = $link['id'];
	if(isset($links[$menu][$id]))
	{
		$link = navigationApplyLinkSettings($links[$menu][$id], $settings, $context);
	}
	$links[$menu][$id] = $link;
}

function navigationRemovePageLinks(&$links, $menu, $source)
{
	foreach($links[$menu] ?? [] as $id => $link)
	{
		if(($link['source'] ?? null) === $source)
		{
			unset($links[$menu][$id]);
		}
	}
}

function navigationGlobRegex($glob)
{
	$glob = str_replace('\\', '/', (string) $glob);
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

function navigationMatchesAnyGlob($path, $patterns)
{
	foreach($patterns as $pattern)
	{
		if(preg_match(navigationGlobRegex($pattern), $path))
		{
			return true;
		}
	}
	return false;
}

function navigationApplyContribution(&$links, $contribution, $definitions, $pages)
{
	$menu = $contribution['menu'];
	$provider = $contribution['provider'];
	if(!isset($definitions[$menu]))
	{
		throw new \RuntimeException('Unknown menu ' . $menu . ' in ' . $provider);
	}

	foreach(navigationList($contribution['selectors'], 'selectors for ' . $menu) as $selectorIndex => $selector)
	{
		if(!is_array($selector) || navigationArrayIsList($selector))
		{
			throw new \RuntimeException('Selector ' . $selectorIndex . ' for ' . $menu . ' must be a mapping.');
		}
		$include = navigationList($selector['include'] ?? ['**/*'], 'selector include');
		$exclude = navigationList($selector['exclude'] ?? [], 'selector exclude');
		$where = $selector['where'] ?? [];
		$defaults = $selector['defaults'] ?? [];
		if(!is_array($where) || ($where !== [] && navigationArrayIsList($where)))
		{
			throw new \RuntimeException('Selector where for ' . $menu . ' must be a mapping.');
		}
		foreach($pages as $page)
		{
			if(!navigationMatchesAnyGlob($page['source'], $include) || navigationMatchesAnyGlob($page['source'], $exclude))
			{
				continue;
			}
			$matches = true;
			foreach($where as $key => $expected)
			{
				if(!array_key_exists($key, $page['metadata']) || $page['metadata'][$key] !== $expected)
				{
					$matches = false;
					break;
				}
			}
			if($matches)
			{
				navigationPutPageLink($links, $menu, $page, $defaults, $provider . ' selector for ' . $page['source']);
			}
		}
	}

	foreach(navigationList($contribution['links'], 'links for ' . $menu) as $index => $config)
	{
		if(!is_array($config) || navigationArrayIsList($config))
		{
			throw new \RuntimeException('Link ' . $index . ' for ' . $menu . ' must be a mapping.');
		}
		$replace = (bool) ($config['replace'] ?? false);
		$pageReference = $config['page'] ?? null;
		$href = $config['href'] ?? null;
		$isGroup = (bool) ($config['group'] ?? false);
		$targetCount = ($pageReference !== null ? 1 : 0) + ($href !== null ? 1 : 0) + ($isGroup ? 1 : 0);
		if($targetCount !== 1)
		{
			throw new \RuntimeException('Link ' . $index . ' for ' . $menu . ' must define exactly one of page, href, or group.');
		}

		if($pageReference !== null)
		{
			$pageReference = ltrim(str_replace('\\', '/', (string) $pageReference), '/');
			if(!isset($pages[$pageReference]))
			{
				throw new \RuntimeException('Menu page not found in ' . $provider . ': ' . $pageReference);
			}
			$link = navigationPageLink($pages[$pageReference], [], $provider);
		}
		else
		{
			$link = [
				'type' => $isGroup ? 'group' : 'external',
				'title' => $config['title'] ?? '',
				'weight' => 0,
				'expanded' => false,
				'variant' => 'default',
				'parent' => null,
			];
			if($href !== null)
			{
				$link['href'] = (string) $href;
			}
		}

		$id = $config['id'] ?? ($link['id'] ?? null);
		if($id === null || $id === '')
		{
			throw new \RuntimeException('Link ' . $index . ' for ' . $menu . ' requires an id.');
		}
		$id = navigationLinkId($id);
		if(isset($links[$menu][$id]) && !$replace && ($links[$menu][$id]['source'] ?? null) !== ($link['source'] ?? null))
		{
			throw new \RuntimeException('Duplicate menu link id in ' . $menu . ': ' . $id);
		}
		$link['id'] = $id;
		$settings = array_intersect_key($config, array_flip(['title', 'parent', 'weight', 'expanded', 'variant', 'icon', 'description']));
		$links[$menu][$id] = navigationApplyLinkSettings($link, $settings, $provider . ' link ' . $id);
	}
}

function navigationDirectoryAncestors($source)
{
	$directory = dirname($source);
	if($directory === '.' || $directory === '')
	{
		return [];
	}
	$parts = explode('/', $directory);
	$ancestors = [];
	$current = '';
	foreach($parts as $part)
	{
		$current = ltrim($current . '/' . $part, '/');
		$ancestors[] = $current;
	}
	return $ancestors;
}

function navigationAddSourceHierarchy(&$menuLinks, $menuName, $directories)
{
	foreach($menuLinks as &$link)
	{
		if(($link['type'] ?? null) !== 'page' || !empty($link['parent']))
		{
			continue;
		}
		$ancestors = navigationDirectoryAncestors($link['source']);
		if($ancestors)
		{
			$link['parent'] = 'group:' . end($ancestors);
		}
	}
	unset($link);

	$required = [];
	foreach($menuLinks as $link)
	{
		if(($link['type'] ?? null) === 'group' && isset($link['source']))
		{
			$ancestor = dirname($link['source']);
			while($ancestor !== '.' && $ancestor !== '')
			{
				$required[$ancestor] = true;
				$ancestor = dirname($ancestor);
			}
			continue;
		}
		if(($link['type'] ?? null) !== 'page')
		{
			continue;
		}
		foreach(navigationDirectoryAncestors($link['source']) as $ancestor)
		{
			$required[$ancestor] = true;
		}
	}
	foreach(array_keys($required) as $relative)
	{
		$id = 'group:' . $relative;
		if(isset($menuLinks[$id]))
		{
			continue;
		}
		$directory = $directories[$relative] ?? null;
		if(!$directory)
		{
			throw new \RuntimeException('Missing source directory for menu group: ' . $relative);
		}
		$parentDirectory = dirname($relative);
		$settings = [];
		if(array_key_exists('menus', $directory['metadata']))
		{
			$memberships = navigationMemberships($directory['metadata']['menus'], $directory['source']);
			if(isset($memberships[$menuName]) && $memberships[$menuName] !== false)
			{
				$settings = $memberships[$menuName];
			}
		}
		$menuLinks[$id] = navigationApplyLinkSettings([
			'id' => $id,
			'type' => 'group',
			'source' => $relative,
			'title' => $directory['title'],
			'parent' => $parentDirectory === '.' ? null : 'group:' . $parentDirectory,
			'weight' => (float) ($directory['metadata']['weight'] ?? 0),
			'expanded' => (bool) ($directory['metadata']['open'] ?? true),
			'variant' => 'default',
		], $settings, $directory['source']);
	}
}

function navigationSortLinks(&$items)
{
	usort($items, function($a, $b) {
		$weight = ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0);
		return $weight !== 0 ? $weight : strcmp($a['id'], $b['id']);
	});
}

function navigationBuildLinkTree($menuName, $links)
{
	$children = [];
	foreach($links as $id => $link)
	{
		$parent = $link['parent'] ?? '';
		if($parent !== '' && $parent !== null && !isset($links[$parent]))
		{
			throw new \RuntimeException('Unknown parent ' . $parent . ' for ' . $id . ' in menu ' . $menuName);
		}
		$children[$parent ?: ''][] = $id;
	}

	$visiting = [];
	$visited = [];
	$build = function($parent) use (&$build, &$visiting, &$visited, $children, $links, $menuName) {
		$result = [];
		foreach($children[$parent] ?? [] as $id)
		{
			if(isset($visiting[$id]))
			{
				throw new \RuntimeException('Parent cycle in menu ' . $menuName . ' at ' . $id);
			}
			$visiting[$id] = true;
			$link = $links[$id];
			$link['children'] = $build($id);
			unset($visiting[$id]);
			$visited[$id] = true;
			$result[] = $link;
		}
		navigationSortLinks($result);
		return $result;
	};
	$result = $build('');
	if(count($visited) !== count($links))
	{
		$remaining = array_values(array_diff(array_keys($links), array_keys($visited)));
		throw new \RuntimeException('Parent cycle in menu ' . $menuName . ' at ' . ($remaining[0] ?? 'unknown'));
	}
	return $result;
}

function navigationRunCommand($command)
{
	$output = [];
	$status = 0;
	exec($command . ' 2>/dev/null', $output, $status);
	return $status === 0 ? trim(implode("\n", $output)) : '';
}

function navigationFilesystemBirthTime($path)
{
	$quoted = escapeshellarg($path);
	$value = navigationRunCommand('stat -c %W -- ' . $quoted);
	if(ctype_digit($value) && (int) $value > 0)
	{
		return (int) $value;
	}
	$value = navigationRunCommand('stat -f %B ' . $quoted);
	return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
}

function navigationGitCreatedTime($path)
{
	$project = realpath(getenv('SMGEN_PROJECT_DIR') ?: getcwd());
	if(!$project)
	{
		return null;
	}
	$root = navigationRunCommand('git -C ' . escapeshellarg($project) . ' rev-parse --show-toplevel');
	if($root === '' || strpos($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) !== 0)
	{
		return null;
	}
	$relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
	$dates = navigationRunCommand('git -C ' . escapeshellarg($root) . ' log --follow --diff-filter=A --format=%aI -- ' . escapeshellarg($relative));
	if($dates === '')
	{
		return null;
	}
	$lines = preg_split('/\R/', $dates, -1, PREG_SPLIT_NO_EMPTY);
	$timestamp = strtotime(end($lines));
	return $timestamp === false ? null : $timestamp;
}

function navigationWriteCreated($path, $timestamp)
{
	$contents = file_get_contents($path);
	if($contents === false)
	{
		throw new \RuntimeException('Unable to read page for created timestamp: ' . $path);
	}
	$newline = strpos($contents, "\r\n") !== false ? "\r\n" : "\n";
	$value = gmdate('Y-m-d\TH:i:sP', $timestamp);
	$line = "created: '" . str_replace("'", "''", $value) . "'";

	if(preg_match('/\A---(\r\n|\n|\r)/', $contents, $opening))
	{
		$newline = $opening[1];
		if(!preg_match('/(?:\r\n|\n|\r)---(?:(?:\r\n|\n|\r)|\z)/', $contents, $closing, PREG_OFFSET_CAPTURE, strlen($opening[0])))
		{
			throw new \RuntimeException('Unterminated YAML frontmatter: ' . $path);
		}
		$offset = $closing[0][1];
		$updated = substr($contents, 0, $offset) . $newline . $line . substr($contents, $offset);
	}
	else
	{
		$updated = '---' . $newline . $line . $newline . '---' . $newline . $newline . $contents;
	}

	$temporary = tempnam(dirname($path), '.smgen-created-');
	if($temporary === false)
	{
		throw new \RuntimeException('Unable to create temporary page file: ' . $path);
	}
	try
	{
		if(file_put_contents($temporary, $updated) === false)
		{
			throw new \RuntimeException('Unable to write created timestamp: ' . $path);
		}
		$permissions = fileperms($path);
		if($permissions !== false)
		{
			chmod($temporary, $permissions & 0777);
		}
		if(!rename($temporary, $path))
		{
			throw new \RuntimeException('Unable to replace page with created timestamp: ' . $path);
		}
	}
	finally
	{
		if(is_file($temporary))
		{
			unlink($temporary);
		}
	}
	return $value;
}

function navigationResolveTimelineDate(&$page, &$pendingCreated)
{
	if(array_key_exists('date', $page['metadata']))
	{
		return normalizeArchiveDate($page['metadata']['date'], $page['absolute']);
	}
	if(array_key_exists('created', $page['metadata']))
	{
		return normalizeArchiveDate($page['metadata']['created'], $page['absolute']);
	}

	$timestamp = navigationFilesystemBirthTime($page['absolute'])
		?? navigationGitCreatedTime($page['absolute'])
		?? filemtime($page['absolute']);
	if($timestamp === false || $timestamp === null)
	{
		throw new \RuntimeException('Unable to determine creation time for timeline page: ' . $page['source']);
	}
	$page['metadata']['created'] = gmdate('Y-m-d\TH:i:sP', $timestamp);
	$pendingCreated[$page['absolute']] = $timestamp;
	return normalizeArchiveDate($page['metadata']['created'], $page['absolute']);
}

function buildArchiveNavigation($pages)
{
	usort($pages, function($a, $b) {
		if($a['timestamp'] === $b['timestamp'])
		{
			return strcmp($a['path'], $b['path']);
		}
		return $b['timestamp'] <=> $a['timestamp'];
	});

	$years = [];
	foreach($pages as $page)
	{
		$year = substr($page['date'], 0, 4);
		$month = substr($page['date'], 0, 7);
		if(!isset($years[$year]))
		{
			$years[$year] = ['year' => $year, 'count' => 0, 'months' => []];
		}
		if(!isset($years[$year]['months'][$month]))
		{
			$date = \DateTimeImmutable::createFromFormat('!Y-m', $month);
			$years[$year]['months'][$month] = [
				'month' => $month,
				'name' => $date->format('F'),
				'count' => 0,
				'pages' => [],
			];
		}
		unset($page['timestamp']);
		$years[$year]['count']++;
		$years[$year]['months'][$month]['count']++;
		$years[$year]['months'][$month]['pages'][] = $page;
	}
	foreach($years as &$year)
	{
		$year['months'] = array_values($year['months']);
	}
	unset($year);
	return ['years' => array_values($years)];
}

function navigationLegacyWidgets()
{
	return array_map('strval', getConf('NAVIGATION_WIDGETS') ?: ['tree']);
}

$rootPath = realpath($argv[1] ?? '');
if($rootPath === false || !is_dir($rootPath))
{
	fwrite(STDERR, "PAGES_DIR does not exist.\n");
	exit(1);
}

$pages = [];
$directories = [];
navigationScanCatalog($rootPath, $rootPath, '', $pages, $directories);

$definitions = [
	'sidebar' => [
		'name' => 'sidebar',
		'label' => 'Navigation',
		'renderer' => 'tree',
		'hierarchy' => 'source',
		'showLabel' => false,
	],
	'archive' => [
		'name' => 'archive',
		'label' => getConf('ARCHIVE_TITLE') ?: 'Archive',
		'renderer' => 'timeline',
		'hierarchy' => 'manual',
		'showLabel' => true,
	],
];
$contributions = [];
$alters = [];
$projectRoot = realpath(getenv('SMGEN_PROJECT_DIR') ?: getcwd()) ?: getcwd();

foreach(getConf('PLUGINS') ?: [] as $pluginPath)
{
	$manifestPath = navigationResolvePluginPath((string) $pluginPath, $projectRoot, 'Plugin manifest');
	$manifest = navigationReadYamlMapping($manifestPath, $manifestPath);
	$pluginId = navigationToken($manifest['id'] ?? basename(dirname($manifestPath)), 'plugin id');
	navigationMergeMenuDefinitions($definitions, $contributions, $manifest['menus'] ?? [], 'plugin ' . $pluginId);
	if(isset($manifest['alter']))
	{
		$alterPath = navigationResolvePluginPath((string) $manifest['alter'], dirname($manifestPath), 'Plugin alter hook');
		$alters[] = ['provider' => 'plugin ' . $pluginId, 'path' => $alterPath];
	}
}
navigationMergeMenuDefinitions($definitions, $contributions, getConf('MENUS') ?: [], 'site configuration');

$links = [];
foreach(array_keys($definitions) as $name)
{
	$links[$name] = [];
}

foreach($pages as $page)
{
	if(array_key_exists('menus', $page['metadata']))
	{
		continue;
	}
	if($page['legacyVisible'] && ($page['metadata']['leftBarLink'] ?? true))
	{
		navigationPutPageLink($links, 'sidebar', $page, [
			'weight' => 1000 + (float) ($page['metadata']['weight'] ?? 0),
		], 'legacy sidebar');
	}
	if(array_key_exists('date', $page['metadata']) && ($page['metadata']['archive'] ?? true))
	{
		navigationPutPageLink($links, 'archive', $page, [], 'legacy archive');
	}
}

foreach($contributions as $contribution)
{
	navigationApplyContribution($links, $contribution, $definitions, $pages);
}

foreach($pages as $source => $page)
{
	if(!array_key_exists('menus', $page['metadata']))
	{
		continue;
	}
	foreach(navigationMemberships($page['metadata']['menus'], $source) as $menu => $settings)
	{
		$menu = navigationToken($menu, 'menu name');
		if(!isset($definitions[$menu]))
		{
			throw new \RuntimeException('Unknown menu ' . $menu . ' in ' . $source);
		}
		if($settings === false)
		{
			navigationRemovePageLinks($links, $menu, $source);
			continue;
		}
		navigationPutPageLink($links, $menu, $page, $settings, $source);
	}
}

foreach($directories as $source => $directory)
{
	if(!array_key_exists('menus', $directory['metadata']))
	{
		continue;
	}
	foreach(navigationMemberships($directory['metadata']['menus'], $source) as $menu => $settings)
	{
		$menu = navigationToken($menu, 'menu name');
		if(!isset($definitions[$menu]))
		{
			throw new \RuntimeException('Unknown menu ' . $menu . ' in ' . $source);
		}
		$id = $directory['id'];
		if($settings === false)
		{
			unset($links[$menu][$id]);
			continue;
		}
		$parentDirectory = dirname($source);
		$links[$menu][$id] = navigationApplyLinkSettings([
			'id' => $id,
			'type' => 'group',
			'source' => $source,
			'title' => $directory['title'],
			'parent' => ($definitions[$menu]['hierarchy'] ?? 'manual') === 'source' && $parentDirectory !== '.'
				? 'group:' . $parentDirectory
				: null,
			'weight' => (float) ($directory['metadata']['weight'] ?? 0),
			'expanded' => (bool) ($directory['metadata']['open'] ?? true),
			'variant' => 'default',
		], $settings, $source);
	}
}

$regionsConfig = getConf('MENU_REGIONS') ?: [];
if($regionsConfig && (!is_array($regionsConfig) || navigationArrayIsList($regionsConfig)))
{
	throw new \RuntimeException('MENU_REGIONS must be a mapping.');
}
$regions = [];
foreach($regionsConfig as $region => $menuNames)
{
	$region = navigationToken($region, 'menu region');
	$regions[$region] = [];
	foreach(navigationList($menuNames, 'menu region ' . $region) as $menuName)
	{
		$regions[$region][] = navigationToken($menuName, 'menu name');
	}
}
if(!isset($regions['sidebar']))
{
	$regions['sidebar'] = [];
	foreach(navigationLegacyWidgets() as $widget)
	{
		if($widget === 'tree')
		{
			$regions['sidebar'][] = 'sidebar';
		}
		elseif($widget === 'archive')
		{
			$regions['sidebar'][] = 'archive';
		}
		else
		{
			throw new \RuntimeException('Unknown navigation widget: ' . $widget);
		}
	}
}

$registry = ['menus' => &$definitions, 'links' => &$links, 'regions' => &$regions];
$catalogForAlter = ['pages' => $pages, 'directories' => $directories];
foreach($alters as $alter)
{
	$callback = require($alter['path']);
	if(!is_callable($callback))
	{
		throw new \RuntimeException('Menu alter hook must return a callable: ' . $alter['path']);
	}
	$callback($registry, $catalogForAlter, ['provider' => $alter['provider'], 'projectDir' => $projectRoot]);
}
$siteAlter = rtrim((string) getConf('HELPERS_DIR'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'menu-alter.php';
if(is_file($siteAlter))
{
	$callback = require($siteAlter);
	if(!is_callable($callback))
	{
		throw new \RuntimeException('Site menu alter hook must return a callable: ' . $siteAlter);
	}
	$callback($registry, $catalogForAlter, ['provider' => 'site', 'projectDir' => $projectRoot]);
}
unset($registry);

$compiledMenus = [];
$pendingCreated = [];
foreach($definitions as $name => $definition)
{
	$name = navigationToken($name, 'menu name');
	$renderer = (string) ($definition['renderer'] ?? 'flat');
	if(!in_array($renderer, ['flat', 'tree', 'timeline'], true))
	{
		throw new \RuntimeException('Unknown renderer for menu ' . $name . ': ' . $renderer);
	}
	$hierarchy = (string) ($definition['hierarchy'] ?? 'manual');
	if(!in_array($hierarchy, ['manual', 'source'], true))
	{
		throw new \RuntimeException('Unknown hierarchy for menu ' . $name . ': ' . $hierarchy);
	}
	if($hierarchy === 'source')
	{
		navigationAddSourceHierarchy($links[$name], $name, $directories);
	}

	$menu = [
		'name' => $name,
		'label' => (string) ($definition['label'] ?? $name),
		'renderer' => $renderer,
		'showLabel' => (bool) ($definition['showLabel'] ?? false),
	];
	if($renderer === 'timeline')
	{
		$timelinePages = [];
		foreach($links[$name] ?? [] as $link)
		{
			if(($link['type'] ?? null) !== 'page' || !isset($pages[$link['source']]))
			{
				throw new \RuntimeException('Timeline menu ' . $name . ' may contain only content pages.');
			}
			$date = navigationResolveTimelineDate($pages[$link['source']], $pendingCreated);
			$timelinePages[] = [
				'id' => $link['id'],
				'title' => (string) $link['title'],
				'path' => $link['path'],
				'date' => $date['date'],
				'timestamp' => $date['timestamp'],
			];
		}
		$menu['timeline'] = buildArchiveNavigation($timelinePages);
	}
	else
	{
		$menu['items'] = navigationBuildLinkTree($name, $links[$name] ?? []);
	}
	$compiledMenus[$name] = $menu;
}

$activeMenus = [];
foreach($regions as $region => $menuNames)
{
	foreach($menuNames as $menuName)
	{
		if(!isset($compiledMenus[$menuName]))
		{
			throw new \RuntimeException('Unknown menu ' . $menuName . ' in region ' . $region);
		}
		$activeMenus[$menuName] = true;
	}
}
$assets = [];
foreach(array_keys($activeMenus) as $menuName)
{
	$menu = $compiledMenus[$menuName];
	if($menu['renderer'] === 'timeline' && !empty($menu['timeline']['years']))
	{
		$assets[] = '/archive.css';
	}
}

foreach($pendingCreated as $path => $timestamp)
{
	navigationWriteCreated($path, $timestamp);
}

echo json_encode([
	'version' => 2,
	'menus' => $compiledMenus,
	'regions' => $regions,
	'assets' => array_values(array_unique($assets)),
	'tree' => $compiledMenus['sidebar']['items'] ?? [],
	'archive' => $compiledMenus['archive']['timeline'] ?? ['years' => []],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
