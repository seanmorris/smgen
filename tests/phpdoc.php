<?php

/**
 * Verifies that the PHP engine documents every class and named method in prose.
 */
final class PhpDocRegression
{
	/** Run the source scan and exit nonzero when any declaration lacks a description. */
	public static function run(string $root): void
	{
		require_once($root . '/src/autoload.php');
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src/Smgen'));
		$failures = [];
		foreach($iterator as $file)
		{
			if(!$file->isFile() || $file->getExtension() !== 'php') continue;
			$relative = substr($file->getPathname(), strlen($root . '/src/Smgen/') );
			$class = 'Smgen\\' . str_replace('/', '\\', substr($relative, 0, -4));
			$reflection = new ReflectionClass($class);
			self::checkDescription($reflection->getDocComment(), $class, $failures);
			foreach($reflection->getMethods() as $method)
			{
				if($method->getDeclaringClass()->getName() !== $class) continue;
				self::checkDescription($method->getDocComment(), $class . '::' . $method->getName(), $failures);
			}
		}

		require_once($root . '/helpers/navigation-data.php');
		require_once($root . '/helpers/sitemap.php');
		foreach(['compileNavigationData', 'generateSitemapDocument'] as $function)
		{
			self::checkDescription((new ReflectionFunction($function))->getDocComment(), $function . '()', $failures);
		}

		if($failures !== [])
		{
			fwrite(STDERR, "Missing descriptive PHPDoc:\n- " . implode("\n- ", $failures) . "\n");
			exit(1);
		}
	}

	/** Strip PHPDoc syntax and require at least three words of descriptive prose. */
	private static function checkDescription(string|false $comment, string $declaration, array &$failures): void
	{
		if($comment === false)
		{
			$failures[] = $declaration;
			return;
		}
		$lines = preg_split('/\R/', $comment) ?: [];
		$description = [];
		foreach($lines as $line)
		{
			$line = trim(preg_replace('/^\s*\/\*\*?|\*\/\s*$|^\s*\*\s?/', '', $line) ?? '');
			if($line !== '' && !str_starts_with($line, '@')) $description[] = $line;
		}
		if(count(preg_split('/\s+/', implode(' ', $description), -1, PREG_SPLIT_NO_EMPTY) ?: []) < 3)
		{
			$failures[] = $declaration;
		}
	}
}

PhpDocRegression::run(realpath(__DIR__ . '/..') ?: dirname(__DIR__));
