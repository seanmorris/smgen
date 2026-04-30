<footer class="footer">
	<div class = "page-rule row">
		<div class = "col">
			<span>&copy; <?=date('Y');?> <?=getConf('ORGANIZATION') ?: 'Organization Name';?></span>
			<a href = "<?=resolveAssetUrl('/sitemap.xml')?>" target = "_blank">
				<img src = "<?=resolveAssetUrl('/sitemap-badge.png')?>" alt = "sitemap" width = "80" height = "15" alt = "xml sitemap badge">
			</a>
		</div>
	</div>
</footer>
