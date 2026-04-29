<?php

//@TODO: Consider finishing this or removing it.

include(__DIR__ . '../helpers/getConf.php');

function initCommand(){}

function buildCommand()
{
	$dir = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator(getConf('PAGES_DIR'))
	);

	$includePath = get_include_path();

	foreach($dir as $file)
	{
		$name = $file->getFilename();
		if($file->isDir() || $name === '.fm.yaml' || strpos($name, '.') === -1) continue;

		$pathname = $file->getPathname();
		$ext = $file->getExtension();
		$base = substr($name, 0, -1 + -strlen($ext));

		$handle = fopen($pathname, 'r');
		$firstLine = fgets($handle, 4);
		$hasFrontmatter = $firstLine === '---';

		$frontmatter = [];

		if($hasFrontmatter)
		{
			$frontmatter = yaml_parse_file($pathname, 0);
		}

		$template = $frontmatter['template'] ?? null;

		if(!$template)
		{
			$templateDir = getConf('TEMPLATE_DIR');
			$pageTemplate = $templateDir . '/page.php';
			$extTemplate = $templateDir . '/' . $ext . '-page';

			if(file_exists($extTemplate . '.php'))
			{
				$template = $extTemplate . '.php';
			}
			else if(file_exists($extTemplate . '.html'))
			{
				$template = $extTemplate . '.html';
			}
			else
			{
				$template = $pageTemplate;
			}
		}

		$tmpName = tempnam('/tmp', 'smgen_template_');

		(function() use($pathname, $template, $frontmatter, $includePath, $tmpName) {
			set_include_path($includePath . ':./helpers');
			ob_start();
			include $template;
			$rendered = ob_get_contents();
			ob_end_clean();
			file_put_contents($tmpName . '.html', $rendered);
		})();

		set_include_path($includePath);

		$pandoc = 'pandoc --data-dir=. -s -f markdown -t html';

		$highlightStyle = getConf('HIGHLIGHT_STYLE');
		$tocFlag = $frontmatter['TOC'] ?? TRUE;
		$titlePrefix = getConf('TITLE_PREFIX');

		if($highlightStyle)
		{
			$pandoc .= ' --highlight-style ' .  escapeshellarg($highlightStyle);
		}

		if($tocFlag)
		{
			$pandoc .= ' --toc';
		}

		if($titlePrefix)
		{
			$pandoc .= ' ' . escapeshellarg('--title-prefix=' . $titlePrefix);
		}

		$title = $frontmatter['title'] ?? ucwords(trim(str_replace('-', ' ', basename($base))));

		$pandoc .= ' --metadata ' . escapeshellarg('title=' . $title);

		$pandoc .= ' ' . escapeshellarg('--lua-filter=' . getcwd() . '/helpers/domain.lua');
		$pandoc .= ' ' . escapeshellarg('--template=' . $tmpName . '.html');

		foreach(getConf('INLINE_STYLES') as $stylesheet)
		{
			$pandoc .= ' ' . escapeshellarg('-H=' . $stylesheet);
		}

		foreach(getConf('STYLES') as $stylesheet)
		{
			$pandoc .= ' ' . escapeshellarg('--css=' . $stylesheet);
		}

		$dest = substr(dirname($pathname), strlen(getConf('PAGES_DIR'))) . '/' . $base . '.html';

		$pandoc .= ' -o' .  escapeshellarg(getConf('OUTPUT_DIR') . $dest);
		$pandoc .= ' ' . escapeshellarg($pathname);

		putEnv('BASE_URL=' . getConf('BASE_URL'));
		putEnv('CURRENT_PAGE=' . $dest);
		echo 'Building ' . $dest . "\n";
		system($pandoc);
		unlink($tmpName);
		unlink($tmpName . '.html');
	}
}

function watchCommand(){}
function serveCommand(){}
function proofreadCommand(){}
function createRandomPageCommand(){}
function helpCommand(){}

buildCommand();
