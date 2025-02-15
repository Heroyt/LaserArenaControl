<?php

/**
 * @file  config/services.php
 * @brief List of all DI container definition files
 */

$services = [
    ROOT . 'vendor/lsr/routing/services.neon',
    ROOT . 'vendor/lsr/logging/services.neon',
    ROOT.'vendor/lsr/serializer/services.neon',
    ROOT . 'vendor/lsr/core/services.neon',
    ROOT . 'config/constants.php',
];
$services[] = PRODUCTION ? ROOT . 'config/services.neon' : ROOT . 'config/servicesDebug.neon';

$modules = glob(ROOT . 'modules/*/config/services.neon');

if (file_exists(ROOT.'private/config.neon')) {
    $services[] = ROOT.'private/config.neon';
}

return $modules === false ? $services : array_merge($services, $modules);
