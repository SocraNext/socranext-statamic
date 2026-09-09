<?php

use Illuminate\Support\Facades\Route;
use SocraNext\Statamic\Http\Controllers\ControlPanelController;

Route::prefix('socranext')->name('socranext.')->middleware('can:configure socranext')->group(function () {
    Route::get('/', [ControlPanelController::class, 'index'])->name('index');
    Route::post('/connect', [ControlPanelController::class, 'connect'])->name('connect');
    Route::post('/disconnect', [ControlPanelController::class, 'disconnect'])->name('disconnect');
    Route::post('/readiness', [ControlPanelController::class, 'readiness'])->name('readiness');
});
