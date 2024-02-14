<?php
/**
 * @file   web.php
 * @brief  web route definitions
 * @author Tomáš Vojík <xvojik00@stud.fit.vutbr.cz>, <vojik@wboy.cz>
 */

use App\Controllers\GamesList;
use App\Controllers\Gate\Gate;
use App\Controllers\Lang;
use App\Controllers\NewGame;
use App\Controllers\Players;
use App\Controllers\Results;
use App\Controllers\Settings\Settings;
use App\Core\App;
use App\Services\FeatureConfig;
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

$settings = Route::group('settings')
                 ->get('', [Settings::class, 'show'])->name('settings')
                 ->post('', [Settings::class, 'saveGeneral'])
                 ->get('gate', [Settings::class, 'gate'])->name('settings-gate')
                 ->post('gate', [Settings::class, 'saveGate'])
                 ->get('vests', [Settings::class, 'vests'])->name('settings-vests')
                 ->post('vests', [Settings::class, 'saveVests'])
                 ->get('print', [Settings::class, 'print'])->name('settings-print')
                 ->post('print', [Settings::class, 'savePrint']);

if ($featureConfig->isFeatureEnabled('groups')) {
	$settings->get('groups', [Settings::class, 'group'])->name('settings-groups');
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
	->get('/find', [Players::class, 'find'])
	->get('/find/{code}', [Players::class, 'getPlayer'])
	->get('/sync/{code}', [Players::class, 'syncPlayer'])
	->group('/public')
	->get('/find', [Players::class, 'findPublic']);