<section class = "heading">
	<div class = "page-rule">
		<header>
			<div class = "row wide">
				<nav>
					<a class = "logo" href = "<?=resolveAssetUrl('/')?>">
						<?php if(file_exists('static/logo.svg')): ?>
						<img src = "<?=resolveAssetUrl('/logo.svg')?>" class = "logo-image">
						<?php endif; ?>
						<span class = "col">
							<span class = "logo-text">
								<?=getConf('PRODUCT_NAME') ?: 'Product Name';?>
							</span>
							<?php if(getConf('TAGLINE')): ?>
							<span class = "tagline-text"><?=getConf('TAGLINE') ?: 'Tagline';?></span>
							<?php endif; ?>
						</span>
					</a>
				</nav>
				<div class = "header-fill">
					<div class = "search-wrapper">
						<input id = "search-query" placeholder = "search" data-search-index = "<?=resolveAssetUrl('/search.bin')?>" data-search-results = "#search-results" />
						<ul class="search-menu" id = "search-results"></ul>
					</div>
					<div class = "links">
						<a class = "github-icon" title = "github" href = "https://github.com/seanmorris/smgen"></a>
						<a data-toggle-theme-variant></a>
					</div>
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
