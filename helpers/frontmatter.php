<?php

function parseYamlText($yaml, $context)
{
	if(trim($yaml) === '')
	{
		return [];
	}

	set_error_handler(function($severity, $message) use ($context) {
		throw new \RuntimeException('Invalid YAML in ' . $context . ': ' . $message);
	});

	try
	{
		$value = yaml_parse($yaml);
	}
	finally
	{
		restore_error_handler();
	}

	if($value === false || $value === null)
	{
		return [];
	}

	if(!is_array($value))
	{
		throw new \RuntimeException('YAML metadata must be a mapping: ' . $context);
	}

	return $value;
}

function parsePageFrontmatter($path)
{
	$contents = file_get_contents($path);
	if($contents === false)
	{
		throw new \RuntimeException('Unable to read page: ' . $path);
	}

	$contents = str_replace(["\r\n", "\r"], "\n", $contents);
	if(strncmp($contents, "---\n", 4) !== 0)
	{
		return [];
	}

	$end = strpos($contents, "\n---\n", 4);
	if($end === false)
	{
		if(substr($contents, -4) === "\n---")
		{
			$end = strlen($contents) - 4;
		}
		else
		{
			throw new \RuntimeException('Unterminated YAML frontmatter: ' . $path);
		}
	}

	return parseYamlText(substr($contents, 4, $end - 4), $path);
}

function parseDirectoryFrontmatter($path)
{
	if(!is_file($path))
	{
		return [];
	}

	$contents = file_get_contents($path);
	if($contents === false)
	{
		throw new \RuntimeException('Unable to read directory metadata: ' . $path);
	}

	$normalized = str_replace(["\r\n", "\r"], "\n", $contents);
	if(strncmp($normalized, "---\n", 4) === 0)
	{
		return parsePageFrontmatter($path);
	}

	return parseYamlText($normalized, $path);
}
