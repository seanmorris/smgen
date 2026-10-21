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
			$expanded = $entry['expanded'] ?? $entry['open'] ?? true;
			?><details <?=$expanded ? 'open' : '';?> data-id-path="<?=escapeHtml($entry['id'] ?? $idSubPath);?>">
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

function archiveMonths($archive)
{
	$months = [];
	foreach($archive['years'] ?? [] as $year)
	{
		foreach($year['months'] ?? [] as $month)
		{
			$months[] = $month;
		}
	}
	return $months;
}

function archiveMonthContainsPath($month, $path)
{
	foreach($month['pages'] ?? [] as $page)
	{
		if(($page['path'] ?? '') === $path)
		{
			return true;
		}
	}
	return false;
}

function archiveYearContainsPath($year, $path)
{
	foreach($year['months'] ?? [] as $month)
	{
		if(archiveMonthContainsPath($month, $path))
		{
			return true;
		}
	}
	return false;
}

function renderArchiveCalendar($months, $currentPath)
{
	if(!$months)
	{
		return;
	}

	$activeIndex = 0;
	foreach($months as $index => $month)
	{
		if(archiveMonthContainsPath($month, $currentPath))
		{
			$activeIndex = $index;
			break;
		}
	}

	$month = $months[$activeIndex];
	$monthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $month['month'] . '-01');
	$daysInMonth = (int) $monthDate->format('t');
	$startOffset = (int) $monthDate->format('w');
	$weeks = (int) ceil(($startOffset + $daysInMonth) / 7);
	$pagesByDay = [];
	foreach($month['pages'] ?? [] as $page)
	{
		$day = (int) substr($page['date'], 8, 2);
		$pagesByDay[$day][] = $page;
	}

	$older = $months[$activeIndex + 1] ?? null;
	$newer = $months[$activeIndex - 1] ?? null;
	$weekdays = [
		['Sunday', 'Su'],
		['Monday', 'Mo'],
		['Tuesday', 'Tu'],
		['Wednesday', 'We'],
		['Thursday', 'Th'],
		['Friday', 'Fr'],
		['Saturday', 'Sa'],
	];

	?><div class="archive-calendar">
		<div class="archive-calendar-heading">
			<?php if($older): ?>
			<a href="<?=escapeHtml(resolveAssetUrl($older['pages'][0]['path']));?>" aria-label="Previous month: <?=escapeHtml($older['name'] . ' ' . substr($older['month'], 0, 4));?>" rel="prev">&#8249;</a>
			<?php else: ?>
			<span aria-hidden="true"></span>
			<?php endif; ?>
			<strong><?=escapeHtml($month['name'] . ' ' . substr($month['month'], 0, 4));?></strong>
			<?php if($newer): ?>
			<a href="<?=escapeHtml(resolveAssetUrl($newer['pages'][0]['path']));?>" aria-label="Next month: <?=escapeHtml($newer['name'] . ' ' . substr($newer['month'], 0, 4));?>" rel="next">&#8250;</a>
			<?php else: ?>
			<span aria-hidden="true"></span>
			<?php endif; ?>
		</div>
		<table>
			<caption class="visually-hidden"><?=escapeHtml($month['name'] . ' ' . substr($month['month'], 0, 4));?> publication calendar</caption>
			<thead><tr><?php foreach($weekdays as [$name, $short]): ?><th scope="col"><abbr title="<?=escapeHtml($name);?>"><?=escapeHtml($short);?></abbr></th><?php endforeach; ?></tr></thead>
			<tbody><?php
			for($week = 0; $week < $weeks; $week++)
			{
				?><tr><?php
				for($weekday = 0; $weekday < 7; $weekday++)
				{
					$day = ($week * 7) + $weekday - $startOffset + 1;
					if($day < 1 || $day > $daysInMonth)
					{
						?><td aria-hidden="true"></td><?php
						continue;
					}

					$dayPages = $pagesByDay[$day] ?? [];
					if(!$dayPages)
					{
						?><td><span><?=escapeHtml($day);?></span></td><?php
						continue;
					}

					$count = count($dayPages);
					$label = $monthDate->setDate((int) $monthDate->format('Y'), (int) $monthDate->format('m'), $day)->format('F j, Y');
					$label .= ': ' . $count . ($count === 1 ? ' post' : ' posts');
					$isCurrent = false;
					foreach($dayPages as $page)
					{
						$isCurrent = $isCurrent || ($page['path'] ?? '') === $currentPath;
					}
					?><td class="has-posts"><a href="<?=escapeHtml(resolveAssetUrl($dayPages[0]['path']));?>" aria-label="<?=escapeHtml($label);?>" <?=$isCurrent ? 'aria-current="date"' : '';?>><?=escapeHtml($day);?><?php if($count > 1): ?><span class="archive-day-count" aria-hidden="true"><?=escapeHtml($count);?></span><?php endif; ?></a></td><?php
				}
				?></tr><?php
			}
			?></tbody>
		</table>
	</div><?php
}

function renderArchiveNavigation($archive, $idPath = '', $title = null)
{
	$years = $archive['years'] ?? [];
	if(!$years)
	{
		return;
	}

	$currentPath = getenv('CURRENT_PAGE') ?: '';
	$hasCurrentPage = false;
	foreach($years as $year)
	{
		$hasCurrentPage = $hasCurrentPage || archiveYearContainsPath($year, $currentPath);
	}
	$prefix = $idPath === '' ? 'archive' : $idPath . '-archive';
	$title = $title ?? getConf('ARCHIVE_TITLE') ?: 'Archive';

	?><section class="navigation-widget archive-widget" aria-label="<?=escapeHtml($title);?>">
		<h2 class="navigation-widget-title"><?=escapeHtml($title);?></h2>
		<?php renderArchiveCalendar(archiveMonths($archive), $currentPath); ?>
		<ul class="archive-timeline"><?php foreach($years as $yearIndex => $year):
			$yearCurrent = archiveYearContainsPath($year, $currentPath);
			$yearOpen = $yearCurrent || (!$hasCurrentPage && $yearIndex === 0);
			$yearId = $prefix . '-' . $year['year'];
			?><li><details <?=$yearOpen ? 'open' : '';?> data-id-path="<?=escapeHtml($yearId);?>">
				<summary><span><?=escapeHtml($year['year']);?></span> <span class="archive-count">(<?=escapeHtml($year['count']);?>)</span></summary>
				<ul><?php foreach($year['months'] ?? [] as $monthIndex => $month):
					$monthCurrent = archiveMonthContainsPath($month, $currentPath);
					$monthOpen = $monthCurrent || (!$hasCurrentPage && $yearIndex === 0 && $monthIndex === 0);
					$monthId = $yearId . '-' . substr($month['month'], 5, 2);
					?><li><details <?=$monthOpen ? 'open' : '';?> data-id-path="<?=escapeHtml($monthId);?>">
						<summary><span><?=escapeHtml($month['name']);?></span> <span class="archive-count">(<?=escapeHtml($month['count']);?>)</span></summary>
						<ul><?php foreach($month['pages'] ?? [] as $page): ?>
							<li><a href="<?=escapeHtml(resolveAssetUrl($page['path']));?>" <?=($page['path'] ?? '') === $currentPath ? 'class="active-link" aria-current="page"' : '';?>><time datetime="<?=escapeHtml($page['date']);?>"><?=escapeHtml((int) substr($page['date'], 8, 2));?></time><span><?=escapeHtml($page['title']);?></span></a></li>
						<?php endforeach; ?></ul>
					</details></li>
				<?php endforeach; ?></ul>
			</details></li>
		<?php endforeach; ?></ul>
	</section><?php
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
		$data = ['tree' => $data, 'archive' => ['years' => []]];
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
			'archive' => [
				'name' => 'archive',
				'label' => getConf('ARCHIVE_TITLE') ?: 'Archive',
				'renderer' => 'timeline',
				'showLabel' => true,
				'timeline' => $data['archive'] ?? ['years' => []],
			],
		];
		$data['regions']['sidebar'] = [];
		foreach(getConf('NAVIGATION_WIDGETS') ?: ['tree'] as $widget)
		{
			$data['regions']['sidebar'][] = $widget === 'tree' ? 'sidebar' : $widget;
		}
	}
	return $data;
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
	if($renderer === 'timeline')
	{
		renderArchiveNavigation($menu['timeline'] ?? ['years' => []], $idPath, $menu['label'] ?? $menuName);
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
