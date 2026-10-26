<?php

namespace Smgen;

/**
 * Maps physical page outputs to the stable URLs published by a static host.
 */
final class PublicUrl
{
	/** Convert a generated output path into its root-relative public route. */
	public static function fromOutputPath(string $outputPath): string
	{
		$path = '/' . ltrim(str_replace('\\', '/', $outputPath), '/');
		if($path === '/index.html')
		{
			return '/';
		}
		if(str_ends_with($path, '/index.html'))
		{
			return substr($path, 0, -strlen('index.html'));
		}
		return $path;
	}
}
