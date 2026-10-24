<?php

function rssArrayIsList($value)
{
	return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
}

function rssToken($value, $context)
{
	$value = (string) $value;
	if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value))
	{
		throw new \RuntimeException('Invalid ' . $context . ': ' . $value);
	}
	return $value;
}

function rssRequiredText($value, $context)
{
	if(!is_string($value) && !is_int($value) && !is_float($value))
	{
		throw new \RuntimeException($context . ' must be text.');
	}
	$value = trim((string) $value);
	if($value === '')
	{
		throw new \RuntimeException($context . ' must not be empty.');
	}
	if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value))
	{
		throw new \RuntimeException($context . ' contains an invalid control character.');
	}
	return $value;
}

function rssOutputPath($value, $feedName)
{
	$value = rssRequiredText($value, 'RSS feed path for ' . $feedName);
	if(strpos($value, "\0") !== false || preg_match('/[\\\\?#\r\n\t]/', $value))
	{
		throw new \RuntimeException('Invalid RSS feed path for ' . $feedName . ': ' . $value);
	}

	$relative = ltrim($value, '/');
	$parts = explode('/', $relative);
	if($relative === '' || !preg_match('/\.xml$/i', $relative))
	{
		throw new \RuntimeException('RSS feed path must name an XML file for ' . $feedName . ': ' . $value);
	}
	foreach($parts as $part)
	{
		if($part === '' || $part === '.' || $part === '..')
		{
			throw new \RuntimeException('Invalid RSS feed path for ' . $feedName . ': ' . $value);
		}
	}
	return '/' . $relative;
}

function rssChannelLink($value, $feedName)
{
	$value = rssRequiredText($value, 'RSS channel link for ' . $feedName);
	if(preg_match('/[\r\n\t]/', $value))
	{
		throw new \RuntimeException('Invalid RSS channel link for ' . $feedName . ': ' . $value);
	}
	if(preg_match('/^(?:https?:)?\/\//i', $value) || $value[0] === '/')
	{
		return $value;
	}
	if(preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value))
	{
		throw new \RuntimeException('Invalid RSS channel link for ' . $feedName . ': ' . $value);
	}
	return '/' . ltrim($value, '/');
}

function rssFeedDefinitions($value = null)
{
	if(func_num_args() === 0)
	{
		$value = getConf('RSS_FEEDS');
	}
	if($value === null || $value === [])
	{
		return [];
	}
	if(!is_array($value) || rssArrayIsList($value))
	{
		throw new \RuntimeException('RSS_FEEDS must be a mapping.');
	}

	$definitions = [];
	$paths = [];
	foreach($value as $name => $config)
	{
		$name = rssToken($name, 'RSS feed name');
		if(!is_array($config) || ($config !== [] && rssArrayIsList($config)))
		{
			throw new \RuntimeException('RSS feed ' . $name . ' must be a mapping.');
		}
		foreach(array_keys($config) as $field)
		{
			if(!in_array($field, ['path', 'title', 'description', 'link', 'language', 'limit'], true))
			{
				throw new \RuntimeException('Unknown RSS feed ' . $name . ' field: ' . $field);
			}
		}
		if(!array_key_exists('title', $config) || !array_key_exists('description', $config))
		{
			throw new \RuntimeException('RSS feed ' . $name . ' requires title and description.');
		}

		$path = rssOutputPath($config['path'] ?? ('/' . $name . '.xml'), $name);
		if(isset($paths[$path]))
		{
			throw new \RuntimeException('RSS feeds ' . $paths[$path] . ' and ' . $name . ' use the same path: ' . $path);
		}
		$paths[$path] = $name;

		$limit = $config['limit'] ?? 20;
		if(is_string($limit) && preg_match('/^[0-9]+$/', $limit))
		{
			$limit = (int) $limit;
		}
		if(!is_int($limit) || $limit < 0)
		{
			throw new \RuntimeException('RSS feed limit must be a nonnegative integer for ' . $name . '.');
		}

		$definition = [
			'name' => $name,
			'path' => $path,
			'title' => rssRequiredText($config['title'], 'RSS feed title for ' . $name),
			'description' => rssRequiredText($config['description'], 'RSS feed description for ' . $name),
			'link' => rssChannelLink($config['link'] ?? '/', $name),
			'limit' => $limit,
		];
		if(array_key_exists('language', $config))
		{
			$definition['language'] = rssRequiredText($config['language'], 'RSS feed language for ' . $name);
		}
		$definitions[$name] = $definition;
	}
	return $definitions;
}

function rssFeedMemberships($value, $context)
{
	if($value === null)
	{
		return [];
	}
	if(rssArrayIsList($value))
	{
		$result = [];
		foreach($value as $name)
		{
			$result[rssToken($name, 'RSS feed name')] = true;
		}
		return $result;
	}
	if(!is_array($value))
	{
		throw new \RuntimeException('feeds must be a mapping or list in ' . $context);
	}

	$result = [];
	foreach($value as $name => $enabled)
	{
		$name = rssToken($name, 'RSS feed name');
		if(!is_bool($enabled))
		{
			throw new \RuntimeException('RSS feed membership must be true or false for ' . $name . ' in ' . $context);
		}
		$result[$name] = $enabled !== false;
	}
	return $result;
}

function rssResolveUrl($baseUrl, $path)
{
	if(preg_match('/^(?:https?:)?\/\//i', $path))
	{
		return $path;
	}
	$baseUrl = rtrim((string) $baseUrl, '/');
	$path = '/' . ltrim((string) $path, '/');
	return $baseUrl === '' ? $path : $baseUrl . $path;
}

function rssEncodeOutputUrl($baseUrl, $path)
{
	$path = str_replace(DIRECTORY_SEPARATOR, '/', (string) $path);
	$encoded = implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
	return rssResolveUrl($baseUrl, '/' . $encoded);
}

function rssXmlEscape($value)
{
	return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
