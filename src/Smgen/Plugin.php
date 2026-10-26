<?php

namespace Smgen;

/**
 * Represents one validated plugin activation and its project-specific options.
 */
final class Plugin
{
	/** Store the resolved manifest and activation overrides for one plugin. */
	public function __construct(
		public readonly string $id,
		public readonly string $manifestPath,
		public readonly string $root,
		public readonly array $manifest,
		public readonly array $options,
		public readonly ?array $pageOverride,
		public readonly array $activationWatch,
		public readonly bool $legacy,
	) {
	}

	/** Return declarative page contributions, including assets-only global selection. */
	public function pageContribution(): ?array
	{
		$value = $this->manifest['pages'] ?? null;
		if(is_array($value)) return $value;
		return isset($this->manifest['assets']) ? [] : null;
	}

	/** Return the selectors that decide which pages receive this plugin. */
	public function selectors(): array
	{
		$pages = $this->pageOverride ?? $this->pageContribution();
		if($pages === null)
		{
			return [];
		}
		$selectorKeys = ['include', 'exclude', 'where'];
		if($this->pageOverride !== null && array_intersect(array_keys($pages), $selectorKeys) !== [])
		{
			$selectors = [$pages];
		}
		else
		{
			$selectors = $pages['selectors'] ?? [['include' => ['**/*']]];
		}
		if(!is_array($selectors))
		{
			throw new \RuntimeException('Plugin page selectors must be a list for ' . $this->id . '.');
		}
		if(array_keys($selectors) !== range(0, count($selectors) - 1))
		{
			$selectors = [$selectors];
		}
		return $selectors;
	}

	/** Normalize one lifecycle hook into an argument vector for direct execution. */
	public function hook(string $phase): ?array
	{
		$hook = $this->manifest['hooks'][$phase] ?? null;
		if($hook === null)
		{
			return null;
		}
		if(is_array($hook) && isset($hook['run']))
		{
			$hook = $hook['run'];
		}
		if(!is_array($hook) || $hook === [] || array_keys($hook) !== range(0, count($hook) - 1))
		{
			throw new \RuntimeException('Plugin ' . $this->id . ' hook ' . $phase . ' must be a nonempty run list.');
		}
		foreach($hook as $part)
		{
			if(!is_string($part) && !is_int($part) && !is_float($part))
			{
				throw new \RuntimeException('Plugin ' . $this->id . ' hook arguments must be scalar values.');
			}
		}
		return array_map('strval', $hook);
	}

}
