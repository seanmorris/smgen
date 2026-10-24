<?php

require_once(__DIR__ . '/frontmatter.php');
require_once(__DIR__ . '/getConf.php');
require_once(__DIR__ . '/rss-lib.php');

function rssReadManifest($path)
{
	$manifest = fopen($path, 'r');
	if($manifest === false)
	{
		throw new \RuntimeException('Unable to read page manifest: ' . $path);
	}

	$pages = [];
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
	return $pages;
}

function rssPageDate($metadata, $source)
{
	$field = array_key_exists('date', $metadata) ? 'date' : (array_key_exists('created', $metadata) ? 'created' : null);
	if($field === null)
	{
		throw new \RuntimeException('RSS page requires date or created: ' . $source);
	}
	$value = $metadata[$field];
	if(!is_string($value) && !is_int($value) && !is_float($value))
	{
		throw new \RuntimeException('RSS date must be a string or number: ' . $source);
	}
	if(is_int($value) || is_float($value))
	{
		$date = (new \DateTimeImmutable('@' . (int) $value))->setTimezone(new \DateTimeZone('UTC'));
		return ['timestamp' => $date->getTimestamp(), 'formatted' => $date->format(\DateTimeInterface::RSS)];
	}

	$value = trim($value);
	if($value === '')
	{
		throw new \RuntimeException('RSS date must not be empty: ' . $source);
	}
	try
	{
		$date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
	}
	catch(\Exception $exception)
	{
		throw new \RuntimeException('Invalid RSS date in ' . $source . ': ' . $value, 0, $exception);
	}
	$date = $date->setTimezone(new \DateTimeZone('UTC'));
	return ['timestamp' => $date->getTimestamp(), 'formatted' => $date->format(\DateTimeInterface::RSS)];
}

function rssPageTitle($metadata, $output, $source)
{
	foreach(['title', 'pagetitle'] as $field)
	{
		if(array_key_exists($field, $metadata))
		{
			return rssRequiredText($metadata[$field], 'RSS item title in ' . $source);
		}
	}
	$name = preg_replace('/\.[^.]+$/', '', basename($output));
	return ucwords(str_replace(['-', '_'], ' ', $name));
}

function rssPageDescription($metadata, $source)
{
	if(!array_key_exists('description-meta', $metadata) || $metadata['description-meta'] === null)
	{
		return null;
	}
	return rssRequiredText($metadata['description-meta'], 'RSS item description in ' . $source);
}

function rssFeedItems($definitions, $manifestPath, $baseUrl)
{
	$items = array_fill_keys(array_keys($definitions), []);
	foreach(rssReadManifest($manifestPath) as $page)
	{
		$metadata = parsePageFrontmatter($page['source']);
		if(!array_key_exists('feeds', $metadata))
		{
			continue;
		}

		$memberships = rssFeedMemberships($metadata['feeds'], $page['source']);
		$selected = [];
		foreach($memberships as $feedName => $enabled)
		{
			if(!isset($definitions[$feedName]))
			{
				throw new \RuntimeException('Unknown RSS feed ' . $feedName . ' in ' . $page['source']);
			}
			if($enabled)
			{
				$selected[] = $feedName;
			}
		}
		if(!$selected)
		{
			continue;
		}

		$date = rssPageDate($metadata, $page['source']);
		$item = [
			'title' => rssPageTitle($metadata, $page['output'], $page['source']),
			'link' => rssEncodeOutputUrl($baseUrl, $page['output']),
			'pubDate' => $date['formatted'],
			'timestamp' => $date['timestamp'],
			'description' => rssPageDescription($metadata, $page['source']),
		];
		foreach($selected as $feedName)
		{
			$items[$feedName][] = $item;
		}
	}

	foreach($items as $feedName => &$feedItems)
	{
		usort($feedItems, function($first, $second) {
			$byDate = $second['timestamp'] <=> $first['timestamp'];
			return $byDate !== 0 ? $byDate : strcmp($first['link'], $second['link']);
		});
		$limit = $definitions[$feedName]['limit'];
		if($limit > 0)
		{
			$feedItems = array_slice($feedItems, 0, $limit);
		}
	}
	unset($feedItems);
	return $items;
}

function rssRenderFeed($definition, $items, $baseUrl)
{
	$selfUrl = rssEncodeOutputUrl($baseUrl, $definition['path']);
	$channelUrl = rssResolveUrl($baseUrl, $definition['link']);
	$xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
	$xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . PHP_EOL;
	$xml .= "\t<channel>" . PHP_EOL;
	$xml .= "\t\t<title>" . rssXmlEscape($definition['title']) . '</title>' . PHP_EOL;
	$xml .= "\t\t<link>" . rssXmlEscape($channelUrl) . '</link>' . PHP_EOL;
	$xml .= "\t\t<description>" . rssXmlEscape($definition['description']) . '</description>' . PHP_EOL;
	$xml .= "\t\t<atom:link href=\"" . rssXmlEscape($selfUrl) . '" rel="self" type="application/rss+xml" />' . PHP_EOL;
	if(isset($definition['language']))
	{
		$xml .= "\t\t<language>" . rssXmlEscape($definition['language']) . '</language>' . PHP_EOL;
	}
	if($items)
	{
		$xml .= "\t\t<lastBuildDate>" . rssXmlEscape($items[0]['pubDate']) . '</lastBuildDate>' . PHP_EOL;
	}
	$xml .= "\t\t<generator>SMGen</generator>" . PHP_EOL;
	foreach($items as $item)
	{
		$xml .= "\t\t<item>" . PHP_EOL;
		$xml .= "\t\t\t<title>" . rssXmlEscape($item['title']) . '</title>' . PHP_EOL;
		$xml .= "\t\t\t<link>" . rssXmlEscape($item['link']) . '</link>' . PHP_EOL;
		$xml .= "\t\t\t<guid isPermaLink=\"true\">" . rssXmlEscape($item['link']) . '</guid>' . PHP_EOL;
		$xml .= "\t\t\t<pubDate>" . rssXmlEscape($item['pubDate']) . '</pubDate>' . PHP_EOL;
		if($item['description'] !== null)
		{
			$xml .= "\t\t\t<description>" . rssXmlEscape($item['description']) . '</description>' . PHP_EOL;
		}
		$xml .= "\t\t</item>" . PHP_EOL;
	}
	$xml .= "\t</channel>" . PHP_EOL;
	$xml .= '</rss>' . PHP_EOL;
	return $xml;
}

function rssOutputTarget($buildDir, $path, $feedName)
{
	$root = realpath($buildDir);
	if($root === false || !is_dir($root))
	{
		throw new \RuntimeException('RSS output directory does not exist: ' . $buildDir);
	}
	$parts = explode('/', ltrim($path, '/'));
	$filename = array_pop($parts);
	$current = $root;
	foreach($parts as $part)
	{
		$current .= DIRECTORY_SEPARATOR . $part;
		if(is_link($current))
		{
			throw new \RuntimeException('RSS feed path crosses a symbolic link for ' . $feedName . ': ' . $path);
		}
		if(file_exists($current) && !is_dir($current))
		{
			throw new \RuntimeException('RSS feed path collides with an output file for ' . $feedName . ': ' . $path);
		}
		if(!is_dir($current) && !mkdir($current, 0755))
		{
			throw new \RuntimeException('Unable to create RSS feed directory for ' . $feedName . ': ' . $path);
		}
	}

	$target = $current . DIRECTORY_SEPARATOR . $filename;
	if(file_exists($target) || is_link($target))
	{
		throw new \RuntimeException('RSS feed path collides with existing output for ' . $feedName . ': ' . $path);
	}
	return $target;
}

function rssGenerateFeeds($baseUrl, $buildDir, $manifestPath)
{
	$definitions = rssFeedDefinitions();
	if(!$definitions)
	{
		return;
	}
	$items = rssFeedItems($definitions, $manifestPath, $baseUrl);
	foreach($definitions as $name => $definition)
	{
		$target = rssOutputTarget($buildDir, $definition['path'], $name);
		if(file_put_contents($target, rssRenderFeed($definition, $items[$name], $baseUrl)) === false)
		{
			throw new \RuntimeException('Unable to write RSS feed ' . $name . ': ' . $definition['path']);
		}
	}
}

rssGenerateFeeds((string) ($argv[1] ?? ''), $argv[2] ?? '', $argv[3] ?? '');
