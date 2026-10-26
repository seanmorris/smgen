<?php

require_once(__DIR__ . '/frontmatter.php');

function llmsConfiguration()
{
	$value = getConf('LLMS');
	if($value === null || $value === false || $value === [] || $value === '')
	{
		return null;
	}

	if(!is_array($value))
	{
		throw new \RuntimeException('LLMS configuration must be a YAML mapping or false.');
	}
	$allowed = ['enabled', 'title', 'summary', 'details', 'sections'];
	foreach(array_keys($value) as $key)
	{
		if(!is_string($key) || !in_array($key, $allowed, true))
		{
			throw new \RuntimeException('Unknown LLMS configuration field ' . (string) $key . '.');
		}
	}

	if(array_key_exists('enabled', $value))
	{
		if(!is_bool($value['enabled']))
		{
			throw new \RuntimeException('LLMS enabled must be true or false.');
		}
		if($value['enabled'] === false)
		{
			return null;
		}
	}

	return $value;
}

function llmsEnabled()
{
	return llmsConfiguration() !== null;
}

function llmsScalarText($value, $field, $context, $allowEmpty = false)
{
	if(!is_string($value) && !is_int($value) && !is_float($value))
	{
		throw new \RuntimeException($field . ' must be text in ' . $context . '.');
	}

	$text = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
	if(preg_match('//u', $text) !== 1)
	{
		throw new \RuntimeException($field . ' must use valid UTF-8 in ' . $context . '.');
	}
	if(!$allowEmpty && $text === '')
	{
		throw new \RuntimeException($field . ' must not be empty in ' . $context . '.');
	}

	return $text;
}

function llmsSingleLineText($value, $field, $context, $allowEmpty = false)
{
	$text = llmsScalarText($value, $field, $context, $allowEmpty);
	return preg_replace('/\s+/u', ' ', $text);
}

function llmsWeight($value, $field, $context)
{
	if((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value))
	{
		throw new \RuntimeException($field . ' must be numeric in ' . $context . '.');
	}

	$weight = (float) $value;
	if(is_infinite($weight) || is_nan($weight))
	{
		throw new \RuntimeException($field . ' must be finite in ' . $context . '.');
	}

	return $weight;
}

function llmsMarkdownLabel($value)
{
	return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $value);
}

function llmsEncodeOutputPath($path)
{
	$path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
	return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function llmsOutputUrl($baseUrl, $outputPath)
{
	$baseUrl = rtrim($baseUrl, '/');
	return $baseUrl . '/' . llmsEncodeOutputPath($outputPath);
}

function llmsPageBody($path)
{
	$contents = file_get_contents($path);
	if($contents === false)
	{
		throw new \RuntimeException('Unable to read page: ' . $path);
	}

	$contents = str_replace(["\r\n", "\r"], "\n", $contents);
	if(strncmp($contents, "---\n", 4) !== 0)
	{
		return $contents;
	}

	$end = strpos($contents, "\n---\n", 4);
	if($end === false)
	{
		if(substr($contents, -4) === "\n---")
		{
			return '';
		}
		throw new \RuntimeException('Unterminated YAML frontmatter: ' . $path);
	}

	return substr($contents, $end + 5);
}

function llmsFirstHeading($path)
{
	$lines = explode("\n", llmsPageBody($path));
	$fence = null;
	$previous = null;

	foreach($lines as $line)
	{
		if($fence !== null)
		{
			if(
				preg_match('/^ {0,3}(`+|~+)[ \t]*$/', $line, $match)
				&& $match[1][0] === $fence['marker']
				&& strlen($match[1]) >= $fence['length']
			)
			{
				$fence = null;
			}
			continue;
		}

		if(preg_match('/^ {0,3}(`{3,}|~{3,})(.*)$/', $line, $match))
		{
			$marker = $match[1][0];
			if($marker !== '`' || strpos($match[2], '`') === false)
			{
				$fence = [
					'marker' => $marker,
					'length' => strlen($match[1]),
				];
				continue;
			}
		}

		if(preg_match('/^ {0,3}#[ \t]+(.+)$/u', $line, $match))
		{
			$heading = preg_replace('/[ \t]+#+[ \t]*$/u', '', trim($match[1]));
			return $heading === '' ? null : $heading;
		}
		if($previous !== null && preg_match('/^ {0,3}=+[ \t]*$/', $line))
		{
			return $previous;
		}

		$trimmed = trim($line);
		$previous = $trimmed === '' ? null : $trimmed;
	}

	return null;
}

function llmsValidateMapping($value, $context)
{
	if(!is_array($value))
	{
		throw new \RuntimeException('llms must be a mapping, section name, true, or false in ' . $context . '.');
	}

	$allowed = ['section', 'title', 'description', 'weight'];
	foreach(array_keys($value) as $key)
	{
		if(!is_string($key) || !in_array($key, $allowed, true))
		{
			throw new \RuntimeException('Unknown llms field ' . (string) $key . ' in ' . $context . '.');
		}
	}

	return $value;
}

function llmsApplySelection($current, $value, $context)
{
	if($value === false)
	{
		return false;
	}
	if($value === true)
	{
		return is_array($current) ? $current : [];
	}
	if(is_string($value))
	{
		$section = trim($value);
		if($section === '')
		{
			throw new \RuntimeException('llms section must not be empty in ' . $context . '.');
		}
		return array_replace(is_array($current) ? $current : [], ['section' => $section]);
	}

	$value = llmsValidateMapping($value, $context);
	return array_replace(is_array($current) ? $current : [], $value);
}

function llmsPageSelection($sourcePath, $pagesRoot, &$directoryCache)
{
	$sourcePath = str_replace(DIRECTORY_SEPARATOR, '/', $sourcePath);
	$pagesRoot = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $pagesRoot), '/');
	$prefix = $pagesRoot . '/';
	if(strpos($sourcePath, $prefix) !== 0)
	{
		throw new \RuntimeException('Page manifest source is outside PAGES_DIR: ' . $sourcePath);
	}

	$relative = substr($sourcePath, strlen($prefix));
	$directory = dirname($relative);
	$selection = null;
	$metadataPaths = [$pagesRoot . '/.fm.yaml'];
	if($directory !== '.')
	{
		$current = $pagesRoot;
		foreach(explode('/', $directory) as $part)
		{
			if($part === '' || $part === '.' || $part === '..')
			{
				throw new \RuntimeException('Invalid page path in manifest: ' . $sourcePath);
			}
			$current .= '/' . $part;
			$metadataPaths[] = $current . '/.fm.yaml';
		}
	}

	foreach($metadataPaths as $metadataPath)
	{
		if(!array_key_exists($metadataPath, $directoryCache))
		{
			$directoryCache[$metadataPath] = parseDirectoryFrontmatter($metadataPath);
		}
		$metadata = $directoryCache[$metadataPath];
		if(array_key_exists('llms', $metadata))
		{
			$selection = llmsApplySelection($selection, $metadata['llms'], $metadataPath);
		}
	}

	$pageMetadata = parsePageFrontmatter($sourcePath);
	if(array_key_exists('llms', $pageMetadata))
	{
		$selection = llmsApplySelection($selection, $pageMetadata['llms'], $sourcePath);
	}

	return [$selection, $pageMetadata];
}

function llmsSections($configuration)
{
	$value = $configuration['sections'] ?? [];
	if(!is_array($value))
	{
		throw new \RuntimeException('LLMS sections must be a YAML mapping.');
	}

	$sections = [];
	$order = 0;
	foreach($value as $key => $definition)
	{
		if(!is_string($key) || trim($key) === '')
		{
			throw new \RuntimeException('Each LLMS section needs a nonempty text key.');
		}
		if(!is_array($definition))
		{
			throw new \RuntimeException('LLMS section ' . $key . ' must be a YAML mapping.');
		}
		if(!array_key_exists('title', $definition))
		{
			throw new \RuntimeException('LLMS section ' . $key . ' requires title.');
		}
		foreach(array_keys($definition) as $field)
		{
			if(!is_string($field) || !in_array($field, ['title', 'weight'], true))
			{
				throw new \RuntimeException('Unknown field ' . (string) $field . ' in LLMS section ' . $key . '.');
			}
		}

		$sections[$key] = [
			'key' => $key,
			'title' => llmsSingleLineText($definition['title'], 'LLMS section title', $key),
			'weight' => array_key_exists('weight', $definition)
				? llmsWeight($definition['weight'], 'LLMS section weight', $key)
				: 0.0,
			'order' => $order++,
			'pages' => [],
		];
	}

	return $sections;
}

function llmsManifestPages($manifestPath)
{
	$manifest = fopen($manifestPath, 'r');
	if($manifest === false)
	{
		throw new \RuntimeException('Unable to read page manifest: ' . $manifestPath);
	}

	$pages = [];
	while(($line = fgets($manifest)) !== false)
	{
		$parts = explode("\t", rtrim($line, "\r\n"), 2);
		if(count($parts) !== 2)
		{
			fclose($manifest);
			throw new \RuntimeException('Invalid page manifest entry.');
		}
		$pages[] = ['output' => $parts[0], 'source' => $parts[1]];
	}
	fclose($manifest);

	return $pages;
}

function llmsPageEntry($page, $selection, $metadata, $baseUrl)
{
	$context = $page['source'];
	if(!array_key_exists('section', $selection))
	{
		throw new \RuntimeException('Selected llms page requires section: ' . $context);
	}
	$section = llmsSingleLineText($selection['section'], 'llms section', $context);

	if(array_key_exists('title', $selection))
	{
		$title = llmsSingleLineText($selection['title'], 'llms title', $context);
	}
	elseif(array_key_exists('title', $metadata))
	{
		$title = llmsSingleLineText($metadata['title'], 'title', $context);
	}
	elseif(array_key_exists('pagetitle', $metadata))
	{
		$title = llmsSingleLineText($metadata['pagetitle'], 'pagetitle', $context);
	}
	else
	{
		$title = llmsFirstHeading($context);
		if($title === null)
		{
			throw new \RuntimeException('Selected llms page requires a title or first-level heading: ' . $context);
		}
		$title = llmsSingleLineText($title, 'first-level heading', $context);
	}

	$description = null;
	if(array_key_exists('description', $selection))
	{
		$description = llmsSingleLineText($selection['description'], 'llms description', $context, true);
	}
	elseif(array_key_exists('description-meta', $metadata))
	{
		$description = llmsSingleLineText($metadata['description-meta'], 'description-meta', $context, true);
	}
	elseif(array_key_exists('description', $metadata))
	{
		$description = llmsSingleLineText($metadata['description'], 'description', $context, true);
	}

	$weightValue = array_key_exists('weight', $selection)
		? $selection['weight']
		: ($metadata['weight'] ?? 0);

	return [
		'section' => $section,
		'title' => $title,
		'description' => $description,
		'weight' => llmsWeight($weightValue, 'llms weight', $context),
		'url' => llmsOutputUrl($baseUrl, $page['output']),
		'source' => $context,
	];
}

function llmsDocument($configuration, $baseUrl, $manifestPath, $pagesRoot)
{
	$titleValue = $configuration['title'] ?? getConf('PRODUCT_NAME');
	$title = llmsSingleLineText($titleValue, 'LLMS title', '.smgen.yaml');
	$sections = llmsSections($configuration);
	$directoryCache = [];

	foreach(llmsManifestPages($manifestPath) as $page)
	{
		[$selection, $metadata] = llmsPageSelection($page['source'], $pagesRoot, $directoryCache);
		if($selection === null || $selection === false)
		{
			continue;
		}

		$entry = llmsPageEntry($page, $selection, $metadata, $baseUrl);
		if(!array_key_exists($entry['section'], $sections))
		{
			throw new \RuntimeException('Unknown llms.txt section ' . $entry['section'] . ' for ' . $entry['source'] . '.');
		}
		$sections[$entry['section']]['pages'][] = $entry;
	}

	usort($sections, function($left, $right) {
		return $left['weight'] <=> $right['weight'] ?: $left['order'] <=> $right['order'];
	});

	$output = '# ' . $title . "\n\n";
	if(array_key_exists('summary', $configuration))
	{
		$summary = llmsScalarText($configuration['summary'], 'LLMS summary', '.smgen.yaml', true);
		if($summary !== '')
		{
			foreach(explode("\n", $summary) as $line)
			{
				$output .= '>' . ($line === '' ? '' : ' ' . $line) . "\n";
			}
			$output .= "\n";
		}
	}
	if(array_key_exists('details', $configuration))
	{
		$details = llmsScalarText($configuration['details'], 'LLMS details', '.smgen.yaml', true);
		if($details !== '')
		{
			$output .= $details . "\n\n";
		}
	}

	foreach($sections as $section)
	{
		if($section['pages'] === [])
		{
			continue;
		}
		usort($section['pages'], function($left, $right) {
			return $left['weight'] <=> $right['weight'] ?: strcmp($left['source'], $right['source']);
		});

		$output .= '## ' . $section['title'] . "\n\n";
		foreach($section['pages'] as $page)
		{
			$output .= '- [' . llmsMarkdownLabel($page['title']) . '](' . $page['url'] . ')';
			if($page['description'] !== null && $page['description'] !== '')
			{
				$output .= ': ' . $page['description'];
			}
			$output .= "\n";
		}
		$output .= "\n";
	}

	return rtrim($output) . "\n";
}

function generateLlmsFile($baseUrl, $buildDir, $manifestPath, $pagesRoot)
{
	$configuration = llmsConfiguration();
	if($configuration === null)
	{
		return;
	}

	$pagesRoot = realpath($pagesRoot);
	if($pagesRoot === false)
	{
		throw new \RuntimeException('PAGES_DIR does not exist while generating llms.txt.');
	}

	$outputPath = rtrim($buildDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'llms.txt';
	if(file_exists($outputPath) || is_link($outputPath))
	{
		throw new \RuntimeException('llms.txt collides with an existing output file.');
	}

	$document = llmsDocument($configuration, $baseUrl, $manifestPath, $pagesRoot);
	if(file_put_contents($outputPath, $document) === false)
	{
		throw new \RuntimeException('Unable to write llms.txt: ' . $outputPath);
	}
}
