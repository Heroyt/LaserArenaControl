<?php
/**
 * @file  preload.php
 * @brief This files specifies all other PHP files which should be preloaded in OPCache.
 */

const ROOT = __DIR__.'/';

opcache_compile_file(ROOT.'index.php');

foreach (glob(ROOT.'include*.php') as $file) {
	opcache_compile_file($file);
}
foreach (glob(ROOT.'src/Core/*.php') as $file) {
	opcache_compile_file($file);
}
foreach (glob(ROOT.'src/Controllers/*.php') as $file) {
	opcache_compile_file($file);
}
foreach (glob(ROOT.'src/Controllers/*/*.php') as $file) {
	opcache_compile_file($file);
}

$Directory = new RecursiveDirectoryIterator(ROOT.'vendor/lsr/');
$Iterator = new RecursiveIteratorIterator($Directory);
$Regex = new RegexIterator($Iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);

foreach ($Regex as [$file]) {
	opcache_compile_file($file);
}