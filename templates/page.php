<?php

function resolveTemplateInclude($path)
{
	$templateRoot = realpath(getConf('TEMPLATE_DIR') ?: 'templates');
	$resolvedPath = realpath($path);

	if(!$templateRoot || !$resolvedPath)
	{
		throw new \RuntimeException('Template include path does not exist: ' . $path);
	}

	if($resolvedPath !== $templateRoot && strpos($resolvedPath, $templateRoot . DIRECTORY_SEPARATOR) !== 0)
	{
		throw new \RuntimeException('Template include path must stay inside TEMPLATE_DIR: ' . $path);
	}

	return $resolvedPath;
}

$headerTemplate = resolveTemplateInclude($frontmatter['header'] ?? getConf('HEADER') ?? 'templates/header.php');
$footerTemplate = resolveTemplateInclude($frontmatter['footer'] ?? getConf('FOOTER') ?? 'templates/footer.php');

$leftBarLink = $frontmatter['leftBarLink'] ?? TRUE;
$leftBarShow = $frontmatter['leftBarShow'] ?? TRUE;

?><!DOCTYPE HTML>
<html lang = "en" class = "<?=getConf('DEFAULT_THEME')??'theme-default';?>">
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
	<meta name="viewport" content="width=800, user-scalable=yes" />
	<meta name="smgen-base-url" content="<?=getConf('BASE_URL');?>" />
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
	<meta name="generated-at" content="<?=date('Y-m-d_h:i:s')?>">
	<link rel="icon" type="image/png" href="<?=getConf('BASE_URL');?>/icon-16.png">
	<link rel="sitemap" href="<?=getConf('BASE_URL');?>/sitemap.xml" />
<?php if(file_exists('static/logo.svg')): ?>
	<link rel="preload" href="<?=getConf('BASE_URL');?>/logo.svg" as="image" type="image/svg">
<?php endif; ?>
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
<?php if(getConf('SCRIPTS')) foreach(getConf('SCRIPTS') as $javascript):?>
	<script src = "<?=$javascript;?>"></script>
<?php endforeach; ?>
<?php if(getConf('INLINE_SCRIPTS')) foreach(getConf('INLINE_SCRIPTS') as $javascriptFile):?>
	<script><?=file_get_contents($javascriptFile);?></script>
<?php endforeach; ?>
</head>
<body>
	<?php include $headerTemplate; ?>
	<section class = "below-fold">
		<div class = "page-rule row">
			<?php if($leftBarShow ?? true): ?>
				<nav class = "main"><?php renderNavBar(); ?></nav>
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
	<?php include $footerTemplate; ?>
<?php if(getConf('BODY_SCRIPTS')) foreach(getConf('BODY_SCRIPTS') as $javascript):?>
	<script src = "<?=$javascript;?>"></script>
<?php endforeach; ?>
<?php if(getConf('INLINE_BODY_SCRIPTS')) foreach(getConf('INLINE_BODY_SCRIPTS') as $javascriptFile):?>
	<script><?=file_get_contents($javascriptFile);?></script>
<?php endforeach; ?>
	</body>
</html>
