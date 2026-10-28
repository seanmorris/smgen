<?php

namespace Smgen\Template;

use Smgen\Config;
use Smgen\Filesystem;
use Smgen\PageRecord;

/**
 * Exposes immutable page data and rendering helpers to reusable templates.
 */
final class PageContext
{
	/** Bind one page, its resolved settings, and the fragment renderer. */
	public function __construct(
		private readonly Config $config,
		private readonly PageRecord $page,
		private readonly string $templateRoot,
		private readonly \Closure $fragmentRenderer,
		private readonly array $overrides = [],
	) {
	}

	/** Read a page-specific resolved setting before falling back to site config. */
	public function config(string $name, mixed $default = null): mixed
	{
		if(array_key_exists($name, $this->overrides))
		{
			$value = $this->overrides[$name];
			if(in_array($name, Config::LIST_KEYS, true))
			{
				return $value === '' ? [] : explode("\n", (string) $value);
			}
			return $value;
		}
		return $this->config->get($name, $default);
	}

	/** Return parsed page frontmatter exactly as authored. */
	public function frontmatter(): array
	{
		return $this->page->frontmatter;
	}

	/** Return page metadata with inherited plugin decisions included. */
	public function metadata(): array
	{
		$metadata = $this->page->frontmatter;
		if($this->page->pluginFlags !== [])
		{
			$metadata['plugins'] = $this->page->pluginFlags;
		}
		return $metadata;
	}

	/** Return the canonical source file path for the current page. */
	public function sourcePath(): string
	{
		return $this->page->source;
	}

	/** Return the source path relative to the configured page root. */
	public function sourceRelativePath(): string
	{
		return $this->page->relative;
	}

	/** Return the generated HTML path relative to staged output. */
	public function destinationPath(): string
	{
		return $this->page->destination;
	}

	/** Return the root-relative generated URL used by navigation helpers. */
	public function currentPage(): string
	{
		return $this->page->publicPath();
	}

	/** Escape a value for safe insertion into an HTML attribute or text node. */
	public function escape(mixed $value): string
	{
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** Resolve a local asset against BASE_URL while preserving external URLs. */
	public function assetUrl(string $path): string
	{
		if(preg_match('/^(?:[a-z]+:)?\/\//i', $path) || preg_match('/^(?:data|mailto|tel):/i', $path))
		{
			return $path;
		}
		$baseUrl = rtrim((string) $this->config('BASE_URL', ''), '/');
		$assetPath = ltrim($path, '/');
		if($assetPath === '')
		{
			return $baseUrl !== '' ? $baseUrl . '/' : '/';
		}
		return $baseUrl ? $baseUrl . '/' . $assetPath : '/' . $assetPath;
	}

	/** Report whether project or selected core static input contains an asset. */
	public function staticAssetExists(string $path): bool
	{
		$relative = ltrim($path, '/');
		$staticDir = (string) $this->config('STATIC_DIR', './static');
		if(is_file($staticDir . DIRECTORY_SEPARATOR . $relative))
		{
			return true;
		}
		$coreDir = getenv('SMGEN_CORE_DIR') ?: dirname(__DIR__, 3);
		if((string) $this->config('INHERIT_CORE_STATIC', '0') === '1')
		{
			return is_file($coreDir . '/static/' . $relative);
		}
		foreach($this->config('CORE_ASSETS', []) ?: [] as $asset)
		{
			if(ltrim((string) $asset, '/') === $relative && is_file($coreDir . '/static/' . $relative))
			{
				return true;
			}
		}
		return false;
	}

	/** Render validated JSON-LD frontmatter into a script element. */
	public function renderJsonLd(): string
	{
		ob_start();
		try
		{
			\renderJsonLd($this->page->frontmatter, $this->page->source);
			return (string) ob_get_clean();
		}
		catch(\Throwable $exception)
		{
			ob_end_clean();
			throw $exception;
		}
	}

	/** Report whether the project has enabled llms.txt generation. */
	public function llmsEnabled(): bool
	{
		return \llmsEnabled();
	}

	/** Return normalized RSS feed definitions for alternate-link markup. */
	public function rssFeeds(): array
	{
		return \rssFeedDefinitions();
	}

	/** Report whether navigation defines a named template region. */
	public function menuRegionExists(string $name): bool
	{
		return \menuRegionExists($name);
	}

	/** Capture the static markup for one named menu. */
	public function renderMenu(string $name, array $options = []): string
	{
		return $this->capture(static fn() => \renderMenu($name, $options));
	}

	/** Capture the static markup for every menu assigned to one region. */
	public function renderMenuRegion(string $name, array $options = []): string
	{
		return $this->capture(static fn() => \renderMenuRegion($name, $options));
	}

	/**
	 * Return the breadcrumb trail for this page: frontmatter ancestors or the menu chain.
	 *
	 * @param array<string, mixed> $options navigationTrail() options.
	 * @return list<array{title: string, href: ?string, current: bool}> Crumbs, oldest first.
	 */
	public function breadcrumbs(array $options = []): array
	{
		return \navigationTrail($options + ['frontmatter' => $this->page->frontmatter]);
	}

	/** Capture the breadcrumb navigation markup; empty for a page with no ancestors. */
	public function renderBreadcrumbs(array $options = []): string
	{
		return $this->capture(fn() => \renderBreadcrumbs($options + ['frontmatter' => $this->page->frontmatter]));
	}

	/** Capture the compatibility sidebar navigation markup. */
	public function renderNavBar(): string
	{
		return $this->capture(static fn() => \renderNavBar());
	}

	/** Render another reusable provider inside the configured template root. */
	public function renderFragment(string $path): string
	{
		$candidate = Filesystem::resolveProjectPath(getcwd(), $path);
		$resolved = Filesystem::canonicalFileWithin($candidate, $this->templateRoot, 'Reusable template fragment');
		if(!str_ends_with($resolved, '.smgen.php'))
		{
			throw new \RuntimeException('Reusable template fragments must end in .smgen.php: ' . $path);
		}
		return ($this->fragmentRenderer)($resolved, $this);
	}

	/** Capture output from a legacy rendering callback and preserve exceptions. */
	private function capture(callable $callback): string
	{
		ob_start();
		try
		{
			$callback();
			return (string) ob_get_clean();
		}
		catch(\Throwable $exception)
		{
			ob_end_clean();
			throw $exception;
		}
	}
}
