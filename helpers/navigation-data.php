<?php

require(__DIR__ . '/frontmatter.php');

function buildNavigationEntries($path, $rootPath)
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
			if(!($metadata['leftBarLink'] ?? true))
			{
				continue;
			}

			$directories[] = [
				'type' => 'directory',
				'name' => $filename,
				'title' => $metadata['title'] ?? ucwords(trim(str_replace('-', ' ', $filename))),
				'weight' => (float) ($metadata['weight'] ?? 0),
				'open' => (bool) ($metadata['open'] ?? true),
				'children' => buildNavigationEntries($pathname, $rootPath),
			];
			continue;
		}

		$metadata = parsePageFrontmatter($pathname);
		if(!($metadata['leftBarLink'] ?? true))
		{
			continue;
		}

		$relative = substr($pathname, strlen($rootPath) + 1);
		$outputPath = '/' . preg_replace('/\.[^.]+$/', '.html', str_replace(DIRECTORY_SEPARATOR, '/', $relative));
		$files[] = [
			'type' => 'file',
			'name' => $filename,
			'title' => $metadata['title'] ?? ucwords(preg_replace(['/\.[^.]+$/', '/-/'], ['', ' '], $filename)),
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

$rootPath = realpath($argv[1] ?? '');
if($rootPath === false || !is_dir($rootPath))
{
	fwrite(STDERR, "PAGES_DIR does not exist.\n");
	exit(1);
}

echo json_encode(
	buildNavigationEntries($rootPath, $rootPath),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
);
