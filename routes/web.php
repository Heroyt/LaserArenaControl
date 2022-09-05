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
use App\Controllers\Results;
use App\Controllers\Settings;
use Lsr\Core\Routing\Route;

Route::get('/lang/{lang}', [Lang::class, 'setLang']);

Route::get('/', [NewGame::class, 'show'])->name('dashboard');
Route::get('/results', [Results::class, 'show'])->name('results');
Route::get('/results/{code}', [Results::class, 'show'])->name('results-game');
Route::get('/results/{code}/print', [Results::class, 'printGame'])->name('print');
Route::get('/results/{code}/print/{lang}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}/{style}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}/{style}/{template}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}/{style}/{template}/{type}', [Results::class, 'printGame']);

Route::get('/list', [GamesList::class, 'show'])->name('games-list');
Route::get('/list/{game}', [GamesList::class, 'game']);

Route::get('/settings', [Settings::class, 'show'])->name('settings');
Route::post('/settings', [Settings::class, 'saveGeneral']);
Route::get('/settings/gate', [Settings::class, 'gate'])->name('settings-gate');
Route::post('/settings/gate', [Settings::class, 'saveGate']);
Route::get('/settings/modes', [Settings::class, 'modes'])->name('settings-modes');
Route::get('/settings/vests', [Settings::class, 'vests'])->name('settings-vests');
Route::post('/settings/vests', [Settings::class, 'saveVests']);
Route::get('/settings/print', [Settings::class, 'print'])->name('settings-print');
Route::post('/settings/print', [Settings::class, 'savePrint']);

Route::get('/gate', [Gate::class, 'show'])->name('gate');
Route::post('/gate/set', [Gate::class, 'setGateGame']);      // Error
Route::post('/gate/loaded', [Gate::class, 'setGateLoaded']); // Error
Route::post('/gate/set/{system}', [Gate::class, 'setGateGame']);
Route::post('/gate/loaded/{system}', [Gate::class, 'setGateLoaded']);
