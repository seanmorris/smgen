<?php

use Smgen\Template\PageContext;

/** Render the reusable default page shell around Pandoc placeholders. */
return static function(PageContext $page): void {
	$frontmatter = $page->frontmatter();
	$header = $frontmatter['header'] ?? $page->config('HEADER', 'templates/header.smgen.php');
	$footer = $frontmatter['footer'] ?? $page->config('FOOTER', 'templates/footer.smgen.php');
	$leftBarShow = $frontmatter['leftBarShow'] ?? true;
	?>
<!DOCTYPE HTML>
<html lang="en" class="<?=$page->escape($page->config('DEFAULT_THEME') ?: 'theme-default')?>">
<head>
	$if(noprefix)$
	<title>$if(pagetitle)$${pagetitle}$else$${title}$endif$</title>
	$else$
	$if(title-prefix)$
	<title>$if(title-prefix)$${title-prefix} | $endif$$if(pagetitle)$${pagetitle}$else$${title}$endif$</title>
	$else$
	<title>$if(pagetitle)$${pagetitle}$else$${title}$endif$</title>
	$endif$
	$endif$
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="smgen-base-url" content="<?=$page->escape($page->config('BASE_URL'))?>" />
$for(author)$
	<meta name="author" content="$author.name$" />
$endfor$
$if(date-meta)$
	<meta name="dcterms.date" content="$date-meta$" />
$endif$
$if(keywords)$
	<meta name="keywords" content="$for(keywords)$$keywords$$sep$, $endfor$" />
$endif$
$if(description-meta)$
	<meta name="description" content="$description-meta$" />
$endif$
$if(canonical)$
	<link rel="canonical" href="$canonical$" />
$endif$
	<meta name="title" content="$if(pagetitle)$${pagetitle}$else$${title}$endif$">
<?=$page->renderJsonLd()?>
	<link rel="icon" type="image/png" href="<?=$page->escape($page->assetUrl('/icon-16.png'))?>">
	<link rel="sitemap" href="<?=$page->escape($page->assetUrl('/sitemap.xml'))?>" />
<?php if($page->llmsEnabled()): ?>
	<link rel="describedby" type="text/markdown" href="<?=$page->escape($page->assetUrl('/llms.txt'))?>" />
<?php endif; ?>
<?php foreach($page->rssFeeds() as $rssFeed): ?>
	<link rel="alternate" type="application/rss+xml" title="<?=$page->escape($rssFeed['title'])?>" href="<?=$page->escape($page->assetUrl($rssFeed['path']))?>" />
<?php endforeach; ?>
<?php if($page->staticAssetExists('/logo.svg')): ?>
	<link rel="preload" href="<?=$page->escape($page->assetUrl('/logo.svg'))?>" as="image" type="image/svg">
<?php endif; ?>
	<script>(function(d){try{var v=sessionStorage.getItem('current-theme-variant');if(!v)v=matchMedia('(prefers-color-scheme: light)').matches?'light':'dark';d.classList.add(v)}catch(e){d.classList.add('dark')}})(document.documentElement)</script>
	<style>
		$styles.html()$
	</style>
$for(css)$
	<link rel="stylesheet" href="${css}" />
$endfor$
	<style>
$for(header-includes)$
	$header-includes$
$endfor$
	</style>
$if(math)$
	$math$
$endif$
<?php foreach($page->config('SCRIPTS', []) ?: [] as $javascript): ?>
	<script src="<?=$page->escape($page->assetUrl((string) $javascript))?>"></script>
<?php endforeach; ?>
<?php foreach($page->config('DEFER_SCRIPTS', []) ?: [] as $javascript): ?>
	<script defer src="<?=$page->escape($page->assetUrl((string) $javascript))?>"></script>
<?php endforeach; ?>
<?php foreach($page->config('INLINE_SCRIPTS', []) ?: [] as $javascriptFile): ?>
	<script><?=file_get_contents((string) $javascriptFile)?></script>
<?php endforeach; ?>
</head>
<body>
	<?=$page->renderFragment((string) $header)?>
	<section class = "below-fold">
		<div class = "page-rule row">
			<?php if($leftBarShow): ?>
				<nav class = "main"><?=$page->renderNavBar()?></nav>
			<?php endif; ?>
			<div class = "page-content">
				<article $if(itemtype)$ itemscope itemtype = "https://${itemtype}" $endif$>
				$for(microdata/pairs)$
				<meta itemprop = "${microdata.key}" content = "${microdata.value}" />
				$endfor$
				$body$
				</article>
				$if(toc)$
				<nav class = "table-of-contents">
					<span class = "wide-only">on this page:</span>
					${toc}
					<span class = "wide-only"><a href = "#">top</a></span>
				</nav>
				$endif$
			</div>
		</div>
	</section>
	<?=$page->renderFragment((string) $footer)?>
<?php foreach($page->config('BODY_SCRIPTS', []) ?: [] as $javascript): ?>
	<script src="<?=$page->escape($page->assetUrl((string) $javascript))?>"></script>
<?php endforeach; ?>
<?php foreach($page->config('INLINE_BODY_SCRIPTS', []) ?: [] as $javascriptFile): ?>
	<script><?=file_get_contents((string) $javascriptFile)?></script>
<?php endforeach; ?>
</body>
</html>
	<?php
};
