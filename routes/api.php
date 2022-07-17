<?php

use App\Controllers\Api\Debug;
use App\Controllers\Api\GameHelpers;
use App\Controllers\Api\Games;
use App\Controllers\Api\Logs;
use App\Controllers\Api\Mount;
use App\Controllers\Api\Results;
use App\Controllers\Api\Updater;
use App\Core\Routing\Route;

Route::post('/api/results/import', [Results::class, 'import']);
Route::post('/api/results/import/{game}', [Results::class, 'importGame']);
Route::get('/api/results/last', [Results::class, 'getLastGameFile']);
Route::get('/api/results/download', [Results::class, 'downloadLastGameFiles']);

Route::post('/api/mount', [Mount::class, 'mount']);

Route::post('/api/update', [Updater::class, 'update']);

Route::post('/api/git/pull', [Updater::class, 'pull']);
Route::post('/api/git/fetch', [Updater::class, 'fetch']);
Route::post('/api/git/status', [Updater::class, 'status']);

Route::post('/api/build', [Updater::class, 'build']);
Route::post('/api/install', [Updater::class, 'install']);

Route::get('/api/logs', [Logs::class, 'show']);
Route::get('/api/logs/download', [Logs::class, 'download']);

Route::get('/api/debug/pwd', [Debug::class, 'pwd']);
Route::get('/api/debug/whoami', [Debug::class, 'whoami']);
Route::post('/api/debug/enable', [Debug::class, 'enable']);
Route::post('/api/debug/disable', [Debug::class, 'disable']);
Route::update('/api/debug/incrementCache', [Debug::class, 'incrementCache']);
Route::get('/api/debug/glob', [Debug::class, 'glob']);

Route::get('/api/game/loaded', [GameHelpers::class, 'getLoadedGameInfo']);
Route::get('/api/game/gate', [GameHelpers::class, 'getGateGameInfo']);

Route::get('api/games', [Games::class, 'listGames']);
Route::post('api/games/sync', [Games::class, 'syncGames']);
Route::post('api/games/sync/{limit}', [Games::class, 'syncGames']);
Route::get('api/games/{code}', [Games::class, 'getGame']);