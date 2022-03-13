<?php

use App\Controllers\Api\Logs;
use App\Controllers\Api\Mount;
use App\Controllers\Api\Results;
use App\Controllers\Api\Updater;
use App\Core\Routing\Route;

Route::post('/api/results/import', [Results::class, 'import']);
Route::post('/api/mount', [Mount::class, 'mount']);
Route::post('/api/pull', [Updater::class, 'pull']);
Route::post('/api/build', [Updater::class, 'build']);
Route::get('/api/logs', [Logs::class, 'show']);
Route::get('/api/logs/download', [Logs::class, 'download']);