<?php

namespace Smgen;

/**
 * Scans page sources once and caches their parsed frontmatter for a build.
 */
final class PageCatalog
{
	private array $frontmatterCache = [];
	private array $directoryCache = [];

	/** Prepare a catalog rooted at the canonical page source directory. */
	public function __construct(private readonly string $pagesRoot)
	{
		require_once(dirname(__DIR__, 2) . '/helpers/frontmatter.php');
	}

	/** Discover source pages and reject duplicate output destinations. */
	public function scan(): array
	{
		$pages = [];
		$destinations = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->pagesRoot, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach($iterator as $file)
		{
			if(!$file->isFile())
			{
				continue;
			}
			$source = $file->getPathname();
			$relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($source, strlen($this->pagesRoot) + 1));
			if(
				$relative === '.fm.yaml'
				|| str_ends_with($relative, '/.fm.yaml')
				|| $relative === '.smgen-generated/.smgen-managed'
			)
			{
				continue;
			}
			$outputRelative = preg_replace('~^\.smgen-generated/[^/]+/~', '', $relative);
			if($outputRelative === null || $outputRelative === $relative && str_starts_with($relative, '.smgen-generated/'))
			{
				throw new \RuntimeException('Invalid generated page path: ' . $relative);
			}
			$base = basename($outputRelative);
			$extension = pathinfo($base, PATHINFO_EXTENSION);
			$pageName = pathinfo($base, PATHINFO_FILENAME);
			$directory = dirname($outputRelative);
			$destination = ($directory === '.' ? '' : $directory . '/') . $pageName . '.html';
			if(str_contains($destination, "\n") || str_contains($destination, "\t"))
			{
				throw new \RuntimeException('Page paths must not contain tabs or newlines: ' . $relative);
			}
			if(isset($destinations[$destination]))
			{
				throw new \RuntimeException('Multiple pages map to the same output: ' . $destination);
			}
			$destinations[$destination] = true;
			$frontmatter = $this->frontmatter($source);
			$pages[] = new PageRecord(
				$source,
				$relative,
				$destination,
				$extension,
				$frontmatter,
				$this->pluginFlags($relative, $frontmatter),
			);
		}
		return $pages;
	}

	/** Parse and cache the page-level frontmatter for one source file. */
	public function frontmatter(string $path): array
	{
		return $this->frontmatterCache[$path] ??= \parsePageFrontmatter($path);
	}

	/** Merge inherited directory plugin flags with page-level overrides. */
	private function pluginFlags(string $relative, array $frontmatter): array
	{
		$flags = [];
		$directory = dirname($relative);
		$parts = $directory === '.' ? [] : explode('/', $directory);
		$current = $this->pagesRoot;
		$metadataPaths = [$current . DIRECTORY_SEPARATOR . '.fm.yaml'];
		foreach($parts as $part)
		{
			$current .= DIRECTORY_SEPARATOR . $part;
			$metadataPaths[] = $current . DIRECTORY_SEPARATOR . '.fm.yaml';
		}
		foreach($metadataPaths as $metadataPath)
		{
			$metadata = $this->directoryCache[$metadataPath] ??= \parseDirectoryFrontmatter($metadataPath);
			$flags = array_replace($flags, $this->normalizePluginFlags($metadata['plugins'] ?? null, $metadataPath));
		}
		return array_replace($flags, $this->normalizePluginFlags($frontmatter['plugins'] ?? null, $relative));
	}

	/** Validate a plugin-flag mapping and normalize it to booleans by ID. */
	private function normalizePluginFlags(mixed $value, string $context): array
	{
		if($value === null)
		{
			return [];
		}
		if(!is_array($value) || ($value !== [] && array_keys($value) === range(0, count($value) - 1)))
		{
			throw new \RuntimeException('plugins metadata must be a mapping in ' . $context . '.');
		}
		$flags = [];
		foreach($value as $id => $enabled)
		{
			if(!is_string($id) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $id) || !is_bool($enabled))
			{
				throw new \RuntimeException('plugins metadata must map plugin IDs to booleans in ' . $context . '.');
			}
			$flags[$id] = $enabled;
		}
		return $flags;
	}
}
