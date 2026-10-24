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
	if(preg_match('//u', $value) !== 1)
	{
		throw new \RuntimeException($context . ' must be valid UTF-8.');
	}
	return $value;
}

function rssXmlValue($value, $context)
{
	if(is_bool($value))
	{
		return $value ? 'true' : 'false';
	}
	if(!is_string($value) && !is_int($value) && !is_float($value))
	{
		throw new \RuntimeException($context . ' must be a scalar value.');
	}
	$value = (string) $value;
	if(preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value))
	{
		throw new \RuntimeException($context . ' contains an invalid control character.');
	}
	if(preg_match('//u', $value) !== 1)
	{
		throw new \RuntimeException($context . ' must be valid UTF-8.');
	}
	return $value;
}

function rssXmlName($value, $context)
{
	if(!is_string($value))
	{
		throw new \RuntimeException($context . ' must be an XML name.');
	}
	$part = '[A-Za-z_][A-Za-z0-9._-]*';
	if(!preg_match('/^' . $part . '(?::' . $part . ')?$/D', $value)
		|| strcasecmp($value, 'xmlns') === 0
		|| stripos($value, 'xmlns:') === 0)
	{
		throw new \RuntimeException('Invalid ' . $context . ': ' . $value);
	}
	return $value;
}

function rssNamespaceDefinitions($value, $feedName)
{
	if($value === null || $value === [])
	{
		return [];
	}
	if(!is_array($value) || rssArrayIsList($value))
	{
		throw new \RuntimeException('RSS feed namespaces must be a mapping for ' . $feedName . '.');
	}

	$result = [];
	foreach($value as $prefix => $uri)
	{
		$prefix = rssXmlName($prefix, 'RSS namespace prefix for ' . $feedName);
		if(strpos($prefix, ':') !== false || preg_match('/^xml/i', $prefix) || strcasecmp($prefix, 'atom') === 0)
		{
			throw new \RuntimeException('Reserved RSS namespace prefix for ' . $feedName . ': ' . $prefix);
		}
		$result[$prefix] = rssRequiredText($uri, 'RSS namespace URI for ' . $prefix . ' in ' . $feedName);
	}
	return $result;
}

function rssCustomNode($value, $context)
{
	if($value === null)
	{
		return null;
	}
	if(!is_array($value))
	{
		return rssXmlValue($value, $context);
	}
	if(rssArrayIsList($value))
	{
		$result = [];
		foreach($value as $index => $item)
		{
			$result[] = rssCustomNode($item, $context . ' entry ' . ($index + 1));
		}
		return $result;
	}

	$result = [];
	$hasText = false;
	$hasChildren = false;
	foreach($value as $name => $item)
	{
		if($name === '_attributes')
		{
			if(!is_array($item) || ($item !== [] && rssArrayIsList($item)))
			{
				throw new \RuntimeException($context . ' _attributes must be a mapping.');
			}
			$attributes = [];
			foreach($item as $attribute => $attributeValue)
			{
				$attribute = rssXmlName($attribute, $context . ' attribute name');
				$attributes[$attribute] = rssXmlValue($attributeValue, $context . ' attribute ' . $attribute);
			}
			$result['_attributes'] = $attributes;
			continue;
		}
		if($name === '_text')
		{
			$result['_text'] = rssXmlValue($item, $context . ' text');
			$hasText = true;
			continue;
		}

		$name = rssXmlName($name, $context . ' child element');
		$result[$name] = rssCustomNode($item, $context . ' child ' . $name);
		$hasChildren = true;
	}
	if($hasText && $hasChildren)
	{
		throw new \RuntimeException($context . ' cannot contain both _text and child elements.');
	}
	return $result;
}

function rssCustomElements($value, $context, $reserved = [])
{
	if($value === null || $value === [])
	{
		return [];
	}
	if(!is_array($value) || rssArrayIsList($value))
	{
		throw new \RuntimeException($context . ' must be a mapping.');
	}

	$result = [];
	foreach($value as $name => $node)
	{
		$name = rssXmlName($name, $context . ' element');
		if(in_array($name, $reserved, true))
		{
			throw new \RuntimeException($context . ' cannot replace generated element ' . $name . '.');
		}
		$result[$name] = rssCustomNode($node, $context . ' element ' . $name);
	}
	return $result;
}

function rssValidateXmlNamespace($name, $namespaces, $context)
{
	if(strpos($name, ':') === false)
	{
		return;
	}
	$prefix = strstr($name, ':', true);
	if($prefix !== 'atom' && $prefix !== 'xml' && !array_key_exists($prefix, $namespaces))
	{
		throw new \RuntimeException('Unknown RSS namespace prefix ' . $prefix . ' in ' . $context . '.');
	}
}

function rssValidateCustomNodeNamespaces($value, $namespaces, $context)
{
	if(!is_array($value))
	{
		return;
	}
	if(rssArrayIsList($value))
	{
		foreach($value as $item)
		{
			rssValidateCustomNodeNamespaces($item, $namespaces, $context);
		}
		return;
	}
	foreach($value['_attributes'] ?? [] as $name => $attributeValue)
	{
		rssValidateXmlNamespace($name, $namespaces, $context . ' attribute ' . $name);
	}
	foreach($value as $name => $item)
	{
		if($name === '_attributes' || $name === '_text')
		{
			continue;
		}
		rssValidateXmlNamespace($name, $namespaces, $context . ' child ' . $name);
		rssValidateCustomNodeNamespaces($item, $namespaces, $context . ' child ' . $name);
	}
}

function rssValidateCustomNamespaces($elements, $namespaces, $context)
{
	foreach($elements as $name => $node)
	{
		rssValidateXmlNamespace($name, $namespaces, $context . ' element ' . $name);
		rssValidateCustomNodeNamespaces($node, $namespaces, $context . ' element ' . $name);
	}
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
	$allowedFields = ['path', 'title', 'description', 'link', 'language', 'limit', 'namespaces', 'channel'];
	foreach($value as $name => $config)
	{
		$name = rssToken($name, 'RSS feed name');
		if(!is_array($config) || ($config !== [] && rssArrayIsList($config)))
		{
			throw new \RuntimeException('RSS feed ' . $name . ' must be a mapping.');
		}
		foreach(array_keys($config) as $field)
		{
			if(!in_array($field, $allowedFields, true))
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
			throw new \RuntimeException(
				'RSS feeds ' . $paths[$path] . ' and ' . $name . ' use the same path: ' . $path
			);
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
			'namespaces' => rssNamespaceDefinitions($config['namespaces'] ?? [], $name),
			'channel' => rssCustomElements(
				$config['channel'] ?? [],
				'RSS channel data for ' . $name,
				['title', 'link', 'description', 'language', 'lastBuildDate', 'generator', 'item']
			),
		];
		if(array_key_exists('language', $config))
		{
			$definition['language'] = rssRequiredText($config['language'], 'RSS feed language for ' . $name);
		}
		rssValidateCustomNamespaces(
			$definition['channel'],
			$definition['namespaces'],
			'RSS channel data for ' . $name
		);
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
