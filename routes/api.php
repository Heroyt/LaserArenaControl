<?php
/**
 * @file  api.php
 * @brief API route definitions
 */

use App\Controllers\Api\Debug;
use App\Controllers\Api\GameHelpers;
use App\Controllers\Api\Games;
use App\Controllers\Api\Logs;
use App\Controllers\Api\Mount;
use App\Controllers\Api\Results;
use App\Controllers\Api\Updater;
use App\Controllers\Api\Tournaments;
use Lsr\Core\Routing\Route;

Route::group('/api')
	->post('/mount', [Mount::class, 'mount'])
	->post('/update', [Updater::class, 'update'])
	->post('/build', [Updater::class, 'build'])
	->post('/install', [Updater::class, 'install'])
	->group('results')
	->post('/import', [Results::class, 'import'])
	->post('/import/{game}', [Results::class, 'importGame'])
	->get('/last', [Results::class, 'getLastGameFile'])
	->get('/download', [Results::class, 'downloadLastGameFiles'])
	->endGroup()
	->group('/git')
	->post('/pull', [Updater::class, 'pull'])
	->post('/fetch', [Updater::class, 'fetch'])
	->post('/status', [Updater::class, 'status'])
	->endGroup()
	->group('/logs')
	->get('/', [Logs::class, 'show'])
	->get('/download', [Logs::class, 'download'])
	->endGroup()
	->group('/debug')
	->get('/pwd', [Debug::class, 'pwd'])
	->get('/whoami', [Debug::class, 'whoami'])
	->post('/enable', [Debug::class, 'enable'])
	->post('/disable', [Debug::class, 'disable'])
	->update('/incrementCache', [Debug::class, 'incrementCache'])
	->put('/incrementCache', [Debug::class, 'incrementCache'])
	->get('/glob', [Debug::class, 'glob'])
	->endGroup()
	->group('/game')
	->get('/loaded', [GameHelpers::class, 'getLoadedGameInfo'])
	->get('/gate', [GameHelpers::class, 'getGateGameInfo'])
	->post('/{code}/recalcSkill', [GameHelpers::class, 'recalcSkill'])
	->post('/{code}/recalcScores', [GameHelpers::class, 'recalcScores'])
	->post('/{code}/changeMode', [GameHelpers::class, 'changeGameMode'])
	->endGroup()
	->group('/games')
	->get('/', [Games::class, 'listGames'])
	->post('/sync', [Games::class, 'syncGames'])
	->post('/sync/{limit}', [Games::class, 'syncGames'])
	->get('/{code}', [Games::class, 'getGame'])
	->endGroup()
	->group('/tournament')
	->get('/', [Tournaments::class, 'getAll'])
	->get('/{id}', [Tournaments::class, 'get'])
	->post('/sync', [Tournaments::class, 'sync'])
	->endGroup();