<?php

use App\Controllers\Api\Mount;
use App\Controllers\Api\Results;
use App\Core\Routing\Route;

Route::post('/api/results/import', [Results::class, 'import']);
Route::post('/api/mount', [Mount::class, 'mount']);