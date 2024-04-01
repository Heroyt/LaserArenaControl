<?php
/**
 * @file   web.php
 * @brief  web route definitions
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

use App\Controllers\GamesList;
use App\Controllers\Gate\GateController;
use App\Controllers\Lang;
use App\Controllers\NewGame;
use App\Controllers\Players;
use App\Controllers\Results;
use App\Controllers\Settings\Gate;
use App\Controllers\Settings\Settings;
use App\Core\App;
use App\Services\FeatureConfig;
use Lsr\Core\Routing\Route;

$featureConfig = App::getServiceByType(FeatureConfig::class);

Route::get('/lang/{lang}', [Lang::class, 'setLang']);

Route::get('/', [NewGame::class, 'show'])->name('dashboard');

Route::group('/results')
	->get('/', [Results::class, 'show'])
	->name('results')
	->get('/{code}', [Results::class, 'show'])
	->name('results-game')
	->get('/{code}/print', [Results::class, 'printGame'])
	->name('print')
	->get('/{code}/print/{lang}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}/{style}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}/{style}/{template}', [Results::class, 'printGame'])
	->get('/{code}/print/{lang}/{copies}/{style}/{template}/{type}', [Results::class, 'printGame']);

Route::group('/list')
	->get('/', [GamesList::class, 'show'])
	->name('games-list')
	->get('/{game}', [GamesList::class, 'game']);

$settings = Route::group('settings')
	->get('', [Settings::class, 'show'])
	->name('settings')
                 ->post('', [Settings::class, 'saveGeneral'])
	->get('gate', [Gate::class, 'gate'])
	->name('settings-gate')
	->get('gate/settings/{screen}', [Gate::class, 'screenSettings'])
	->post('gate', [Gate::class, 'saveGate'])
	->get('vests', [Settings::class, 'vests'])
	->name('settings-vests')
                 ->post('vests', [Settings::class, 'saveVests'])
	->get('print', [Settings::class, 'print'])
	->name('settings-print')
                 ->post('print', [Settings::class, 'savePrint']);

if ($featureConfig->isFeatureEnabled('groups')) {
	$settings->get('groups', [Settings::class, 'group'])->name('settings-groups');
}

Route::group('/gate')
	->get('/', [GateController::class, 'show'])
	->name('gate')
	->get('/{gate}', [GateController::class, 'show'])
	->name('gate-slug')
	->post('/event', [GateController::class, 'setEvent'])
	->post('/set', [GateController::class, 'setGateGame'])      // Error
	->post('/loaded', [GateController::class, 'setGateLoaded']) // Error
	->post('/idle', [GateController::class, 'setGateIdle']) // Error
	->post('/set/{system}', [GateController::class, 'setGateGame'])
	->post('/loaded/{system}', [GateController::class, 'setGateLoaded'])
	->post('/idle/{system}', [GateController::class, 'setGateIdle']);

Route::group('/players')
	->get('/find', [Players::class, 'find'])
	->get('/find/{code}', [Players::class, 'getPlayer'])
	->get('/sync/{code}', [Players::class, 'syncPlayer'])
	->group('/public')
	->get('/find', [Players::class, 'findPublic']);