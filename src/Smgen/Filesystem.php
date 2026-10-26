<?php

namespace Smgen;

/**
 * Provides guarded filesystem operations for staged and source content.
 */
final class Filesystem
{
	/** Resolve an existing directory or fail with a setting-specific message. */
	public static function canonicalDirectory(string $path, string $label): string
	{
		$resolved = realpath($path);
		if($resolved === false || !is_dir($resolved))
		{
			throw new \RuntimeException($label . ' does not exist: ' . $path);
		}
		return $resolved;
	}

	/** Resolve an existing file and require it to remain inside an allowed root. */
	public static function canonicalFileWithin(string $path, string $root, string $label): string
	{
		$resolved = self::canonicalEntryWithin($path, $root, $label);
		if(!is_file($resolved))
		{
			throw new \RuntimeException($label . ' is not a file: ' . $path);
		}
		return $resolved;
	}

	/** Resolve an existing file or directory inside an allowed root. */
	public static function canonicalEntryWithin(string $path, string $root, string $label): string
	{
		$root = self::canonicalDirectory($root, $label . ' root');
		$resolved = realpath($path);
		if($resolved === false || (!is_file($resolved) && !is_dir($resolved)))
		{
			throw new \RuntimeException($label . ' does not exist: ' . $path);
		}
		if($resolved !== $root && !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR))
		{
			throw new \RuntimeException($label . ' must stay inside ' . $root . ': ' . $path);
		}
		return $resolved;
	}

	/** Resolve a project-relative path while preserving absolute paths. */
	public static function resolveProjectPath(string $projectDir, string $path): string
	{
		return self::isAbsolute($path) ? $path : $projectDir . DIRECTORY_SEPARATOR . $path;
	}

	/** Report whether a path is absolute on Unix or Windows. */
	public static function isAbsolute(string $path): bool
	{
		return $path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1);
	}

	/** Normalize a safe relative output path and reject traversal segments. */
	public static function assertRelativeOutputPath(string $path, string $label): string
	{
		$path = str_replace('\\', '/', $path);
		$path = ltrim($path, '/');
		$segments = explode('/', $path);
		if(
			$path === ''
			|| in_array('.', $segments, true)
			|| in_array('..', $segments, true)
			|| str_contains($path, "\0")
			|| str_contains($path, "\n")
			|| str_contains($path, "\r")
		)
		{
			throw new \RuntimeException('Invalid ' . $label . ': ' . $path);
		}
		return $path;
	}

	/** Create a private temporary directory with a random collision-resistant suffix. */
	public static function temporaryDirectory(string $parent, string $prefix): string
	{
		for($attempt = 0; $attempt < 100; $attempt++)
		{
			$suffix = bin2hex(random_bytes(6));
			$path = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . $suffix;
			if(@mkdir($path, 0700))
			{
				return $path;
			}
		}
		throw new \RuntimeException('Unable to create temporary directory in ' . $parent . '.');
	}

	/** Recursively copy a directory while applying guarded entry replacement. */
	public static function copyTree(string $source, string $destination): void
	{
		if(!is_dir($source))
		{
			return;
		}
		self::ensureDirectory($destination);
		$entries = scandir($source);
		if($entries === false)
		{
			throw new \RuntimeException('Unable to read directory: ' . $source);
		}
		foreach($entries as $entry)
		{
			if($entry === '.' || $entry === '..')
			{
				continue;
			}
			self::copyEntry($source . DIRECTORY_SEPARATOR . $entry, $destination . DIRECTORY_SEPARATOR . $entry);
		}
	}

	/** Copy one file, directory, or compatibility symlink into staged output. */
	public static function copyEntry(string $source, string $destination): void
	{
		if(is_link($source))
		{
			self::replaceDestination($destination);
			$target = readlink($source);
			if($target === false || !symlink($target, $destination))
			{
				throw new \RuntimeException('Unable to copy symbolic link: ' . $source);
			}
			return;
		}
		if(is_dir($source))
		{
			self::ensureDirectory($destination);
			self::copyTree($source, $destination);
			@chmod($destination, fileperms($source) & 0777);
			@touch($destination, filemtime($source) ?: time());
			return;
		}
		if(!is_file($source))
		{
			throw new \RuntimeException('Static source is not a file or directory: ' . $source);
		}
		self::ensureDirectory(dirname($destination));
		self::replaceDestination($destination);
		if(!copy($source, $destination))
		{
			throw new \RuntimeException('Unable to copy file: ' . $source);
		}
		@chmod($destination, fileperms($source) & 0777);
		@touch($destination, filemtime($source) ?: time());
	}

	/** Reject plugin asset trees that could publish links back into source storage. */
	public static function rejectSymlinksInTree(string $path, string $label): void
	{
		if(is_link($path))
		{
			throw new \RuntimeException($label . ' contains a symbolic link: ' . $path);
		}
		if(!is_dir($path)) return;
		$entries = scandir($path);
		if($entries === false)
		{
			throw new \RuntimeException('Unable to inspect ' . $label . ': ' . $path);
		}
		foreach($entries as $entry)
		{
			if($entry === '.' || $entry === '..') continue;
			self::rejectSymlinksInTree($path . DIRECTORY_SEPARATOR . $entry, $label);
		}
	}

	/** Remove a file, link, or directory tree without following symbolic links. */
	public static function removeTree(string $path): void
	{
		if(is_link($path) || is_file($path))
		{
			if(!@unlink($path) && (is_link($path) || file_exists($path)))
			{
				throw new \RuntimeException('Unable to remove path: ' . $path);
			}
			return;
		}
		if(!is_dir($path))
		{
			return;
		}
		$entries = scandir($path);
		if($entries === false)
		{
			throw new \RuntimeException('Unable to read directory for removal: ' . $path);
		}
		foreach($entries as $entry)
		{
			if($entry !== '.' && $entry !== '..')
			{
				self::removeTree($path . DIRECTORY_SEPARATOR . $entry);
			}
		}
		if(!@rmdir($path) && is_dir($path))
		{
			throw new \RuntimeException('Unable to remove directory: ' . $path);
		}
	}

	/** Resolve an output target after rejecting links and files in its parent path. */
	public static function assertNoSymlinkSegments(string $root, string $relative, string $label): string
	{
		$relative = self::assertRelativeOutputPath($relative, $label);
		$current = rtrim($root, DIRECTORY_SEPARATOR);
		$parts = explode('/', $relative);
		foreach($parts as $index => $part)
		{
			$current .= DIRECTORY_SEPARATOR . $part;
			if(is_link($current))
			{
				throw new \RuntimeException($label . ' crosses a symbolic link: ' . $relative);
			}
			if($index < count($parts) - 1 && file_exists($current) && !is_dir($current))
			{
				throw new \RuntimeException($label . ' crosses an output file: ' . $relative);
			}
		}
		return $current;
	}

	/** Create a directory after removing an incompatible destination entry. */
	private static function ensureDirectory(string $path): void
	{
		if(is_link($path) || (file_exists($path) && !is_dir($path)))
		{
			self::removeTree($path);
		}
		if(is_dir($path))
		{
			return;
		}
		if(!mkdir($path, 0755, true) && !is_dir($path))
		{
			throw new \RuntimeException('Unable to create directory: ' . $path);
		}
	}

	/** Remove an existing destination so a copy can replace it predictably. */
	private static function replaceDestination(string $path): void
	{
		if(file_exists($path) || is_link($path))
		{
			self::removeTree($path);
		}
	}
}
