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

/**
 * Normalise one breadcrumb entry from frontmatter or a navigation entry.
 *
 * @param mixed $entry Candidate with a title and an optional path or href.
 * @param string $context Description used in error messages.
 * @return array{title: string, href: ?string} Normalised crumb.
 */
function navigationTrailEntry($entry, $context)
{
	if(!is_array($entry) || !isset($entry['title']) || !is_scalar($entry['title']) || (string) $entry['title'] === '')
	{
		throw new \RuntimeException('Each ' . $context . ' entry needs a title.');
	}
	$target = $entry['path'] ?? $entry['href'] ?? null;
	if($target !== null && (!is_scalar($target) || (string) $target === ''))
	{
		throw new \RuntimeException('A ' . $context . ' path must be a non-empty string: ' . $entry['title']);
	}
	return ['title' => (string) $entry['title'], 'href' => $target === null ? null : resolveAssetUrl((string) $target)];
}

/**
 * Find the chain of navigation entries leading to the current page inside one tree.
 *
 * @param list<array<string, mixed>> $entries Compiled menu entries.
 * @param string $currentPage Public path of the page being rendered.
 * @return list<array<string, mixed>>|null Ancestors followed by the page entry, or null when absent.
 */
function navigationFindTrail($entries, $currentPage)
{
	foreach($entries as $entry)
	{
		if(navigationEntryHref($entry) === $currentPage)
		{
			return [$entry];
		}
		$chain = navigationFindTrail($entry['children'] ?? [], $currentPage);
		if($chain !== null)
		{
			array_unshift($chain, $entry);
			return $chain;
		}
	}
	return null;
}

/**
 * Build the breadcrumb trail for the current page.
 *
 * A `breadcrumbs` frontmatter list names the page's ancestors directly, each with a
 * `title` and an optional root-relative `path`. Otherwise the trail follows the
 * first menu that contains the page: every enclosing group becomes a crumb, linked
 * to the group's directory index page when one exists, and the page closes the
 * trail. Options: `frontmatter` (the page's parsed frontmatter; the template global
 * is used when omitted), `menu` (one menu name), `region` (menus of one region;
 * default `sidebar`, falling back to every menu), and `home` (a title that prepends
 * a link to the site root).
 *
 * @param array<string, mixed> $options Trail options.
 * @return list<array{title: string, href: ?string, current: bool}> Crumbs, oldest first; empty when the page is in no menu.
 */
function navigationTrail($options = [])
{
	$frontmatter = $options['frontmatter'] ?? ($GLOBALS['frontmatter'] ?? []);
	$frontmatter = is_array($frontmatter) ? $frontmatter : [];
	$currentPage = (string) (getenv('CURRENT_PAGE') ?: '');
	$trail = [];

	if(array_key_exists('breadcrumbs', $frontmatter))
	{
		$declared = $frontmatter['breadcrumbs'];
		if($declared === false || $declared === null)
		{
			return [];
		}
		if(!is_array($declared) || ($declared !== [] && array_keys($declared) !== range(0, count($declared) - 1)))
		{
			throw new \RuntimeException('breadcrumbs frontmatter must be a list of entries with a title and an optional path.');
		}
		foreach($declared as $entry)
		{
			$trail[] = navigationTrailEntry($entry, 'breadcrumbs') + ['current' => false];
		}
		$title = $frontmatter['pagetitle'] ?? $frontmatter['title'] ?? null;
		$trail[] = ['title' => is_scalar($title) ? (string) $title : $currentPage, 'href' => $currentPage !== '' ? resolveAssetUrl($currentPage) : null, 'current' => true];
	}
	else
	{
		$data = loadNavigationData();
		if(isset($options['menu']))
		{
			$menuNames = [(string) $options['menu']];
		}
		else
		{
			$region = (string) ($options['region'] ?? 'sidebar');
			$menuNames = $data['regions'][$region] ?? array_keys($data['menus'] ?? []);
		}
		$chain = null;
		foreach($menuNames as $menuName)
		{
			if(!isset($data['menus'][$menuName]))
			{
				throw new \RuntimeException('Unknown menu: ' . $menuName);
			}
			$chain = navigationFindTrail($data['menus'][$menuName]['items'] ?? [], $currentPage);
			if($chain !== null)
			{
				break;
			}
		}
		if($chain === null)
		{
			return [];
		}
		$page = array_pop($chain);
		foreach($chain as $entry)
		{
			$href = $entry['index'] ?? navigationEntryHref($entry);
			if($href === $currentPage)
			{
				continue;
			}
			$trail[] = ['title' => (string) ($entry['title'] ?? ''), 'href' => $href === null ? null : resolveAssetUrl($href), 'current' => false];
		}
		$trail[] = ['title' => (string) ($page['title'] ?? ''), 'href' => resolveAssetUrl($currentPage), 'current' => true];
	}

	if(isset($options['home']) && (string) $options['home'] !== '' && ($trail[0]['href'] ?? null) !== resolveAssetUrl('/'))
	{
		array_unshift($trail, ['title' => (string) $options['home'], 'href' => resolveAssetUrl('/'), 'current' => false]);
	}
	return $trail;
}

/**
 * Render the breadcrumb trail as a nav element with an ordered list.
 *
 * Nothing renders for a page with no ancestors. Accepts the navigationTrail() options.
 *
 * @param array<string, mixed> $options Trail options.
 */
function renderBreadcrumbs($options = [])
{
	$trail = navigationTrail($options);
	if(count($trail) < 2)
	{
		return;
	}
	?><nav class="breadcrumbs" aria-label="Breadcrumb"><ol><?php
	foreach($trail as $crumb)
	{
		if($crumb['current'])
		{
			?><li aria-current="page"><?=escapeHtml($crumb['title']);?></li><?php
		}
		elseif($crumb['href'] !== null)
		{
			?><li><a href="<?=escapeHtml($crumb['href']);?>"><?=escapeHtml($crumb['title']);?></a></li><?php
		}
		else
		{
			?><li><span><?=escapeHtml($crumb['title']);?></span></li><?php
		}
	}
	?></ol></nav><?php
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
