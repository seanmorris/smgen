<?php

use Smgen\Template\PageContext;

/** Render the reusable default site footer for one page. */
return static function(PageContext $page): void {
	?>
<footer class="footer">
	<div class = "page-rule row">
		<?php if($page->menuRegionExists('footer')): ?><nav class="footer-menus"><?=$page->renderMenuRegion('footer', ['showLabel' => true])?></nav><?php endif; ?>
		<div class = "col">
			<span>&copy; <?=date('Y')?> <?=$page->escape($page->config('ORGANIZATION') ?: 'Organization Name')?></span>
			<a href="<?=$page->escape($page->assetUrl('/sitemap.xml'))?>" target="_blank" rel="noopener noreferrer">
				<img src="<?=$page->escape($page->assetUrl('/sitemap-badge.png'))?>" width="80" height="15" alt="XML sitemap">
			</a>
		</div>
	</div>
</footer>
	<?php
};
