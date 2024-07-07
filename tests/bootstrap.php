<?php

define('ROOT', dirname(__DIR__) . '/');
const INDEX = true;

if (!file_exists(ROOT . 'private/config.ini')) {
    copy(ROOT . 'tests/private/config.ini', ROOT . 'private/config.ini');
}

require_once ROOT . 'include/load.php';
