<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\InstallController;
use App\Models\Setting;
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

// Öffentliche Website (wird in Schritt 2 durch echte Seiten ersetzt)
Route::get('/', fn () => view('site.home', [
    'tagline' => Setting::get('tagline'),
]))->name('home');

// Anmeldung
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'show'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
});
Route::post('/admin/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Verwaltung
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/einstellungen', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/plugins', [PluginController::class, 'index'])->name('plugins');
    Route::post('/plugins', [PluginController::class, 'upload'])->name('plugins.upload');
    Route::post('/plugins/{slug}/aktivieren', [PluginController::class, 'activate'])->name('plugins.activate');
    Route::post('/plugins/{slug}/deaktivieren', [PluginController::class, 'deactivate'])->name('plugins.deactivate');
    Route::delete('/plugins/{slug}', [PluginController::class, 'destroy'])->name('plugins.destroy');
});
