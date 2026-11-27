<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignController;
use App\Http\Controllers\Admin\Licensing\LicenseController;
use App\Http\Controllers\Admin\Licensing\MarketplaceController;
use App\Http\Controllers\Admin\Licensing\ServerController as LicenseServerController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\NewsletterAdminController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\Shop\OrderController as ShopOrderController;
use App\Http\Controllers\Admin\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Admin\Shop\ShopController as AdminShopController;
use App\Http\Controllers\Admin\StarterController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LicenseApiController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\SeoFileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\LicenseGate;
use App\Http\Middleware\ShopEnabled;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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
Route::get('/robots.txt', [SeoFileController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoFileController::class, 'sitemap'])->name('sitemap');
Route::post('/kontakt/nachricht', ContactController::class)->middleware('throttle:5,1')->name('contact.send');
Route::middleware('feature:newsletter')->group(function () {
    Route::post('/newsletter/anmelden', [NewsletterController::class, 'subscribe'])->middleware('throttle:5,1')->name('newsletter.subscribe');
    Route::get('/newsletter/bestaetigen/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
});
// Abmelden geht immer, auch wenn das Modul später wegfällt
Route::get('/newsletter/abmelden/{token}', [NewsletterController::class, 'unsubscribeForm'])->name('newsletter.unsubscribe');
Route::post('/newsletter/abmelden/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe.do');

// Shop
Route::middleware(ShopEnabled::class)->group(function () {
    Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
    Route::get('/shop/{slug}', [ShopController::class, 'show'])->name('shop.product');
    Route::get('/warenkorb', [ShopController::class, 'cart'])->name('shop.cart');
    Route::post('/warenkorb', [ShopController::class, 'add'])->middleware('throttle:60,1')->name('shop.cart.add');
    Route::patch('/warenkorb', [ShopController::class, 'update'])->name('shop.cart.update');
    Route::get('/kasse', [ShopController::class, 'checkout'])->name('shop.checkout');
    Route::post('/kasse', [ShopController::class, 'placeOrder'])->middleware('throttle:10,1')->name('shop.checkout.place');
    Route::get('/bestellung/{token}', [ShopController::class, 'order'])->name('shop.order');
    Route::post('/bestellung/{token}/bezahlen', [ShopController::class, 'pay'])->middleware('throttle:10,1')->name('shop.pay');
    Route::get('/bestellung/{token}/zahlung/{method}', [ShopController::class, 'paymentReturn'])->name('shop.payment.return');
});

// Schnittstelle des Lizenzservers (nur aktiv, wenn diese Installation Lizenzserver ist)
Route::prefix('api/nova')->middleware('throttle:60,1')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->group(function () {
        Route::post('/aktivieren', [LicenseApiController::class, 'activate']);
        Route::post('/freigeben', [LicenseApiController::class, 'release']);
        Route::post('/marktplatz', [LicenseApiController::class, 'catalog']);
        Route::post('/marktplatz/{slug}', [LicenseApiController::class, 'download'])->where('slug', '[a-z0-9-]+');
    });

// Anmeldung
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'show'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login'])->middleware('throttle:20,1')->name('login.attempt');
    Route::get('/admin/login/code', [LoginController::class, 'twoFactorForm'])->name('login.two-factor');
    Route::post('/admin/login/code', [LoginController::class, 'twoFactor'])->middleware('throttle:20,1')->name('login.two-factor.verify');
});
Route::post('/admin/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Verwaltung
Route::middleware(['auth', 'admin', LicenseGate::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/seiten', [PageController::class, 'index'])->name('pages.index');
    Route::get('/suche', SearchController::class)->name('search');
    Route::get('/menue', [MenuController::class, 'index'])->name('menu');
    Route::get('/anfragen', [ContactMessageController::class, 'index'])->name('messages');
    Route::patch('/anfragen/{message}', [ContactMessageController::class, 'read'])->name('messages.read');
    Route::delete('/anfragen/{message}', [ContactMessageController::class, 'destroy'])->name('messages.destroy');
    Route::post('/startinhalt', [StarterController::class, 'store'])->name('starter.store');
    Route::post('/startinhalt/aktualisieren', [StarterController::class, 'update'])->name('starter.update');
    Route::get('/design', [DesignController::class, 'index'])->name('design');
    Route::put('/design', [DesignController::class, 'update'])->name('design.update');
    Route::middleware('feature:whatsapp')->group(function () {
        Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp');
        Route::put('/whatsapp', [WhatsAppController::class, 'update'])->name('whatsapp.update');
    });
    Route::middleware('feature:newsletter')->prefix('newsletter')->name('newsletter.')->group(function () {
        Route::get('/', [NewsletterAdminController::class, 'index'])->name('index');
        Route::get('/abonnenten.csv', [NewsletterAdminController::class, 'export'])->name('export');
        Route::delete('/abonnenten/{subscriber}', [NewsletterAdminController::class, 'destroySubscriber'])->name('subscribers.destroy');
        Route::get('/neu', [NewsletterAdminController::class, 'create'])->name('create');
        Route::post('/', [NewsletterAdminController::class, 'store'])->name('store');
        Route::get('/{campaign}', [NewsletterAdminController::class, 'edit'])->name('edit');
        Route::put('/{campaign}', [NewsletterAdminController::class, 'update'])->name('update');
        Route::delete('/{campaign}', [NewsletterAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{campaign}/test', [NewsletterAdminController::class, 'test'])->name('test');
        Route::post('/{campaign}/senden', [NewsletterAdminController::class, 'sendBatch'])->name('send');
    });
    Route::put('/menue', [MenuController::class, 'update'])->name('menu.update');
    Route::post('/seiten/live-vorschau', [PageController::class, 'livePreview'])->name('pages.live-preview');
    Route::post('/seiten', [PageController::class, 'store'])->name('pages.store');
    Route::get('/seiten/{page}', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('/seiten/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::get('/seiten/{page}/vorschau', [PageController::class, 'preview'])->name('pages.preview');
    Route::post('/seiten/{page}/versionen/{revision}', [PageController::class, 'restoreRevision'])->name('pages.revisions.restore');
    Route::delete('/seiten/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    Route::post('/papierkorb/{id}', [PageController::class, 'restore'])->whereNumber('id')->name('pages.restore');
    Route::delete('/papierkorb/{id}', [PageController::class, 'forceDelete'])->whereNumber('id')->name('pages.force-delete');

    Route::get('/medien', [MediaController::class, 'index'])->name('media.index');
    Route::get('/medien/liste', [MediaController::class, 'list'])->name('media.list');
    Route::get('/medien/{media}', [MediaController::class, 'show'])->name('media.show');
    Route::patch('/medien/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/medien/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    Route::post('/medien/{media}/beschreibung', [MediaController::class, 'describe'])->middleware('throttle:30,1')->name('media.describe');

    Route::post('/uploads', [UploadController::class, 'store'])->middleware('throttle:60,1')->name('uploads.store');

    Route::get('/ki', [AiController::class, 'index'])->name('ai');
    Route::put('/ki', [AiController::class, 'update'])->name('ai.update');
    Route::post('/ki/test', [AiController::class, 'test'])->middleware('throttle:10,1')->name('ai.test');
    Route::get('/ki/assistent', [AiController::class, 'wizard'])->name('ai.wizard');
    Route::post('/ki/assistent', [AiController::class, 'runWizard'])->middleware('throttle:5,1')->name('ai.wizard.run');
    Route::post('/ki/text', [AiController::class, 'rewrite'])->middleware('throttle:30,1')->name('ai.rewrite');
    Route::post('/ki/beschreibung', [AiController::class, 'description'])->middleware('throttle:30,1')->name('ai.description');

    Route::get('/seo', [SeoController::class, 'index'])->name('seo');
    Route::put('/seo', [SeoController::class, 'settings'])->name('seo.settings');
    Route::post('/seo/pruefen', [SeoController::class, 'analyse'])->middleware('throttle:120,1')->name('seo.analyse');
    Route::post('/seo/suchbegriffe', [SeoController::class, 'keywords'])->middleware('throttle:20,1')->name('seo.keywords');
    Route::post('/seo/vergleich', [SeoController::class, 'compare'])->middleware('throttle:10,1')->name('seo.compare');

    Route::get('/shop', [AdminShopController::class, 'index'])->name('shop.index');
    Route::get('/shop/einstellungen', [AdminShopController::class, 'settings'])->name('shop.settings');
    Route::put('/shop/einstellungen', [AdminShopController::class, 'update'])->name('shop.settings.update');
    Route::get('/shop/produkte', [ShopProductController::class, 'index'])->name('shop.products.index');
    Route::get('/shop/produkte/neu', [ShopProductController::class, 'create'])->name('shop.products.create');
    Route::post('/shop/produkte', [ShopProductController::class, 'store'])->name('shop.products.store');
    Route::get('/shop/produkte/{product}', [ShopProductController::class, 'edit'])->name('shop.products.edit');
    Route::put('/shop/produkte/{product}', [ShopProductController::class, 'update'])->name('shop.products.update');
    Route::delete('/shop/produkte/{product}', [ShopProductController::class, 'destroy'])->name('shop.products.destroy');
    Route::get('/shop/bestellungen/{order}', [ShopOrderController::class, 'show'])->name('shop.orders.show');
    Route::put('/shop/bestellungen/{order}', [ShopOrderController::class, 'update'])->name('shop.orders.update');
    Route::get('/shop/bestellungen/{order}/rechnung', [ShopOrderController::class, 'invoice'])->name('shop.orders.invoice');
    Route::post('/ki/produkt', [AiController::class, 'productText'])->middleware('throttle:30,1')->name('ai.product');

    Route::get('/sicherheit', [SecurityController::class, 'index'])->name('security');
    Route::post('/sicherheit/pruefen', [SecurityController::class, 'scan'])->middleware('throttle:10,1')->name('security.scan');
    Route::post('/sicherheit/beheben', [SecurityController::class, 'fix'])->name('security.fix');
    Route::put('/sicherheit', [SecurityController::class, 'settings'])->name('security.settings');
    Route::post('/sicherheit/sperren', [SecurityController::class, 'block'])->name('security.block');
    Route::delete('/sicherheit/sperren/{blockedIp}', [SecurityController::class, 'unblock'])->name('security.unblock');

    Route::get('/konto', [AccountController::class, 'edit'])->name('account');
    Route::put('/konto', [AccountController::class, 'update'])->name('account.update');
    Route::put('/konto/passwort', [AccountController::class, 'password'])->middleware('throttle:10,1')->name('account.password');
    Route::post('/konto/zwei-faktor', [AccountController::class, 'startTwoFactor'])->name('account.2fa.start');
    Route::post('/konto/zwei-faktor/bestaetigen', [AccountController::class, 'confirmTwoFactor'])->middleware('throttle:10,1')->name('account.2fa.confirm');
    Route::post('/konto/zwei-faktor/notfall-codes', [AccountController::class, 'recoveryCodes'])->middleware('throttle:10,1')->name('account.2fa.recovery');
    Route::delete('/konto/zwei-faktor', [AccountController::class, 'disableTwoFactor'])->middleware('throttle:10,1')->name('account.2fa.disable');

    Route::get('/einstellungen', [SettingsController::class, 'edit'])->name('settings');
    Route::put('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');
    Route::put('/einstellungen/email', [SettingsController::class, 'mail'])->name('settings.mail');
    Route::post('/einstellungen/email/test', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.mail.test');

    Route::get('/lizenz', [LicenseController::class, 'index'])->name('license');
    Route::post('/lizenz', [LicenseController::class, 'store'])->middleware('throttle:10,1')->name('license.store');
    Route::post('/lizenz/pruefen', [LicenseController::class, 'refresh'])->middleware('throttle:10,1')->name('license.refresh');
    Route::delete('/lizenz', [LicenseController::class, 'destroy'])->name('license.destroy');

    Route::get('/plugins/marktplatz', [MarketplaceController::class, 'index'])->name('plugins.market');
    Route::post('/plugins/marktplatz/{slug}', [MarketplaceController::class, 'install'])->middleware('throttle:20,1')->where('slug', '[a-z0-9-]+')->name('plugins.market.install');

    Route::get('/lizenzserver', [LicenseServerController::class, 'index'])->name('license-server');
    Route::post('/lizenzserver/lizenzen', [LicenseServerController::class, 'store'])->name('license-server.store');
    Route::get('/lizenzserver/lizenzen/{license}', [LicenseServerController::class, 'show'])->name('license-server.show');
    Route::put('/lizenzserver/lizenzen/{license}', [LicenseServerController::class, 'update'])->name('license-server.update');
    Route::delete('/lizenzserver/lizenzen/{license}/websites/{activation}', [LicenseServerController::class, 'releaseActivation'])->name('license-server.release');
    Route::post('/lizenzserver/module', [LicenseServerController::class, 'uploadModule'])->name('license-server.modules.upload');
    Route::put('/lizenzserver/module/{module}', [LicenseServerController::class, 'updateModule'])->name('license-server.modules.update');

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
