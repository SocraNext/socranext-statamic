<?php

use Illuminate\Support\Facades\Route;
use SocraNext\Statamic\Http\Controllers\PublicController;

Route::get('socranext/preview/{kind}', [PublicController::class, 'preview'])->whereIn('kind', ['faq', 'article', 'articles'])->name('socranext.preview');
Route::get('llms.txt', [PublicController::class, 'llms'])->name('socranext.llms');
Route::get('llms-full.txt', [PublicController::class, 'llms'])->defaults('full', true)->name('socranext.llms-full');
// Managed articles themselves use native collection routes. This stable archive
// endpoint remains available if the website supplies its own public archive.
Route::get('socranext/articles', [PublicController::class, 'articles'])->name('socranext.articles');
