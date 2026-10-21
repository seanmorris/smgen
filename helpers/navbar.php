<?php

function renderNavigationEntries($entries, $idPath = '')
{
	?><ul><?php
	foreach($entries as $index => $entry)
	{
		$idSubPath = $idPath . ($idPath === '' ? $index : '-' . $index);
		if(($entry['type'] ?? '') === 'directory')
		{
			?><details <?=($entry['open'] ?? true) ? 'open' : '';?> data-id-path="<?=escapeHtml($idSubPath);?>">
				<summary><?=escapeHtml($entry['title'] ?? '');?></summary>
				<?php renderNavigationEntries($entry['children'] ?? [], $idSubPath); ?>
			</details><?php
			continue;
		}

		$linkPath = $entry['path'] ?? '/';
		?><li>
			<a href="<?=escapeHtml(resolveAssetUrl($linkPath));?>" <?=getenv('CURRENT_PAGE') === $linkPath ? 'class="active-link" aria-current="page"' : '';?>>
				<?=escapeHtml($entry['title'] ?? '');?>
			</a>
		</li><?php
	}
	?></ul><?php
}

function renderNavBar($path = null, $rootPath = null, $idPath = '')
{
	static $entries;
	if($entries === null)
	{
		$dataFile = getenv('SMGEN_NAV_DATA');
		if(!$dataFile || !is_file($dataFile))
		{
			throw new \RuntimeException('Navigation data is unavailable.');
		}
		$entries = json_decode(file_get_contents($dataFile), true, 512, JSON_THROW_ON_ERROR);
	}

	renderNavigationEntries($entries, $idPath);
}
