<?php

namespace Smgen\Template;

use Smgen\Config;
use Smgen\PageRecord;

/**
 * Loads reusable template providers once and invokes them with per-page context.
 */
final class Renderer
{
	private array $providers = [];
	private array $stack = [];

	/** Load shared template helpers for an in-process rendering session. */
	public function __construct(
		private readonly Config $config,
		private readonly string $templateRoot,
	) {
		$core = dirname(__DIR__, 3);
		require_once($core . '/helpers/bootstrap.php');
	}

	/** Render one page while temporarily exposing its compatibility environment. */
	public function render(string $providerPath, PageRecord $page, array $pageEnvironment): string
	{
		$context = new PageContext(
			$this->config,
			$page,
			$this->templateRoot,
			fn(string $path, PageContext $fragmentContext): string => $this->invoke($path, $fragmentContext),
			$pageEnvironment,
		);
		$environment = array_merge($pageEnvironment, [
			'CURRENT_PAGE' => $page->publicPath(),
			'SMGEN_PAGE_FILE' => $page->source,
		]);
		$previous = [];
		foreach($environment as $name => $value)
		{
			$old = getenv($name);
			$previous[$name] = $old === false ? null : $old;
			putenv($name . '=' . $value);
			$_ENV[$name] = $value;
		}
		try
		{
			return $this->invoke($providerPath, $context);
		}
		finally
		{
			foreach($previous as $name => $value)
			{
				if($value === null)
				{
					putenv($name);
					unset($_ENV[$name]);
				}
				else
				{
					putenv($name . '=' . $value);
					$_ENV[$name] = $value;
				}
			}
		}
	}

	/** Invoke a cached provider and enforce its output and recursion contract. */
	private function invoke(string $path, PageContext $context): string
	{
		if(in_array($path, $this->stack, true))
		{
			throw new \RuntimeException('Recursive reusable template fragment: ' . $path);
		}
		$provider = $this->providers[$path] ??= $this->loadProvider($path);
		$this->stack[] = $path;
		ob_start();
		try
		{
			$result = $provider($context);
			$output = (string) ob_get_clean();
		}
		catch(\Throwable $exception)
		{
			ob_end_clean();
			array_pop($this->stack);
			throw $exception;
		}
		array_pop($this->stack);
		if($result !== null && !is_string($result))
		{
			throw new \RuntimeException('Reusable template must return a string or null: ' . $path);
		}
		if(is_string($result) && $output !== '')
		{
			throw new \RuntimeException('Reusable template must either emit output or return a string, not both: ' . $path);
		}
		return is_string($result) ? $result : $output;
	}

	/** Load one provider and require it to return a callable without emitting output. */
	private function loadProvider(string $path): callable
	{
		ob_start();
		try
		{
			$provider = require($path);
			$output = (string) ob_get_clean();
		}
		catch(\Throwable $exception)
		{
			ob_end_clean();
			throw $exception;
		}
		if($output !== '')
		{
			throw new \RuntimeException('Reusable template provider emitted output while loading: ' . $path);
		}
		if(!is_callable($provider))
		{
			throw new \RuntimeException('Reusable template provider must return a callable: ' . $path);
		}
		return $provider;
	}
}
