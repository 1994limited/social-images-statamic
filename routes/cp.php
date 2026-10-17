<?php

use Illuminate\Support\Facades\Route;
use NineteenNinetyFour\SocialImages\Http\Controllers\CP\ImagesController;
use NineteenNinetyFour\SocialImages\Http\Controllers\CP\LibraryController;
use NineteenNinetyFour\SocialImages\Http\Controllers\CP\RendererController;
use NineteenNinetyFour\SocialImages\Http\Controllers\CP\TemplatesController;

Route::prefix('social-images')->name('social-images.')->group(function () {
    Route::get('/', [TemplatesController::class, 'index'])->name('index');

    Route::post('templates', [TemplatesController::class, 'store'])->name('templates.store');
    Route::post('templates/import', [TemplatesController::class, 'import'])->name('templates.import');
    Route::get('templates/{handle}', [TemplatesController::class, 'edit'])->name('templates.edit');
    Route::patch('templates/{handle}', [TemplatesController::class, 'update'])->name('templates.update');
    Route::delete('templates/{handle}', [TemplatesController::class, 'destroy'])->name('templates.destroy');
    Route::post('templates/{handle}/duplicate', [TemplatesController::class, 'duplicate'])->name('templates.duplicate');
    Route::get('templates/{handle}/export', [TemplatesController::class, 'export'])->name('templates.export');
    Route::get('templates/{handle}/thumbnail', [TemplatesController::class, 'thumbnail'])->name('templates.thumbnail');

    Route::post('preview', [TemplatesController::class, 'preview'])->name('preview');
    Route::post('render', [TemplatesController::class, 'render'])->name('render');
    Route::get('entries', [TemplatesController::class, 'entries'])->name('entries');

    Route::get('fonts', [LibraryController::class, 'fonts'])->name('fonts.index');
    Route::post('fonts/google', [LibraryController::class, 'google'])->name('fonts.google');
    Route::post('fonts/upload', [LibraryController::class, 'uploadFont'])->name('fonts.upload');
    Route::delete('fonts', [LibraryController::class, 'deleteFont'])->name('fonts.destroy');
    Route::post('uploads', [LibraryController::class, 'upload'])->name('uploads.store');
    Route::get('uploads/{name}', [LibraryController::class, 'show'])->name('uploads.show');

    Route::post('renderer/check', [RendererController::class, 'check'])->name('renderer.check');

    Route::get('images', [ImagesController::class, 'index'])->name('images.index');
    Route::get('images/{entryId}', [ImagesController::class, 'show'])->name('images.show');
    Route::post('images/{entryId}/regenerate', [ImagesController::class, 'regenerate'])->name('images.regenerate');
});
