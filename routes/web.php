<?php
/**
 * @file   web.php
 * @brief  web route definitions
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

use App\Controllers\GamesList;
use App\Controllers\Gate;
use App\Controllers\Lang;
use App\Controllers\NewGame;
use App\Controllers\PlayersController;
use App\Controllers\Results;
use App\Controllers\Settings;
use App\Controllers\TournamentController;
use App\Controllers\TournamentResults;
use App\Services\FeatureConfig;
use Lsr\Core\App;
use Lsr\Core\Routing\Route;

$featureConfig = App::getServiceByType(FeatureConfig::class);

Route::get('/lang/{lang}', [Lang::class, 'setLang']);

Route::get('/', [NewGame::class, 'show'])->name('dashboard');

Route::group('/results')
	->get('/', [Results::class, 'show'])->name('results')
	->get('/{code}', [Results::class, 'show'])->name('results-game')
	->get('/{code}/print', [Results::class, 'printGame'])->name('print')
	->get('/{code}/print/{lang}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}/{style}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}/{style}/{template}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}/{style}/{template}/{type}', [Results::class, 'printGame']);

Route::group('/list')
	->get('/', [GamesList::class, 'show'])->name('games-list')
	->get('/{game}', [GamesList::class, 'game']);

$settings = Route::group('/settings')
	->get('/', [Settings::class, 'show'])->name('settings')
	->post('/', [Settings::class, 'saveGeneral'])
	->get('/gate', [Settings::class, 'gate'])->name('settings-gate')
	->post('/gate', [Settings::class, 'saveGate'])
	->get('/vests', [Settings::class, 'vests'])->name('settings-vests')
	->post('/vests', [Settings::class, 'saveVests'])
	->get('/print', [Settings::class, 'print'])->name('settings-print')
	->post('/print', [Settings::class, 'savePrint']);

if ($featureConfig->isFeatureEnabled('groups')) {
	$settings
		->get('/groups', [Settings::class, 'group'])->name('settings-groups');
}

if ($featureConfig->isFeatureEnabled('tables')) {
	$settings
		->get('/tables', [Settings::class, 'tables'])->name('settings-tables')
		->post('/tables', [Settings::class, 'saveTables'])
		->post('/tables/new', [Settings::class, 'addTable'])
		->post('/tables/{id}/delete', [Settings::class, 'deleteTable'])
		->delete('/tables/{id}', [Settings::class, 'deleteTable']);
}

if ($featureConfig->isFeatureEnabled('tournaments')) {
	Route::group('/tournament')
		->get('/', [TournamentController::class, 'index'])->name('tournaments')
		->get('/sync', [TournamentController::class, 'sync'])
		->get('/{id}', [TournamentController::class, 'show'])
		->get('/{id}/rozlos', [TournamentController::class, 'rozlos'])->name('tournament-rozlos')
		->get('/{id}/gate', [TournamentResults::class, 'gate'])->name('tournament-gate')
		->post('/{id}/rozlos', [TournamentController::class, 'rozlosProcess'])
		->get('/{id}/rozlos/clear', [TournamentController::class, 'rozlosClear'])
		->get('/{id}/play', [TournamentController::class, 'play'])->name('tournament-play')
		->get('/{id}/play/list', [TournamentController::class, 'playList'])->name('tournament-play-list')
		->post('/{id}/progress', [TournamentController::class, 'progress'])
		->get('/{id}/play/{gameId}', [TournamentController::class, 'play'])->name('tournament-play-game')
		->get('/{id}/play/{gameId}/results', [TournamentController::class, 'playResults'])
		->post('/{id}/play/{gameId}', [TournamentController::class, 'playProcess'])
		->post('/{id}/play/{gameId}/bonus', [TournamentController::class, 'updateBonusScore'])
		->post('/{id}/play/{gameId}/reset', [TournamentController::class, 'resetGame']);
}

Route::group('/gate')
	->get('/', [Gate::class, 'show'])->name('gate')
	->post('/set', [Gate::class, 'setGateGame'])      // Error
	->post('/loaded', [Gate::class, 'setGateLoaded']) // Error
	->post('/idle', [Gate::class, 'setGateIdle']) // Error
	->post('/set/{system}', [Gate::class, 'setGateGame'])
	->post('/loaded/{system}', [Gate::class, 'setGateLoaded'])
	->post('/idle/{system}', [Gate::class, 'setGateIdle']);

Route::group('/players')
	->get('/find', [PlayersController::class, 'find'])
	->get('/find/{code}', [PlayersController::class, 'getPlayer'])
	->get('/sync/{code}', [PlayersController::class, 'syncPlayer'])
	->group('/public')
	->get('/find', [PlayersController::class, 'findPublic']);
