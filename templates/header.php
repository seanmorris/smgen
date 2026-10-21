<section class = "heading">
	<div class = "page-rule">
		<header>
			<div class = "row wide">
				<nav>
					<a class = "logo" href = "<?=escapeHtml(resolveAssetUrl('/'))?>">
						<?php if(staticAssetExists('/logo.svg')): ?>
						<img src="<?=escapeHtml(resolveAssetUrl('/logo.svg'))?>" class="logo-image" alt="" width="216" height="210">
						<?php endif; ?>
						<span class = "col">
							<span class = "logo-text">
								<?=escapeHtml(getConf('PRODUCT_NAME') ?: 'Product Name');?>
							</span>
							<?php if(getConf('TAGLINE')): ?>
							<span class = "tagline-text"><?=escapeHtml(getConf('TAGLINE') ?: 'Tagline');?></span>
							<?php endif; ?>
						</span>
					</a>
				</nav>
				<div class = "header-fill">
					<?php if((string) getConf('SEARCH_ENABLED') === '1'): ?>
					<div class = "search-wrapper">
						<input id="search-query" type="search" placeholder="search" aria-label="Search" aria-controls="search-results" autocomplete="off" data-search-index="<?=escapeHtml(resolveAssetUrl('/search.bin'))?>" data-search-reader="<?=escapeHtml(resolveAssetUrl(getConf('SEARCH_READER')))?>" data-search-results="#search-results" />
						<ul class="search-menu" id="search-results" aria-live="polite"></ul>
					</div>
					<?php endif; ?>
					<div class = "links"><?php if(menuRegionExists('header')) renderMenuRegion('header'); ?></div>
				</div>
			</div>
		</header>
	</div>
	<?php if($heroHtml??false): ?>
	<div class = "page-rule">
		<?=$heroHtml;?>
	</div>
	<?php endif; ?>
</section>
