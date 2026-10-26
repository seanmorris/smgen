<?php

namespace Smgen;

/**
 * Holds the immutable source, output, metadata, and plugin state for one page.
 */
final class PageRecord
{
	/** Create a page record from one catalog scan result. */
	public function __construct(
		public readonly string $source,
		public readonly string $relative,
		public readonly string $destination,
		public readonly string $extension,
		public readonly array $frontmatter,
		public readonly array $pluginFlags,
	) {
	}

	/** Return the root-relative route published for this physical output file. */
	public function publicPath(): string
	{
		return PublicUrl::fromOutputPath($this->destination);
	}
}
