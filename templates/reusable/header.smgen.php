<?php

use Smgen\Template\PageContext;

/** Render the reusable default site header for one page. */
return static function(PageContext $page): void {
	?>
<section class = "heading">
	<div class = "page-rule">
		<header>
			<div class = "row wide">
				<nav>
					<a class = "logo" href = "<?=$page->escape($page->assetUrl('/'))?>">
						<?php if($page->staticAssetExists('/logo.svg')): ?>
						<img src="<?=$page->escape($page->assetUrl('/logo.svg'))?>" class="logo-image" alt="" width="216" height="210">
						<?php endif; ?>
						<span class = "col">
							<span class = "logo-text">
								<?=$page->escape($page->config('PRODUCT_NAME') ?: 'Product Name')?>
							</span>
							<?php if($page->config('TAGLINE')): ?>
							<span class = "tagline-text"><?=$page->escape($page->config('TAGLINE'))?></span>
							<?php endif; ?>
						</span>
					</a>
				</nav>
				<div class = "header-fill">
					<?php if((string) $page->config('SEARCH_ENABLED') === '1'): ?>
					<div class = "search-wrapper">
						<input id="search-query" type="search" placeholder="search" aria-label="Search" aria-controls="search-results" autocomplete="off" data-search-index="<?=$page->escape($page->assetUrl('/search.bin'))?>" data-search-reader="<?=$page->escape($page->assetUrl((string) $page->config('SEARCH_READER')))?>" data-search-results="#search-results" />
						<ul class="search-menu" id="search-results" aria-live="polite"></ul>
					</div>
					<?php endif; ?>
					<div class = "links"><?php if($page->menuRegionExists('header')) echo $page->renderMenuRegion('header'); ?></div>
				</div>
			</div>
		</header>
	</div>
</section>
	<?php
};
