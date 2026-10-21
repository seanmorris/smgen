<footer class="footer">
	<div class = "page-rule row">
		<div class = "col">
			<span>&copy; <?=date('Y');?> <?=escapeHtml(getConf('ORGANIZATION') ?: 'Organization Name');?></span>
			<a href="<?=escapeHtml(resolveAssetUrl('/sitemap.xml'))?>" target="_blank" rel="noopener noreferrer">
				<img src="<?=escapeHtml(resolveAssetUrl('/sitemap-badge.png'))?>" width="80" height="15" alt="XML sitemap">
			</a>
		</div>
	</div>
</footer>
