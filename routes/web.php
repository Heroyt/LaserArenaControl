<?php

use App\Controllers\Dashboard;
use App\Controllers\GamesList;
use App\Controllers\Results;
use App\Controllers\Settings;
use App\Core\Routing\Route;

Route::get('/', [Dashboard::class, 'show'])->name('dashboard');
Route::get('/results', [Results::class, 'show'])->name('results');
Route::get('/results/{code}', [Results::class, 'show']);
Route::get('/results/{code}/print', [Results::class, 'printGame'])->name('print');
Route::get('/results/{code}/print/{lang}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}/{style}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}/{style}/{template}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}/{style}/{template}/{type}', [Results::class, 'printGame']);

Route::get('/list', [GamesList::class, 'show'])->name('games-list');
Route::get('/list/{game}', [GamesList::class, 'game']);

Route::get('/settings', [Settings::class, 'show'])->name('settings');
Route::get('/settings/modes', [Settings::class, 'modes'])->name('settings-modes');
Route::get('/settings/print', [Settings::class, 'print'])->name('settings-print');
Route::post('/settings/print', [Settings::class, 'savePrint']);
