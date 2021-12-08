<?php

use App\Controllers\Cli\Results;
use App\Core\Routing\CliRoute;

if (PHP_SAPI === 'cli') {

	CliRoute::cli('results/load', [Results::class, 'import']);

}