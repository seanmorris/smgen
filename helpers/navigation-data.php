<?php

require(__DIR__ . '/frontmatter.php');

function navigationOutputPath($pathname, $rootPath)
{
	$relative = substr($pathname, strlen($rootPath) + 1);
	return '/' . preg_replace('/\.[^.]+$/', '.html', str_replace(DIRECTORY_SEPARATOR, '/', $relative));
}

function navigationTitle($filename, $metadata)
{
	return $metadata['title'] ?? ucwords(preg_replace(['/\.[^.]+$/', '/-/'], ['', ' '], $filename));
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

	return [
		'date' => $date->format('Y-m-d'),
		'timestamp' => $date->getTimestamp(),
	];
}

function buildNavigationEntries($path, $rootPath, &$archivePages, $archiveEnabled)
{
	$directories = [];
	$files = [];

	foreach(new \DirectoryIterator($path) as $entry)
	{
		$filename = $entry->getFilename();
		if($filename === '' || $filename[0] === '.')
		{
			continue;
		}

		$pathname = $entry->getPathname();
		if($entry->isLink())
		{
			continue;
		}
		if($entry->isDir())
		{
			$metadata = parseDirectoryFrontmatter($pathname . DIRECTORY_SEPARATOR . '.fm.yaml');
			$includeInTree = (bool) ($metadata['leftBarLink'] ?? true);
			if(!$includeInTree && !$archiveEnabled)
			{
				continue;
			}
			$children = buildNavigationEntries($pathname, $rootPath, $archivePages, $archiveEnabled);
			if(!$includeInTree)
			{
				continue;
			}

			$directories[] = [
				'type' => 'directory',
				'name' => $filename,
				'title' => $metadata['title'] ?? ucwords(trim(str_replace('-', ' ', $filename))),
				'weight' => (float) ($metadata['weight'] ?? 0),
				'open' => (bool) ($metadata['open'] ?? true),
				'children' => $children,
			];
			continue;
		}

		$metadata = parsePageFrontmatter($pathname);
		$outputPath = navigationOutputPath($pathname, $rootPath);
		$title = navigationTitle($filename, $metadata);

		if($archiveEnabled && array_key_exists('date', $metadata) && ($metadata['archive'] ?? true))
		{
			$archiveDate = normalizeArchiveDate($metadata['date'], $pathname);
			$archivePages[] = [
				'name' => $filename,
				'title' => $title,
				'path' => $outputPath,
				'date' => $archiveDate['date'],
				'timestamp' => $archiveDate['timestamp'],
			];
		}

		if(!($metadata['leftBarLink'] ?? true))
		{
			continue;
		}

		$files[] = [
			'type' => 'file',
			'name' => $filename,
			'title' => $title,
			'weight' => 1000 + (float) ($metadata['weight'] ?? 0),
			'path' => $outputPath,
		];
	}

	$entries = array_merge($directories, $files);
	usort($entries, function($a, $b) {
		if($a['weight'] === $b['weight'])
		{
			return strcmp($a['name'], $b['name']);
		}
		return $a['weight'] <=> $b['weight'];
	});

	return $entries;
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
			$years[$year] = [
				'year' => $year,
				'count' => 0,
				'months' => [],
			];
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

		unset($page['timestamp'], $page['name']);
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

$rootPath = realpath($argv[1] ?? '');
if($rootPath === false || !is_dir($rootPath))
{
	fwrite(STDERR, "PAGES_DIR does not exist.\n");
	exit(1);
}

$widgets = preg_split('/\R/', trim((string) getenv('NAVIGATION_WIDGETS')), -1, PREG_SPLIT_NO_EMPTY);
$archiveEnabled = in_array('archive', $widgets, true);
$archivePages = [];
$tree = buildNavigationEntries($rootPath, $rootPath, $archivePages, $archiveEnabled);

echo json_encode(
	[
		'tree' => $tree,
		'archive' => buildArchiveNavigation($archivePages),
	],
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);
