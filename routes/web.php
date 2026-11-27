<?php

use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

// Installations-Assistent (nur erreichbar, solange Nova CMS nicht installiert ist)
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/datenbank', [InstallController::class, 'database'])->name('database');
    Route::post('/datenbank', [InstallController::class, 'saveDatabase'])->name('database.save');
    Route::get('/website', [InstallController::class, 'site'])->name('site');
    Route::post('/website', [InstallController::class, 'finish'])->name('finish');
    Route::get('/fertig', [InstallController::class, 'done'])->name('done');
});

Route::get('/', [SiteController::class, 'home'])->name('home');

// Anmeldung
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'show'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
});
Route::post('/admin/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Verwaltung
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/seiten', [PageController::class, 'index'])->name('pages.index');
    Route::post('/seiten', [PageController::class, 'store'])->name('pages.store');
    Route::get('/seiten/{page}', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('/seiten/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::get('/seiten/{page}/vorschau', [PageController::class, 'preview'])->name('pages.preview');
    Route::post('/seiten/{page}/versionen/{revision}', [PageController::class, 'restoreRevision'])->name('pages.revisions.restore');
    Route::delete('/seiten/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    Route::post('/papierkorb/{id}', [PageController::class, 'restore'])->whereNumber('id')->name('pages.restore');
    Route::delete('/papierkorb/{id}', [PageController::class, 'forceDelete'])->whereNumber('id')->name('pages.force-delete');

    Route::post('/uploads', [UploadController::class, 'store'])->middleware('throttle:60,1')->name('uploads.store');

    Route::get('/ki', [AiController::class, 'index'])->name('ai');
    Route::put('/ki', [AiController::class, 'update'])->name('ai.update');
    Route::post('/ki/test', [AiController::class, 'test'])->middleware('throttle:10,1')->name('ai.test');
    Route::get('/ki/assistent', [AiController::class, 'wizard'])->name('ai.wizard');
    Route::post('/ki/assistent', [AiController::class, 'runWizard'])->middleware('throttle:5,1')->name('ai.wizard.run');
    Route::post('/ki/text', [AiController::class, 'rewrite'])->middleware('throttle:30,1')->name('ai.rewrite');
    Route::post('/ki/beschreibung', [AiController::class, 'description'])->middleware('throttle:30,1')->name('ai.description');

    Route::get('/einstellungen', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/plugins', [PluginController::class, 'index'])->name('plugins');
    Route::post('/plugins', [PluginController::class, 'upload'])->name('plugins.upload');
    Route::post('/plugins/{slug}/aktivieren', [PluginController::class, 'activate'])->name('plugins.activate');
    Route::post('/plugins/{slug}/deaktivieren', [PluginController::class, 'deactivate'])->name('plugins.deactivate');
    Route::delete('/plugins/{slug}', [PluginController::class, 'destroy'])->name('plugins.destroy');
});

// Öffentliche Seiten, z. B. /ueber-uns. Muss als letzte Route stehen.
Route::get('/{slug}', [SiteController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page.show');
