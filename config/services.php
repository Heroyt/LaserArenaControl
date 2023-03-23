<?php
/**
 * @file  config/services.php
 * @brief List of all DI container definition files
 */

$services = [
	ROOT.'vendor/lsr/routing/services.neon',
	ROOT.'vendor/lsr/logging/services.neon',
	ROOT.'vendor/lsr/core/services.neon',
];
$services[] = PRODUCTION ? ROOT.'config/services.neon' : ROOT.'config/servicesDebug.neon';
return $services;