<?php

use App\Core\Routing\Route;
use App\Pages\Dashboard;
use App\Pages\GamesList;
use App\Pages\Results;

Route::get('/', [Dashboard::class, 'show'])->name('dashboard');
Route::get('/results', [Results::class, 'show'])->name('results');
Route::get('/results/{code}/print', [Results::class, 'printGame'])->name('print');
Route::get('/results/{code}/print/{lang}', [Results::class, 'printGame']);
Route::get('/results/{code}/print/{lang}/{copies}', [Results::class, 'printGame']);

Route::get('/list', [GamesList::class, 'show'])->name('games-list');
Route::get('/list/{game}', [GamesList::class, 'game']);
