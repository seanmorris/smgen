<?php

require_once(__DIR__ . '/frontmatter.php');

/** Escape a value for safe placement in an XML text node. */
function xmlEscape($value)
{
	return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Encode every segment of a relative path for use in a sitemap URL. */
function encodeUrlPath($path)
{
	return implode('/', array_map('rawurlencode', explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $path))));
}

/**
 * Render a deterministic sitemap document from the completed page manifest.
 */
function generateSitemapDocument($baseUrl, $manifestPath)
{
	$baseUrl = rtrim((string) $baseUrl, '/');
	$pages = [];
	$manifest = fopen($manifestPath, 'r');
	if($manifest === false)
	{
		throw new \RuntimeException('Unable to read page manifest: ' . $manifestPath);
	}
	while(($line = fgets($manifest)) !== false)
	{
		$parts = explode("\t", rtrim($line, "\r\n"), 2);
		if(count($parts) !== 2 || $parts[0] === '404.html')
		{
			continue;
		}
		$pages[] = ['output' => $parts[0], 'source' => $parts[1]];
	}
	fclose($manifest);
	usort($pages, fn($a, $b) => strcmp($a['output'], $b['output']));

	ob_start();
	try
	{
		echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
		foreach($pages as $page)
		{
			$url = $baseUrl . '/' . encodeUrlPath($page['output']);
			$metadata = parsePageFrontmatter($page['source']);
			$modified = filemtime($page['source']);
			if(array_key_exists('modified', $metadata))
			{
				$value = $metadata['modified'];
				if(!is_string($value) && !is_int($value) && !is_float($value))
				{
					throw new \RuntimeException('Modified date must be a string or number: ' . $page['source']);
				}
				try
				{
					$modified = (new \DateTimeImmutable(trim((string) $value)))->getTimestamp();
				}
				catch(\Exception $exception)
				{
					throw new \RuntimeException('Invalid modified date in ' . $page['source'] . ': ' . $value, 0, $exception);
				}
			}
			?>	<url>
			<loc><?=xmlEscape($url);?></loc>
			<lastmod><?=gmdate('Y-m-d', $modified === false ? time() : $modified);?></lastmod>
			<changefreq>daily</changefreq>
			<priority>0.8</priority>
		</url>
<?php
		}
		echo '</urlset>' . PHP_EOL;
		return (string) ob_get_clean();
	}
	catch(\Throwable $exception)
	{
		ob_end_clean();
		throw $exception;
	}
}

if(realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__)
{
	echo generateSitemapDocument((string) ($argv[1] ?? ''), $argv[3] ?? ($argv[2] ?? ''));
}
