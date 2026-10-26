<?php

function jsonLdArrayIsList($value)
{
	return is_array($value) && ($value === [] || array_keys($value) === range(0, count($value) - 1));
}

function jsonLdDocument($value, $source)
{
	if(!is_array($value))
	{
		throw new \RuntimeException('JSON-LD frontmatter must be a mapping or list of mappings: ' . $source);
	}

	if(jsonLdArrayIsList($value))
	{
		foreach($value as $index => $entry)
		{
			if(!is_array($entry) || jsonLdArrayIsList($entry))
			{
				throw new \RuntimeException(
					'JSON-LD frontmatter entry ' . ($index + 1) . ' must be a mapping: ' . $source
				);
			}
		}
	}

	try
	{
		$json = json_encode(
			$value,
			JSON_HEX_TAG
			| JSON_HEX_AMP
			| JSON_HEX_APOS
			| JSON_HEX_QUOT
			| JSON_UNESCAPED_SLASHES
			| JSON_UNESCAPED_UNICODE
			| JSON_PRESERVE_ZERO_FRACTION
			| JSON_THROW_ON_ERROR
		);
	}
	catch(\JsonException $exception)
	{
		throw new \RuntimeException(
			'Unable to encode JSON-LD frontmatter in ' . $source . ': ' . $exception->getMessage(),
			0,
			$exception
		);
	}

	return str_replace('$', '$$', $json);
}

function renderJsonLd($frontmatter, $source)
{
	if(!array_key_exists('jsonld', $frontmatter))
	{
		return;
	}

	echo "\t<script type=\"application/ld+json\">";
	echo jsonLdDocument($frontmatter['jsonld'], $source);
	echo "</script>\n";
}
