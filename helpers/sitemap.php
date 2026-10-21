<?php

$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
$manifestPath = $argv[3] ?? ($argv[2] ?? '');

function xmlEscape($value)
{
	return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function encodeUrlPath($path)
{
	return implode('/', array_map('rawurlencode', explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $path))));
}

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

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
foreach($pages as $page)
{
	$url = $baseUrl . '/' . encodeUrlPath($page['output']);
	$modified = filemtime($page['source']);
	?>	<url>
		<loc><?=xmlEscape($url);?></loc>
		<lastmod><?=gmdate('Y-m-d', $modified === false ? time() : $modified);?></lastmod>
		<changefreq>daily</changefreq>
		<priority>0.8</priority>
	</url>
<?php
}
echo '</urlset>' . PHP_EOL;
