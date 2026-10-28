<?php

function navigationEntryHref($entry)
{
	return $entry['path'] ?? $entry['href'] ?? null;
}

function navigationLinkClasses($entry, $active)
{
	$classes = [];
	$variant = $entry['variant'] ?? 'default';
	if($variant !== 'default')
	{
		$classes[] = 'menu-link--' . $variant;
	}
	if($active)
	{
		$classes[] = 'active-link';
	}
	return $classes ? ' class="' . escapeHtml(implode(' ', $classes)) . '"' : '';
}

/**
 * Report whether a navigation entry or one of its descendants targets the current page.
 *
 * @param array<string, mixed> $entry Compiled navigation entry.
 */
function navigationContainsCurrent($entry)
{
	$href = navigationEntryHref($entry);
	$contains = $href !== null && getenv('CURRENT_PAGE') === $href;
	foreach($entry['children'] ?? [] as $child)
	{
		if($contains)
		{
			break;
		}
		$contains = navigationContainsCurrent($child);
	}
	return $contains;
}

function renderNavigationLink($entry)
{
	$href = navigationEntryHref($entry);
	if($href === null)
	{
		echo escapeHtml($entry['title'] ?? '');
		return;
	}
	$active = getenv('CURRENT_PAGE') === $href;
	$icon = $entry['icon'] ?? null;
	$iconOnly = ($entry['variant'] ?? 'default') === 'icon-only';
	?><a href="<?=escapeHtml(resolveAssetUrl($href));?>"<?=navigationLinkClasses($entry, $active);?><?=$active ? ' aria-current="page"' : '';?><?=$iconOnly ? ' aria-label="' . escapeHtml($entry['title'] ?? '') . '"' : '';?>><?php
	if($icon): ?><span class="menu-link-icon menu-link-icon--<?=escapeHtml($icon);?>" aria-hidden="true"></span><?php endif;
	?><span<?=$iconOnly ? ' class="visually-hidden"' : '';?>><?=escapeHtml($entry['title'] ?? '');?></span><?php
	if(isset($entry['description']) && $entry['description'] !== ''): ?><small class="menu-link-description"><?=escapeHtml($entry['description']);?></small><?php endif;
	?></a><?php
}

function renderNavigationEntries($entries, $idPath = '')
{
	?><ul><?php
	foreach($entries as $index => $entry)
	{
		$idSubPath = $idPath . ($idPath === '' ? $index : '-' . $index);
		$isGroup = in_array(($entry['type'] ?? ''), ['directory', 'group'], true);
		if($isGroup)
		{
			$containsCurrent = navigationContainsCurrent($entry);
			$expanded = $containsCurrent || ($entry['expanded'] ?? $entry['open'] ?? true);
			?><details <?=$expanded ? 'open' : '';?> data-id-path="<?=escapeHtml($entry['id'] ?? $idSubPath);?>"<?=$containsCurrent ? ' data-contains-current="true"' : '';?>>
				<summary><?php renderNavigationLink($entry); ?></summary>
				<?php renderNavigationEntries($entry['children'] ?? [], $idSubPath); ?>
			</details><?php
			continue;
		}
		?><li>
			<?php renderNavigationLink($entry); ?>
			<?php if(!empty($entry['children'])) renderFlatNavigationEntries($entry['children']); ?>
		</li><?php
	}
	?></ul><?php
}

function renderFlatNavigationEntries($entries)
{
	?><ul><?php foreach($entries as $entry): ?>
		<li class="menu-item menu-item--<?=escapeHtml($entry['type'] ?? 'link');?>"><?php renderNavigationLink($entry); ?><?php
			if(!empty($entry['children'])) renderFlatNavigationEntries($entry['children']);
		?></li>
	<?php endforeach; ?></ul><?php
}

function loadNavigationData()
{
	static $data;
	if($data !== null)
	{
		return $data;
	}

	$dataFile = getenv('SMGEN_NAV_DATA');
	if(!$dataFile || !is_file($dataFile))
	{
		throw new \RuntimeException('Navigation data is unavailable.');
	}
	$data = json_decode(file_get_contents($dataFile), true, 512, JSON_THROW_ON_ERROR);

	// Keep project-generated data from older SMGen versions usable.
	if($data === [] || array_keys($data) === range(0, count($data) - 1))
	{
		$data = ['tree' => $data];
	}
	if(!isset($data['menus']))
	{
		$data['menus'] = [
			'sidebar' => [
				'name' => 'sidebar',
				'label' => 'Navigation',
				'renderer' => 'tree',
				'showLabel' => false,
				'items' => $data['tree'] ?? [],
			],
		];
		$data['regions']['sidebar'] = ['sidebar'];
	}
	return $data;
}

/**
 * Load and validate the provider for one plugin-defined menu renderer.
 *
 * @param string $rendererName Renderer token stored in compiled navigation data.
 * @return array{compile: callable, assets: callable, render: callable} Renderer callbacks.
 */
function navigationRendererProvider($rendererName)
{
	static $providers = [];
	if(isset($providers[$rendererName]))
	{
		return $providers[$rendererName];
	}
	$data = loadNavigationData();
	$file = $data['renderers'][$rendererName]['file'] ?? null;
	if(!is_string($file) || !is_file($file))
	{
		throw new \RuntimeException('Menu renderer ' . $rendererName . ' is not available to this template.');
	}
	$provider = require($file);
	if(!is_array($provider))
	{
		throw new \RuntimeException('Menu renderer file must return an array of callables: ' . $file);
	}
	foreach(['compile', 'assets', 'render'] as $callable)
	{
		if(!isset($provider[$callable]) || !is_callable($provider[$callable]))
		{
			throw new \RuntimeException('Menu renderer file must provide a callable ' . $callable . ': ' . $file);
		}
	}
	$providers[$rendererName] = $provider;
	return $provider;
}

function renderMenu($menuName, $options = [])
{
	$data = loadNavigationData();
	if(!isset($data['menus'][$menuName]))
	{
		throw new \RuntimeException('Unknown menu: ' . $menuName);
	}
	$menu = $data['menus'][$menuName];
	$renderer = $menu['renderer'] ?? 'flat';
	$idPath = (string) ($options['idPath'] ?? $menuName);
	if(!in_array($renderer, ['flat', 'tree'], true))
	{
		$provider = navigationRendererProvider($renderer);
		$provider['render']($menu, $idPath, ['currentPage' => getenv('CURRENT_PAGE') ?: '', 'options' => $options]);
		return;
	}

	$showLabel = array_key_exists('showLabel', $options) ? (bool) $options['showLabel'] : (bool) ($menu['showLabel'] ?? false);
	?><div class="menu menu--<?=escapeHtml($renderer);?> menu--<?=escapeHtml($menuName);?>" data-menu="<?=escapeHtml($menuName);?>">
		<?php if($showLabel): ?><h2 class="menu-title"><?=escapeHtml($menu['label'] ?? $menuName);?></h2><?php endif; ?>
		<?php if($renderer === 'tree'): ?>
			<?php renderNavigationEntries($menu['items'] ?? [], $idPath); ?>
		<?php elseif($renderer === 'flat'): ?>
			<?php renderFlatNavigationEntries($menu['items'] ?? []); ?>
		<?php else: ?>
			<?php throw new \RuntimeException('Unknown menu renderer: ' . $renderer); ?>
		<?php endif; ?>
	</div><?php
}

function menuRegionExists($regionName)
{
	$data = loadNavigationData();
	return array_key_exists($regionName, $data['regions'] ?? []);
}

function renderMenuRegion($regionName, $options = [])
{
	$data = loadNavigationData();
	if(!array_key_exists($regionName, $data['regions'] ?? []))
	{
		throw new \RuntimeException('Unknown menu region: ' . $regionName);
	}
	foreach($data['regions'][$regionName] as $menuName)
	{
		renderMenu($menuName, $options);
	}
}

if(!function_exists('renderNavBar'))
{
	function renderNavBar($path = null, $rootPath = null, $idPath = '')
	{
		$data = loadNavigationData();
		foreach($data['regions']['sidebar'] ?? [] as $menuName)
		{
			renderMenu($menuName, ['idPath' => $idPath]);
		}
	}
}
