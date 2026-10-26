<?php

/** Load SMGen classes from the package-local PSR-4-style source tree. */
spl_autoload_register(static function(string $class): void {
	$prefix = 'Smgen\\';
	if(strncmp($class, $prefix, strlen($prefix)) !== 0)
	{
		return;
	}

	$relative = substr($class, strlen($prefix));
	$path = __DIR__ . '/Smgen/' . str_replace('\\', '/', $relative) . '.php';
	if(is_file($path))
	{
		require_once($path);
	}
});
