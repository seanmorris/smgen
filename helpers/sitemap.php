<?php

$domain = $argv[1];
$rootPath = './docs';

$directory = new \RecursiveIteratorIterator(
	new \RecursiveDirectoryIterator($rootPath)
);

echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

foreach($directory as $entry)
{
	$pathname = $entry->getPathname();
	$filename = $entry->getFilename();

	if(substr($pathname, -4) !== 'html' || substr($pathname, -8) === '404.html')
	{
		continue;
	}

	if(preg_match('/^google[0-9a-f].html$/', $filename))
	{
		continue;
	}

	$urlPath = substr($pathname, 1 + strlen($rootPath));

	if($urlPath[0] === '.')
	{
		continue;
	}

	$escapedPathname = escapeshellarg($pathname);
?>
	<url>
		<loc><?=$domain?>/<?=$urlPath?></loc>
		<lastmod><?=trim(`date -r $escapedPathname +"%Y-%m-%d"`);?></lastmod>
		<changefreq>daily</changefreq>
		<priority>0.8</priority>
	</url>
<?php
}

echo '</urlset>' . PHP_EOL;
