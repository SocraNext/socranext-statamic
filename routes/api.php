<?php

use Illuminate\Support\Facades\Route;
use SocraNext\Statamic\Http\Controllers\ConnectionController;
use SocraNext\Statamic\Http\Controllers\ContentController;
use SocraNext\Statamic\Http\Controllers\PresentationController;
use SocraNext\Statamic\Http\Middleware\ApiResponse;
use SocraNext\Statamic\Http\Middleware\Authenticate;
use SocraNext\Statamic\ServiceProvider;

Route::prefix(ServiceProvider::API_PREFIX)->name('socranext.api.')->middleware(['api', ApiResponse::class])->group(function () {
    Route::post('connect/token', [ConnectionController::class, 'receive'])->middleware('throttle:socranext-connect')->name('connect');
    Route::middleware([Authenticate::class, 'throttle:socranext-api'])->group(function () {
        Route::get('status', [ConnectionController::class, 'status'])->name('status');
        Route::get('languages', [ContentController::class, 'languages']);
        Route::get('translations', [ContentController::class, 'translations']);
        Route::get('post-types', [ContentController::class, 'postTypes']);
        Route::get('search-url', [ContentController::class, 'searchUrl']);
        Route::patch('metadata/{id}', [\SocraNext\Statamic\Http\Controllers\MetadataController::class, 'update']);
        Route::post('blog', [ContentController::class, 'publish']);
        Route::patch('blog/meta/{id}', [ContentController::class, 'update']);
        Route::delete('blog/{id}', [ContentController::class, 'delete']);
        Route::post('blog-category', [ContentController::class, 'createCategory']);
        Route::delete('blog-category/{id}', [ContentController::class, 'deleteCategory']);
        Route::post('purge', [ContentController::class, 'purge']);
        Route::post('collection-slugs', [ContentController::class, 'collectionSlugs']);

        foreach (['pages' => '', 'posts' => 'post-', 'products' => 'product-', 'categories' => 'category-'] as $type => $prefix) {
            Route::get($type, [ContentController::class, 'index'])->defaults('type', $type);
            Route::post('store-'.$prefix.'aio-information', [PresentationController::class, 'storeFaq'])->defaults('type', $type);
            Route::get($prefix.'aio-information/{id}', [PresentationController::class, 'getFaq'])->defaults('type', $type);
            Route::post($prefix.'toggle', [PresentationController::class, 'toggleFaq'])->defaults('type', $type);
        }
        Route::get('{type}/{id}', [ContentController::class, 'show'])->whereIn('type', ['pages', 'posts', 'products', 'categories']);
        Route::get('cpt/{cpt}/posts', [ContentController::class, 'cptIndex']);
        Route::get('cpt/{cpt}/posts/{id}', [ContentController::class, 'cptShow']);
        Route::post('cpt/{cpt}/store-aio-information', [PresentationController::class, 'cptStoreFaq']);
        Route::post('cpt/{cpt}/toggle', [PresentationController::class, 'cptToggleFaq']);
        Route::get('cpt/{cpt}/aio-information/{id}', [PresentationController::class, 'cptGetFaq']);

        foreach (['store-blog-custom' => 'blog', 'store-faq-custom' => 'faq', 'articles-list-custom' => 'articles'] as $endpoint => $slot) {
            Route::post($endpoint, [PresentationController::class, 'storeStyle'])->defaults('slot', $slot);
        }
        foreach (['blog-custom' => 'blog', 'faq-custom' => 'faq', 'articles-list-custom' => 'articles'] as $endpoint => $slot) {
            Route::get($endpoint, [PresentationController::class, 'getStyle'])->defaults('slot', $slot);
        }
        Route::post('llmstxt', [PresentationController::class, 'storeLlms']);
        Route::post('preview-token', [PresentationController::class, 'previewToken']);
        Route::match(['GET', 'POST'], 'faq-render-mode', [PresentationController::class, 'renderMode']);
    });
});
