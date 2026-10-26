<?php

require_once(dirname(__DIR__) . '/src/autoload.php');

/** Convert a generated output path into its root-relative public route. */
function smgenPublicOutputPath($outputPath)
{
	return \Smgen\PublicUrl::fromOutputPath((string) $outputPath);
}
