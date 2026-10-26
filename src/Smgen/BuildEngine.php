<?php

namespace Smgen;

use Smgen\Template\Renderer as TemplateRenderer;

/**
 * Coordinates one atomic SMGen build while preserving legacy extension points.
 */
final class BuildEngine
{
	private readonly string $coreDir;
	private readonly string $projectDir;
	private readonly ProcessRunner $processes;
	private string $pagesRoot;
	private string $templateRoot;
	private string $staticRoot;
	private string $helpersRoot;
	private string $outputAbsolute;
	private ?PluginRegistry $plugins = null;
	private ?TemplateRenderer $templateRenderer = null;
	private ?string $generatedPagesRoot = null;

	/** Resolve core and project roots and prepare the subprocess runner. */
	public function __construct(private Config $config, ?string $coreDir = null, ?string $projectDir = null)
	{
		$this->coreDir = realpath($coreDir ?? dirname(__DIR__, 2)) ?: ($coreDir ?? dirname(__DIR__, 2));
		$this->projectDir = realpath($projectDir ?? getcwd()) ?: ($projectDir ?? getcwd());
		$this->processes = new ProcessRunner();
	}

	/** Build every page and atomically install the completed staged output. */
	public function build(?string $requestedPath = null): void
	{
		$this->validateRuntime($requestedPath);
		$lock = $this->acquireBuildLock();
		try
		{
			$this->buildLocked();
		}
		finally
		{
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/** Execute a validated build while the output-specific process lock is held. */
	private function buildLocked(): void
	{
		$environment = $this->config->environment();
		$environment['SMGEN_CORE_DIR'] = $this->coreDir;
		$environment['SMGEN_PROJECT_DIR'] = $this->projectDir;
		$environment['SMGEN_PROJECT_HELPERS_DIR'] = (string) $this->config->get('HELPERS_DIR');

		$before = $this->projectDir . '/before-smgen.sh';
		if(is_file($before))
		{
			$this->mustRun(['bash', './before-smgen.sh'], $environment);
		}

		$this->plugins = new PluginRegistry($this->projectDir, $this->pluginActivations());
		$pluginEnvironment = $environment;
		$pluginEnvironment['PLUGINS'] = implode("\n", $this->plugins->manifestPaths());
		$this->installEnvironment($pluginEnvironment);

		$outputParent = dirname($this->outputAbsolute);
		$outputName = basename($this->outputAbsolute);
		$buildDir = '';
		$workDir = '';
		$backupPath = null;
		$outputInstalled = false;
		$committed = false;

		try
		{
			$buildDir = Filesystem::temporaryDirectory($outputParent, '.' . $outputName . '.smgen-build.');
			if(!chmod($buildDir, 0755))
			{
				throw new \RuntimeException('Unable to set staged output permissions: ' . $buildDir);
			}
			$workDir = Filesystem::temporaryDirectory(getenv('TMPDIR') ?: '/tmp', 'smgen.');
			$this->removeManagedGeneratedPages();
			$this->runPluginHooks('prepare', $workDir, null, null, $pluginEnvironment);

			$navigationPath = $workDir . '/navigation.json';
			$pageManifest = $workDir . '/pages.tsv';
			if(file_put_contents($pageManifest, '') === false)
			{
				throw new \RuntimeException('Unable to create the page manifest: ' . $pageManifest);
			}
			$navigation = $this->buildNavigation($navigationPath, $pluginEnvironment);
			$pluginEnvironment['SMGEN_NAV_DATA'] = $navigationPath;
			$this->installEnvironment(['SMGEN_NAV_DATA' => $navigationPath]);

			$catalog = new PageCatalog($this->pagesRoot);
			$pages = $catalog->scan();
			$selectedPlugins = $this->plugins->selectedPlugins($pages);
			$this->copyStaticAssets($buildDir, $selectedPlugins);

			$siteStyles = $this->stringList($this->config->get('STYLES'), 'STYLES');
			if(in_array('/archive.css', $navigation['assets'] ?? [], true) && !in_array('/archive.css', $siteStyles, true))
			{
				$siteStyles[] = '/archive.css';
				if(!is_file($buildDir . '/archive.css'))
				{
					Filesystem::copyEntry($this->coreDir . '/static/archive.css', $buildDir . '/archive.css');
				}
			}
			$searchEnabled = $this->resolveSearchSetting($buildDir);
			$this->prepareSearchReader($buildDir, $searchEnabled);
			$pluginEnvironment['SEARCH_ENABLED'] = $searchEnabled ? '1' : '0';
			$this->installEnvironment(['SEARCH_ENABLED' => $pluginEnvironment['SEARCH_ENABLED']]);

			$this->templateRenderer = new TemplateRenderer($this->config, $this->templateRoot);
			fwrite(STDOUT, "\033[33;4mBuilding pages...\033[0m\n");
			foreach($pages as $page)
			{
				$this->buildPage($page, $buildDir, $workDir, $pageManifest, $siteStyles, $pluginEnvironment);
			}

			if($searchEnabled && $this->processes->commandExists((string) $this->config->get('SMG_SEARCH')))
			{
				fwrite(STDOUT, "\033[33;4mAssembling search index...\033[0m\n");
				Filesystem::assertNoSymlinkSegments($buildDir, 'search.bin', 'Search index output');
				$this->mustRun([
					(string) $this->config->get('SMG_SEARCH'), 'build-index',
					(string) $this->config->get('PAGES_DIR'), $buildDir . '/search.bin',
				], $pluginEnvironment);
			}
			elseif($searchEnabled && !is_file($buildDir . '/search.bin'))
			{
				throw new \RuntimeException('Search is enabled, but no search index or smgen-search executable is available.');
			}

			fwrite(STDOUT, "\033[33;4mAssembling sitemap...\033[0m\n");
			$this->runGenerators($buildDir, $pageManifest, $pluginEnvironment);
			$this->runPluginHooks('finalize', $workDir, $buildDir, $pageManifest, $pluginEnvironment);

			if(file_exists($this->outputAbsolute) || is_link($this->outputAbsolute))
			{
				$backupPath = Filesystem::temporaryDirectory($outputParent, '.' . $outputName . '.smgen-backup.');
				Filesystem::removeTree($backupPath);
				if(!rename($this->outputAbsolute, $backupPath))
				{
					throw new \RuntimeException('Unable to back up current output: ' . $this->outputAbsolute);
				}
			}
			if(!rename($buildDir, $this->outputAbsolute))
			{
				if($backupPath !== null && !rename($backupPath, $this->outputAbsolute))
				{
					throw new \RuntimeException('Unable to restore the previous output after installation failed: ' . $backupPath);
				}
				throw new \RuntimeException('Unable to install staged output: ' . $this->outputAbsolute);
			}
			$outputInstalled = true;
			$buildDir = '';

			$after = $this->projectDir . '/after-smgen.sh';
			if(is_file($after))
			{
				$result = $this->processes->run(['bash', './after-smgen.sh'], $this->projectDir, $pluginEnvironment);
				if($result->exitCode !== 0)
				{
					throw new \RuntimeException('after-smgen.sh failed; restored the previous output.');
				}
			}
			$committed = true;
			if($backupPath !== null)
			{
				Filesystem::removeTree($backupPath);
				$backupPath = null;
			}
		}
		finally
		{
			if($outputInstalled && !$committed)
			{
				Filesystem::removeTree($this->outputAbsolute);
				if($backupPath !== null && (file_exists($backupPath) || is_link($backupPath)))
				{
					if(!rename($backupPath, $this->outputAbsolute))
					{
						throw new \RuntimeException('Unable to restore the previous output: ' . $backupPath);
					}
					$backupPath = null;
				}
			}
			if($buildDir !== '' && (file_exists($buildDir) || is_link($buildDir)))
			{
				Filesystem::removeTree($buildDir);
			}
			if($workDir !== '' && (file_exists($workDir) || is_link($workDir)))
			{
				Filesystem::removeTree($workDir);
			}
			if($this->generatedPagesRoot !== null && (file_exists($this->generatedPagesRoot) || is_link($this->generatedPagesRoot)))
			{
				Filesystem::removeTree($this->generatedPagesRoot);
				$this->generatedPagesRoot = null;
			}
		}
	}

	/** Acquire a nonblocking lock that prevents concurrent builds in one project. */
	private function acquireBuildLock(): mixed
	{
		$lockRoot = is_dir('/tmp') ? '/tmp' : sys_get_temp_dir();
		$path = $lockRoot . DIRECTORY_SEPARATOR . 'smgen-build-' . hash('sha256', $this->projectDir) . '.lock';
		if(is_link($path))
		{
			throw new \RuntimeException('Build lock must not be a symbolic link: ' . $path);
		}
		$lock = fopen($path, 'c');
		if($lock === false)
		{
			throw new \RuntimeException('Unable to open the build lock: ' . $path);
		}
		if(!chmod($path, 0600))
		{
			fclose($lock);
			throw new \RuntimeException('Unable to protect the build lock: ' . $path);
		}
		if(!flock($lock, LOCK_EX | LOCK_NB))
		{
			fclose($lock);
			throw new \RuntimeException('Another SMGen build is already running for ' . $this->projectDir . '.');
		}
		return $lock;
	}

	/** Return existing plugin paths that the development watcher should monitor. */
	public function pluginWatchPaths(): array
	{
		$this->resolveSourceRoots();
		$plugins = new PluginRegistry($this->projectDir, $this->pluginActivations());
		return $plugins->watchPaths();
	}

	/** Validate runtime dependencies, requested paths, and output ownership. */
	private function validateRuntime(?string $requestedPath): void
	{
		if(!extension_loaded('yaml'))
		{
			throw new \RuntimeException('The PHP YAML extension is required.');
		}
		$this->processes->requireCommand((string) $this->config->get('PANDOC'), 'pandoc');
		$this->processes->requireCommand((string) $this->config->get('PHP'), 'php');
		$this->resolveSourceRoots();
		if($requestedPath !== null && $requestedPath !== '')
		{
			$resolved = realpath(Filesystem::resolveProjectPath($this->projectDir, $requestedPath));
			if($resolved === false || ($resolved !== $this->pagesRoot && !str_starts_with($resolved, $this->pagesRoot . DIRECTORY_SEPARATOR)))
			{
				throw new \RuntimeException('Page path must stay inside ' . $this->config->get('PAGES_DIR') . ': ' . $requestedPath);
			}
		}
		$this->outputAbsolute = $this->validateOutputPath();
	}

	/** Resolve configured source roots before filesystem guards use them. */
	private function resolveSourceRoots(): void
	{
		$this->pagesRoot = Filesystem::canonicalDirectory(
			Filesystem::resolveProjectPath($this->projectDir, (string) $this->config->get('PAGES_DIR')),
			'PAGES_DIR',
		);
		$this->templateRoot = Filesystem::canonicalDirectory(
			Filesystem::resolveProjectPath($this->projectDir, (string) $this->config->get('TEMPLATE_DIR')),
			'TEMPLATE_DIR',
		);
		$staticCandidate = Filesystem::resolveProjectPath($this->projectDir, (string) $this->config->get('STATIC_DIR'));
		$this->staticRoot = is_dir($staticCandidate) ? (realpath($staticCandidate) ?: $staticCandidate) : $staticCandidate;
		$helpersCandidate = Filesystem::resolveProjectPath($this->projectDir, (string) $this->config->get('HELPERS_DIR'));
		$this->helpersRoot = is_dir($helpersCandidate) ? (realpath($helpersCandidate) ?: $helpersCandidate) : $helpersCandidate;
	}

	/** Resolve a safe output path that does not overlap project sources. */
	private function validateOutputPath(): string
	{
		$configured = (string) $this->config->get('OUTPUT_DIR');
		$candidate = Filesystem::resolveProjectPath($this->projectDir, $configured);
		if(is_link($candidate))
		{
			throw new \RuntimeException('OUTPUT_DIR must not be a symbolic link: ' . $configured);
		}
		$name = basename($candidate);
		if($name === '' || in_array($name, ['.', '..', '/'], true))
		{
			throw new \RuntimeException('Unsafe OUTPUT_DIR: ' . $configured);
		}
		$parent = dirname($candidate);
		if(!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent))
		{
			throw new \RuntimeException('Unable to create OUTPUT_DIR parent: ' . $parent);
		}
		$parent = realpath($parent);
		if($parent === false)
		{
			throw new \RuntimeException('Unable to resolve OUTPUT_DIR parent: ' . dirname($candidate));
		}
		$output = $parent . DIRECTORY_SEPARATOR . $name;
		if($output === '/' || $output === $this->projectDir)
		{
			throw new \RuntimeException('OUTPUT_DIR must not be the filesystem or project root.');
		}
		foreach([$this->pagesRoot, $this->templateRoot, $this->staticRoot, $this->helpersRoot] as $protected)
		{
			if(!file_exists($protected)) continue;
			$protected = realpath($protected);
			if($protected === false) continue;
			if($output === $protected || str_starts_with($output, $protected . DIRECTORY_SEPARATOR))
			{
				throw new \RuntimeException('OUTPUT_DIR must not overlap a source directory: ' . $protected);
			}
			if(str_starts_with($protected, $output . DIRECTORY_SEPARATOR))
			{
				throw new \RuntimeException('OUTPUT_DIR must not contain a source directory: ' . $protected);
			}
		}
		return $output;
	}

	/** Normalize the configured plugin activation value into an ordered list. */
	private function pluginActivations(): array
	{
		$value = $this->config->get('PLUGINS', []);
		if($value === null || $value === '') return [];
		if(!is_array($value)) return [(string) $value];
		return $value;
	}

	/** Compile navigation in-process unless project executable code requires isolation. */
	private function buildNavigation(string $navigationPath, array $environment): array
	{
		$coreHelper = $this->coreDir . '/helpers/navigation-data.php';
		$helper = $this->projectHelper('navigation-data.php');
		$isolated = realpath($helper) !== realpath($coreHelper)
			|| $this->plugins->hasExecutableMenuAlter($this->helpersRoot);
		if($isolated)
		{
			$result = $this->processes->mustRun(
				[(string) $this->config->get('PHP'), $helper, (string) $this->config->get('PAGES_DIR')],
				$this->projectDir,
				$environment,
				true,
			);
			$json = $result->stdout;
		}
		else
		{
			require_once($coreHelper);
			$json = json_encode(
				\compileNavigationData($this->pagesRoot, $this->plugins->manifestPaths()),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
			);
		}
		if(file_put_contents($navigationPath, $json) === false)
		{
			throw new \RuntimeException('Unable to write navigation data: ' . $navigationPath);
		}
		$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		return is_array($data) ? $data : [];
	}

	/** Assemble core, selected plugin, and project assets in precedence order. */
	private function copyStaticAssets(string $buildDir, array $selectedPlugins): void
	{
		$coreStatic = $this->coreDir . '/static';
		$coreAssets = $this->stringList($this->config->get('CORE_ASSETS'), 'CORE_ASSETS');
		if($coreAssets !== [])
		{
			foreach($coreAssets as $asset)
			{
				$relative = Filesystem::assertRelativeOutputPath($asset, 'core asset path');
				$source = Filesystem::canonicalEntryWithin($coreStatic . '/' . $relative, $coreStatic, 'Core asset');
				Filesystem::copyEntry($source, $buildDir . '/' . $relative);
			}
		}
		elseif((string) $this->config->get('INHERIT_CORE_STATIC') === '1')
		{
			Filesystem::copyTree($coreStatic, $buildDir);
		}

		foreach($selectedPlugins as $plugin)
		{
			$assets = $plugin->manifest['assets'] ?? [];
			if(!is_array($assets))
			{
				throw new \RuntimeException('Plugin assets must be a list for ' . $plugin->id . '.');
			}
			foreach($assets as $asset)
			{
				if(!is_array($asset) || !isset($asset['from'], $asset['to']) || !is_string($asset['from']) || !is_string($asset['to']))
				{
					throw new \RuntimeException('Plugin assets require string from and to fields for ' . $plugin->id . '.');
				}
				$sourceCandidate = Filesystem::resolveProjectPath($plugin->root, $asset['from']);
				$source = realpath($sourceCandidate);
				$pluginRoot = realpath($plugin->root);
				if($source === false || $pluginRoot === false || ($source !== $pluginRoot && !str_starts_with($source, $pluginRoot . DIRECTORY_SEPARATOR)))
				{
					throw new \RuntimeException('Plugin asset must stay inside the plugin root for ' . $plugin->id . ': ' . $asset['from']);
				}
				Filesystem::rejectSymlinksInTree($sourceCandidate, 'Plugin asset source for ' . $plugin->id);
				$relative = Filesystem::assertRelativeOutputPath($asset['to'], 'plugin asset output');
				$destination = Filesystem::assertNoSymlinkSegments($buildDir, $relative, 'Plugin asset output');
				if(is_dir($source)) Filesystem::copyTree($source, $destination);
				else Filesystem::copyEntry($source, $destination);
			}
		}

		Filesystem::copyTree($this->staticRoot, $buildDir);
	}

	/** Resolve explicit and automatic search settings for the current staged output. */
	private function resolveSearchSetting(string $buildDir): bool
	{
		$configured = $this->config->get('SEARCH_ENABLED');
		if(is_bool($configured)) return $configured;
		$value = strtolower((string) $configured);
		if(in_array($value, ['1', 'true', 'yes'], true)) return true;
		if(in_array($value, ['0', 'false', 'no'], true)) return false;
		if($value !== 'auto')
		{
			throw new \RuntimeException('SEARCH_ENABLED must be auto, 1, or 0.');
		}
		return $this->processes->commandExists((string) $this->config->get('SMG_SEARCH'))
			|| is_file($this->staticRoot . '/search.bin')
			|| is_file($buildDir . '/search.bin');
	}

	/** Ensure an enabled search interface has its browser reader in staged output. */
	private function prepareSearchReader(string $buildDir, bool $enabled): void
	{
		if(!$enabled) return;
		$reader = (string) $this->config->get('SEARCH_READER');
		if(preg_match('~^(?:https?:)?//~', $reader)) return;
		$relative = Filesystem::assertRelativeOutputPath($reader, 'SEARCH_READER path');
		$target = $buildDir . '/' . $relative;
		if(!is_file($target) && $relative === 'vendor/smgen-search/SearchReader.mjs')
		{
			Filesystem::copyTree($this->coreDir . '/static/vendor/smgen-search', $buildDir . '/vendor/smgen-search');
		}
		if(!is_file($target))
		{
			throw new \RuntimeException('Search reader not found in static output: ' . $reader);
		}
	}

	/** Render one source through its PHP template and a dedicated Pandoc process. */
	private function buildPage(
		PageRecord $page,
		string $buildDir,
		string $workDir,
		string $manifestPath,
		array $siteStyles,
		array $environment,
	): void {
		$target = Filesystem::assertNoSymlinkSegments($buildDir, $page->destination, 'Page output');
		if(!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target)))
		{
			throw new \RuntimeException('Unable to create page output directory: ' . dirname($target));
		}
		fwrite(STDOUT, "\033[37m  {$page->source}...\033[0m\n");
		$template = $this->resolveTemplate($page);
		$plugins = $this->plugins->pluginsForPage($page);
		$contributions = $this->pageContributions($plugins);
		$styles = array_values(array_unique(array_merge($contributions['styles'], $siteStyles)));
		$pageEnvironment = array_merge($environment, [
			'CURRENT_PAGE' => '/' . $page->destination,
			'SMGEN_PAGE_FILE' => $page->source,
			'STYLES' => implode("\n", $styles),
			'SCRIPTS' => implode("\n", array_values(array_unique(array_merge($contributions['head'], $this->stringList($this->config->get('SCRIPTS'), 'SCRIPTS'))))),
			'DEFER_SCRIPTS' => implode("\n", array_values(array_unique(array_merge($contributions['defer'], $this->stringList($this->config->get('DEFER_SCRIPTS'), 'DEFER_SCRIPTS'))))),
			'BODY_SCRIPTS' => implode("\n", array_values(array_unique(array_merge($contributions['body'], $this->stringList($this->config->get('BODY_SCRIPTS'), 'BODY_SCRIPTS'))))),
		]);

		if(str_ends_with($template, '.smgen.php'))
		{
			$templateDocument = $this->templateRenderer->render($template, $page, $pageEnvironment);
		}
		else
		{
			$args = array_merge($this->phpHelperArgs(), [$template, $page->source]);
			$templateDocument = $this->processes->mustRun($args, $this->projectDir, $pageEnvironment, true)->stdout;
		}
		$templateFile = tempnam($workDir, 'template.');
		if($templateFile === false || file_put_contents($templateFile, $templateDocument) === false)
		{
			throw new \RuntimeException('Unable to write rendered template.');
		}

		$toc = $page->frontmatter['TOC'] ?? $page->frontmatter['toc'] ?? true;
		$pandoc = [(string) $this->config->get('PANDOC'), '--data-dir=.', '-s', '-f', 'markdown', '-t', 'html'];
		$highlight = (string) $this->config->get('HIGHLIGHT_STYLE');
		if($highlight !== '') $pandoc[] = '--highlight-style=' . $highlight;
		if($toc !== false && $toc !== 'false') $pandoc[] = '--toc';
		$titlePrefix = (string) $this->config->get('TITLE_PREFIX');
		if($titlePrefix !== '') $pandoc[] = '--title-prefix=' . $titlePrefix;
		$pandoc[] = '--lua-filter=' . $this->projectHelper('domain.lua');
		foreach($contributions['filters'] as $filter)
		{
			$pandoc[] = '--lua-filter=' . $filter;
		}
		$pandoc[] = '--template=' . $templateFile;
		foreach($this->stringList($this->config->get('INLINE_STYLES'), 'INLINE_STYLES') as $inlineStyle)
		{
			$pandoc[] = '-H';
			$pandoc[] = $inlineStyle;
		}
		foreach($styles as $style)
		{
			$pandoc[] = '--css';
			$pandoc[] = $this->resolveAssetUrl($style);
		}
		$pandoc[] = '-o';
		$pandoc[] = $target;
		$pandoc[] = $page->source;
		$this->processes->mustRun($pandoc, $this->projectDir, ['BASE_URL' => (string) $this->config->get('BASE_URL')]);
		if(file_put_contents($manifestPath, $page->destination . "\t" . $page->source . "\n", FILE_APPEND) === false)
		{
			throw new \RuntimeException('Unable to append to the page manifest: ' . $manifestPath);
		}
	}

	/** Select an explicit, extension-specific, reusable, or legacy page template. */
	private function resolveTemplate(PageRecord $page): string
	{
		$selected = $page->frontmatter['template'] ?? null;
		if($selected !== null && $selected !== '')
		{
			if(!is_string($selected))
			{
				throw new \RuntimeException('Template frontmatter must be a path: ' . $page->source);
			}
			$candidate = Filesystem::resolveProjectPath($this->projectDir, $selected);
			return Filesystem::canonicalFileWithin($candidate, $this->templateRoot, 'Template');
		}
		$candidates = [
			$this->templateRoot . '/' . $page->extension . '-page.smgen.php',
			$this->templateRoot . '/' . $page->extension . '-page.php',
			$this->templateRoot . '/' . $page->extension . '-template.html',
			$this->templateRoot . '/page.smgen.php',
			$this->templateRoot . '/page.php',
		];
		foreach($candidates as $candidate)
		{
			if(is_file($candidate))
			{
				return Filesystem::canonicalFileWithin($candidate, $this->templateRoot, 'Template');
			}
		}
		throw new \RuntimeException('No page template is available for ' . $page->source . '.');
	}

	/** Merge ordered resource and filter contributions from selected plugins. */
	private function pageContributions(array $plugins): array
	{
		$result = ['styles' => [], 'head' => [], 'defer' => [], 'body' => [], 'filters' => []];
		foreach($plugins as $plugin)
		{
			$pages = $plugin->pageContribution() ?? [];
			$result['styles'] = array_merge($result['styles'], $this->stringList($pages['styles'] ?? [], 'plugin styles'));
			$scripts = $pages['scripts'] ?? [];
			if(!is_array($scripts))
			{
				throw new \RuntimeException('Plugin page scripts must be a mapping for ' . $plugin->id . '.');
			}
			foreach(['head', 'defer', 'body'] as $placement)
			{
				$result[$placement] = array_merge($result[$placement], $this->stringList($scripts[$placement] ?? [], 'plugin ' . $placement . ' scripts'));
			}
			foreach($this->stringList($pages['filters'] ?? [], 'plugin filters') as $filter)
			{
				$result['filters'][] = $this->plugins->resolvePluginPath($plugin, $filter, 'Pandoc filter');
			}
		}
		foreach($result as $name => $values)
		{
			$result[$name] = array_values(array_unique($values));
		}
		return $result;
	}

	/** Generate the sitemap, llms.txt index, and configured RSS feeds. */
	private function runGenerators(string $buildDir, string $manifestPath, array $environment): void
	{
		$baseUrl = (string) $this->config->get('BASE_URL');
		$this->runGenerator('sitemap.php', [$baseUrl, $buildDir, $manifestPath], $environment, function() use ($baseUrl, $buildDir, $manifestPath): void {
			require_once($this->coreDir . '/helpers/sitemap.php');
			$target = Filesystem::assertNoSymlinkSegments($buildDir, 'sitemap.xml', 'Sitemap output');
			if(is_link($target))
			{
				throw new \RuntimeException('sitemap.xml collides with an existing output file.');
			}
			if(file_put_contents($target, \generateSitemapDocument($baseUrl, $manifestPath)) === false)
			{
				throw new \RuntimeException('Unable to write sitemap: ' . $target);
			}
		});
		$this->runGenerator('llms.php', [$baseUrl, $buildDir, $manifestPath, (string) $this->config->get('PAGES_DIR')], $environment, function() use ($baseUrl, $buildDir, $manifestPath): void {
			require_once($this->coreDir . '/helpers/llms.php');
			\generateLlmsFile($baseUrl, $buildDir, $manifestPath, $this->pagesRoot);
		});
		$this->runGenerator('rss.php', [$baseUrl, $buildDir, $manifestPath], $environment, function() use ($baseUrl, $buildDir, $manifestPath): void {
			require_once($this->coreDir . '/helpers/rss.php');
			\rssGenerateFeeds($baseUrl, $buildDir, $manifestPath);
		});
	}

	/** Run a project helper override or its equivalent built-in callable. */
	private function runGenerator(string $name, array $args, array $environment, callable $builtIn): void
	{
		$helper = $this->projectHelper($name);
		$core = $this->coreDir . '/helpers/' . $name;
		if(realpath($helper) === realpath($core))
		{
			$builtIn();
			return;
		}
		$result = $this->processes->mustRun(array_merge($this->phpHelperArgs(), [$helper], $args), $this->projectDir, $environment, $name === 'sitemap.php');
		$target = $name === 'sitemap.php'
			? Filesystem::assertNoSymlinkSegments($args[1], 'sitemap.xml', 'Sitemap output')
			: null;
		if($target !== null && file_put_contents($target, $result->stdout) === false)
		{
			throw new \RuntimeException('Unable to write custom sitemap.');
		}
	}

	/** Execute ordered prepare or finalize hooks with a private JSON context. */
	private function runPluginHooks(string $phase, string $workDir, ?string $buildDir, ?string $manifestPath, array $environment): void
	{
		$generatedRoot = null;
		if($phase === 'prepare')
		{
			foreach($this->plugins->all() as $plugin)
			{
				if($plugin->hook('prepare') !== null)
				{
					$generatedRoot = $this->resetGeneratedPages();
					break;
				}
			}
		}
		foreach($this->plugins->all() as $plugin)
		{
			$command = $plugin->hook($phase);
			if($command === null) continue;
			foreach($command as &$part)
			{
				if(str_starts_with($part, './'))
				{
					$part = $plugin->root . DIRECTORY_SEPARATOR . substr($part, 2);
				}
			}
			unset($part);
			$contextPath = $workDir . '/plugin-' . preg_replace('/[^A-Za-z0-9._-]/', '_', $plugin->id) . '-' . $phase . '.json';
			$generatedPages = null;
			if($phase === 'prepare' && $generatedRoot !== null)
			{
				$generatedPages = Filesystem::assertNoSymlinkSegments($generatedRoot, $plugin->id, 'Generated pages path');
				if(!mkdir($generatedPages, 0755, true) && !is_dir($generatedPages))
				{
					throw new \RuntimeException('Unable to create generated pages directory: ' . $generatedPages);
				}
			}
			$context = [
				'apiVersion' => 'smgen/v1',
				'phase' => $phase,
				'plugin' => ['id' => $plugin->id, 'root' => $plugin->root, 'manifest' => $plugin->manifestPath, 'options' => $plugin->options],
				'project' => ['root' => $this->projectDir, 'environment' => (string) $this->config->get('SMGEN_ENVIRONMENT')],
				'paths' => [
					'pages' => $this->pagesRoot,
					'output' => $this->outputAbsolute,
					'stage' => $buildDir,
					'work' => $workDir,
					'pageManifest' => $manifestPath,
					'generatedPages' => $generatedPages,
				],
				'site' => ['baseUrl' => (string) $this->config->get('BASE_URL')],
			];
			if(file_put_contents($contextPath, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false)
			{
				throw new \RuntimeException('Unable to write plugin context: ' . $contextPath);
			}
			if(!chmod($contextPath, 0600))
			{
				throw new \RuntimeException('Unable to protect plugin context: ' . $contextPath);
			}
			$this->mustRun($command, array_merge($environment, ['SMGEN_PLUGIN_CONTEXT' => $contextPath, 'SMGEN_PLUGIN_PHASE' => $phase]));
		}
	}

	/** Replace the managed generated-page workspace for the current build. */
	private function resetGeneratedPages(): string
	{
		$relative = '.smgen-generated';
		$root = Filesystem::assertNoSymlinkSegments($this->pagesRoot, $relative, 'Generated pages root');
		$this->generatedPagesRoot = $root;
		$this->removeManagedGeneratedPages();
		if(!mkdir($root, 0755, true) && !is_dir($root))
		{
			throw new \RuntimeException('Unable to create generated pages root: ' . $root);
		}
		if(file_put_contents($root . '/.smgen-managed', "smgen/v1\n") === false)
		{
			throw new \RuntimeException('Unable to mark generated pages as managed: ' . $root);
		}
		return $root;
	}

	/** Remove a generated-page workspace only when its ownership marker is valid. */
	private function removeManagedGeneratedPages(): void
	{
		$root = Filesystem::assertNoSymlinkSegments($this->pagesRoot, '.smgen-generated', 'Generated pages root');
		if(!file_exists($root) && !is_link($root)) return;
		$marker = $root . '/.smgen-managed';
		if(!is_dir($root) || !is_file($marker) || trim((string) file_get_contents($marker)) !== 'smgen/v1')
		{
			throw new \RuntimeException('Refusing to replace an unmanaged generated pages directory: ' . $root);
		}
		Filesystem::removeTree($root);
	}

	/** Select a project helper override when present, otherwise use the core helper. */
	private function projectHelper(string $name): string
	{
		$candidate = $this->helpersRoot . DIRECTORY_SEPARATOR . $name;
		return is_file($candidate) ? $candidate : $this->coreDir . '/helpers/' . $name;
	}

	/** Build the PHP argument vector used for isolated legacy helpers and templates. */
	private function phpHelperArgs(): array
	{
		return [
			(string) $this->config->get('PHP'),
			'-d', 'display_errors=stderr',
			'-d', 'auto_prepend_file=' . $this->coreDir . '/helpers/bootstrap.php',
			'-d', 'include_path=' . $this->coreDir . '/helpers',
		];
	}

	/** Run a project-root command and require a successful exit status. */
	private function mustRun(array $command, array $environment): ProcessResult
	{
		return $this->processes->mustRun($command, $this->projectDir, $environment);
	}

	/** Normalize a scalar or list setting into nonempty strings. */
	private function stringList(mixed $value, string $context): array
	{
		if($value === null || $value === '') return [];
		if(!is_array($value)) $value = [(string) $value];
		$result = [];
		foreach($value as $entry)
		{
			if(!is_string($entry) && !is_int($entry) && !is_float($entry))
			{
				throw new \RuntimeException($context . ' must contain scalar values.');
			}
			$entry = (string) $entry;
			if($entry !== '') $result[] = $entry;
		}
		return $result;
	}

	/** Join a local resource path to BASE_URL while preserving external URLs. */
	private function resolveAssetUrl(string $path): string
	{
		if(preg_match('/^(?:[a-z]+:)?\/\//i', $path) || preg_match('/^(?:data|mailto|tel):/i', $path))
		{
			return $path;
		}
		$base = rtrim((string) $this->config->get('BASE_URL'), '/');
		$path = ltrim($path, '/');
		return $base !== '' ? $base . '/' . $path : '/' . $path;
	}

	/** Install resolved values for in-process helpers and reusable templates. */
	private function installEnvironment(array $environment): void
	{
		foreach($environment as $name => $value)
		{
			putenv($name . '=' . $value);
			$_ENV[$name] = $value;
		}
	}
}
