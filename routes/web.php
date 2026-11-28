<?php

use App\Http\Controllers\AccountController as CustomerAccountController;
use App\Http\Controllers\Admin\AccessibilityController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AiController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\BookingAdminController;
use App\Http\Controllers\Admin\CommunityAdminController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesignController;
use App\Http\Controllers\Admin\EditorialController;
use App\Http\Controllers\Admin\ErpController;
use App\Http\Controllers\Admin\GeoController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\Licensing\LicenseController;
use App\Http\Controllers\Admin\Licensing\MarketplaceController;
use App\Http\Controllers\Admin\Licensing\ServerController as LicenseServerController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MembersAdminController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\NewsletterAdminController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\ReviewsController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SeoController;
use App\Http\Controllers\Admin\SeoToolsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\Shop\OrderController as ShopOrderController;
use App\Http\Controllers\Admin\Shop\ProductController as ShopProductController;
use App\Http\Controllers\Admin\Shop\ShopController as AdminShopController;
use App\Http\Controllers\Admin\SocialController;
use App\Http\Controllers\Admin\StarterController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\TurboController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Admin\WhatsAppController;
use App\Http\Controllers\Api\ErpApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Community\FeedController as CommunityFeed;
use App\Http\Controllers\Community\ForumController as CommunityForums;
use App\Http\Controllers\Community\MessageController as CommunityMessages;
use App\Http\Controllers\Community\ProfileController as CommunityProfiles;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\LicenseApiController;
use App\Http\Controllers\MembersController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\SearchController as SiteSearchController;
use App\Http\Controllers\SeoFileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SiteController;
use App\Http\Middleware\ErpToken;
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
Route::get('/suche', SiteSearchController::class)->middleware('throttle:60,1')->name('search');
Route::get('/llms.txt', [SeoFileController::class, 'llms'])->name('llms');
Route::get('/sitemap.xml', [SeoFileController::class, 'sitemap'])->name('sitemap');
Route::post('/kontakt/nachricht', ContactController::class)->middleware('throttle:5,1')->name('contact.send');
Route::middleware('feature:newsletter')->group(function () {
    Route::post('/newsletter/anmelden', [NewsletterController::class, 'subscribe'])->middleware('throttle:5,1')->name('newsletter.subscribe');
    Route::get('/newsletter/bestaetigen/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
});
Route::middleware('feature:terminbuchung')->prefix('termine')->name('booking.')->group(function () {
    Route::redirect('/', '/termine/buchen');
    Route::get('/buchen', [BookingController::class, 'show'])->name('show');
    Route::post('/buchen', [BookingController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/{token}', [BookingController::class, 'status'])->where('token', '[A-Za-z0-9]{40}')->name('status');
    Route::get('/{token}/kalender.ics', [BookingController::class, 'ics'])->where('token', '[A-Za-z0-9]{40}')->name('ics');
    Route::get('/{token}/absagen', [BookingController::class, 'cancelForm'])->where('token', '[A-Za-z0-9]{40}')->name('cancel');
    Route::post('/{token}/absagen', [BookingController::class, 'cancel'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:10,1')->name('cancel.do');
});
Route::middleware('feature:mitgliederbereich|community')->prefix('mitglieder')->group(function () {
    Route::get('/', [MembersController::class, 'home'])->name('members.home');
    Route::get('/anmelden', [MembersController::class, 'loginForm'])->name('members.login');
    Route::post('/anmelden', [MembersController::class, 'login'])->middleware('throttle:20,1')->name('members.login.do');
    Route::post('/abmelden', [MembersController::class, 'logout'])->name('members.logout');
    Route::get('/registrieren', [MembersController::class, 'registerForm'])->name('members.register');
    Route::post('/registrieren', [MembersController::class, 'register'])->middleware('throttle:5,1')->name('members.register.do');
    Route::get('/passwort-vergessen', [MembersController::class, 'forgotForm'])->name('members.forgot');
    Route::post('/passwort-vergessen', [MembersController::class, 'forgot'])->middleware('throttle:5,1')->name('members.forgot.do');
    Route::get('/downloads/{file}', [MembersController::class, 'download'])->name('members.download');
});
// Modul „Community“
Route::middleware(['feature:community', 'community'])->prefix('community')->name('community.')->group(function () {
    Route::get('/', [CommunityFeed::class, 'home'])->name('home');
    Route::post('/beitraege', [CommunityFeed::class, 'store'])->middleware('throttle:10,1')->name('posts.store');
    Route::get('/beitrag/{post}', [CommunityFeed::class, 'post'])->name('post');
    Route::delete('/beitrag/{post}', [CommunityFeed::class, 'destroy'])->name('posts.destroy');
    Route::post('/beitrag/{post}/gefaellt', [CommunityFeed::class, 'like'])->middleware('throttle:60,1')->name('posts.like');
    Route::post('/beitrag/{post}/anheften', [CommunityFeed::class, 'pin'])->name('posts.pin');
    Route::post('/beitrag/{post}/kommentare', [CommunityFeed::class, 'comment'])->middleware('throttle:20,1')->name('comments.store');
    Route::delete('/kommentar/{comment}', [CommunityFeed::class, 'commentDestroy'])->name('comments.destroy');
    Route::get('/leute', [CommunityFeed::class, 'people'])->name('people');
    Route::get('/benachrichtigungen', [CommunityFeed::class, 'notifications'])->name('notifications');
    Route::post('/melden', [CommunityFeed::class, 'report'])->middleware('throttle:10,1')->name('report');
    Route::get('/profil', [CommunityProfiles::class, 'edit'])->name('edit');
    Route::put('/profil', [CommunityProfiles::class, 'update'])->name('update');
    Route::get('/meine-seite', [CommunityProfiles::class, 'pageEdit'])->name('page.edit');
    Route::put('/meine-seite', [CommunityProfiles::class, 'pageUpdate'])->name('page.update');
    Route::delete('/konto', [CommunityProfiles::class, 'destroyAccount'])->middleware('throttle:5,1')->name('account.destroy');
    Route::get('/leute/{profile}', [CommunityProfiles::class, 'show'])->name('profile');
    Route::get('/leute/{profile}/seite', [CommunityProfiles::class, 'page'])->name('page');
    Route::post('/leute/{profile}/folgen', [CommunityFeed::class, 'follow'])->name('follow');
    Route::post('/leute/{profile}/blockieren', [CommunityFeed::class, 'block'])->name('block');
    Route::get('/nachrichten', [CommunityMessages::class, 'index'])->name('messages');
    Route::get('/nachrichten/{profile}', [CommunityMessages::class, 'show'])->name('conversation');
    Route::post('/nachrichten/{profile}', [CommunityMessages::class, 'store'])->middleware('throttle:20,1')->name('messages.store');
    Route::get('/foren', [CommunityForums::class, 'index'])->name('forums');
    Route::get('/foren/{board}', [CommunityForums::class, 'board'])->name('board');
    Route::get('/foren/{board}/neu', [CommunityForums::class, 'create'])->name('topic.create');
    Route::post('/foren/{board}', [CommunityForums::class, 'store'])->middleware('throttle:5,1')->name('topic.store');
    Route::get('/thema/{topic}/{slug?}', [CommunityForums::class, 'topic'])->whereNumber('topic')->name('topic');
    Route::post('/thema/{topic}/antworten', [CommunityForums::class, 'reply'])->middleware('throttle:20,1')->name('topic.reply');
    Route::put('/thema/{topic}', [CommunityForums::class, 'moderate'])->name('topic.moderate');
    Route::delete('/thema/{topic}', [CommunityForums::class, 'destroy'])->name('topic.destroy');
    Route::delete('/antwort/{reply}', [CommunityForums::class, 'destroyReply'])->name('reply.destroy');
});
// Neues Passwort für Mitglieder und Kundenkonten
Route::get('/passwort-neu/{token}', [MembersController::class, 'resetForm'])->name('password.reset');
Route::post('/passwort-neu', [MembersController::class, 'reset'])->middleware('throttle:10,1')->name('members.reset.do');
Route::middleware('feature:kundenkonto')->prefix('konto')->name('account.')->group(function () {
    Route::get('/', [CustomerAccountController::class, 'home'])->name('home');
    Route::get('/anmelden', [CustomerAccountController::class, 'loginForm'])->name('login');
    Route::post('/anmelden', [CustomerAccountController::class, 'login'])->middleware('throttle:20,1')->name('login.do');
    Route::post('/abmelden', [CustomerAccountController::class, 'logout'])->name('logout');
    Route::get('/registrieren', [CustomerAccountController::class, 'registerForm'])->name('register');
    Route::post('/registrieren', [CustomerAccountController::class, 'register'])->middleware('throttle:5,1')->name('register.do');
    Route::get('/passwort-vergessen', [CustomerAccountController::class, 'forgotForm'])->name('forgot');
    Route::post('/passwort-vergessen', [CustomerAccountController::class, 'forgot'])->middleware('throttle:5,1')->name('forgot.do');
    Route::get('/daten', [CustomerAccountController::class, 'edit'])->name('edit');
    Route::put('/daten', [CustomerAccountController::class, 'update'])->name('update');
    Route::put('/passwort', [CustomerAccountController::class, 'password'])->middleware('throttle:10,1')->name('password');
    Route::delete('/', [CustomerAccountController::class, 'destroy'])->name('destroy');
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
    Route::post('/warenkorb/gutschein', [ShopController::class, 'coupon'])->middleware(['feature:gutscheine', 'throttle:20,1'])->name('shop.coupon');
    Route::delete('/warenkorb/gutschein', [ShopController::class, 'removeCoupon'])->middleware('feature:gutscheine')->name('shop.coupon.remove');
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

// Schnittstelle für Warenwirtschaft und Buchhaltung (Modul Warenwirtschaft-Anbindung)
Route::prefix('api/nova/ww')->middleware([ErpToken::class, 'throttle:120,1'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->group(function () {
        Route::get('/produkte', [ErpApiController::class, 'products']);
        Route::patch('/produkte/{sku}', [ErpApiController::class, 'updateProduct'])->where('sku', '[^/]+');
        Route::get('/bestellungen', [ErpApiController::class, 'orders']);
        Route::patch('/bestellungen/{number}', [ErpApiController::class, 'updateOrder'])->where('number', '[A-Za-z0-9-]+');
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
    Route::middleware('feature:social')->prefix('social-media')->name('social')->group(function () {
        Route::get('/', [SocialController::class, 'index']);
        Route::put('/verbindung', [SocialController::class, 'connect'])->middleware('throttle:10,1')->name('.connect');
        Route::delete('/verbindung', [SocialController::class, 'disconnect'])->name('.disconnect');
        Route::post('/vorschlag', [SocialController::class, 'suggest'])->middleware('throttle:30,1')->name('.suggest');
        Route::post('/veroeffentlichen', [SocialController::class, 'publish'])->middleware('throttle:20,1')->name('.publish');
        Route::post('/beitraege/{post}/erneut', [SocialController::class, 'retry'])->middleware('throttle:20,1')->name('.retry');
    });
    Route::middleware('feature:redaktionsplan')->prefix('redaktionsplan')->name('plan')->group(function () {
        Route::get('/', [EditorialController::class, 'index']);
        Route::post('/', [EditorialController::class, 'store'])->name('.store');
        Route::put('/{item}', [EditorialController::class, 'update'])->name('.update');
        Route::delete('/{item}', [EditorialController::class, 'destroy'])->name('.destroy');
    });
    Route::middleware('feature:terminbuchung')->prefix('termine')->name('bookings')->group(function () {
        Route::get('/', [BookingAdminController::class, 'index']);
        Route::post('/{booking}/bestaetigen', [BookingAdminController::class, 'confirm'])->name('.confirm');
        Route::post('/{booking}/absagen', [BookingAdminController::class, 'cancel'])->name('.cancel');
        Route::post('/leistungen', [BookingAdminController::class, 'storeService'])->name('.services.store');
        Route::put('/leistungen/{service}', [BookingAdminController::class, 'updateService'])->name('.services.update');
        Route::delete('/leistungen/{service}', [BookingAdminController::class, 'destroyService'])->name('.services.destroy');
        Route::put('/einstellungen', [BookingAdminController::class, 'settings'])->name('.settings');
    });
    Route::middleware('feature:gutscheine')->prefix('gutscheine')->name('coupons')->group(function () {
        Route::get('/', [CouponController::class, 'index']);
        Route::post('/', [CouponController::class, 'store'])->name('.store');
        Route::put('/{coupon}', [CouponController::class, 'update'])->name('.update');
        Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('.destroy');
    });
    Route::middleware('feature:mitgliederbereich|community')->prefix('mitglieder')->name('members')->group(function () {
        Route::get('/', [MembersAdminController::class, 'index']);
        Route::post('/einladen', [MembersAdminController::class, 'invite'])->middleware('throttle:30,1')->name('.invite');
        Route::put('/einstellungen', [MembersAdminController::class, 'settings'])->name('.settings');
        Route::post('/dateien', [MembersAdminController::class, 'upload'])->name('.files.store');
        Route::delete('/dateien/{file}', [MembersAdminController::class, 'deleteFile'])->name('.files.destroy');
        Route::put('/{user}', [MembersAdminController::class, 'update'])->name('.update');
        Route::delete('/{user}', [MembersAdminController::class, 'destroy'])->name('.destroy');
    });
    Route::middleware('feature:community')->prefix('community')->name('community')->group(function () {
        Route::get('/', [CommunityAdminController::class, 'index']);
        Route::put('/einstellungen', [CommunityAdminController::class, 'settings'])->name('.settings');
        Route::post('/foren', [CommunityAdminController::class, 'storeBoard'])->name('.boards.store');
        Route::put('/foren/{board}', [CommunityAdminController::class, 'updateBoard'])->name('.boards.update');
        Route::delete('/foren/{board}', [CommunityAdminController::class, 'destroyBoard'])->name('.boards.destroy');
        Route::put('/meldungen/{report}', [CommunityAdminController::class, 'resolve'])->name('.reports.resolve');
        Route::put('/sperren/{user}', [CommunityAdminController::class, 'ban'])->name('.ban');
    });
    Route::middleware('feature:warenwirtschaft')->prefix('warenwirtschaft')->name('erp')->group(function () {
        Route::get('/', [ErpController::class, 'index']);
        Route::get('/produkte.csv', [ErpController::class, 'products'])->name('.products');
        Route::get('/bestellungen.csv', [ErpController::class, 'orders'])->name('.orders');
        Route::post('/import', [ErpController::class, 'import'])->name('.import');
        Route::post('/schluessel', [ErpController::class, 'token'])->name('.token');
        Route::delete('/schluessel', [ErpController::class, 'revoke'])->name('.revoke');
    });
    Route::middleware('feature:datensicherung')->prefix('datensicherung')->name('backups')->group(function () {
        Route::get('/', [BackupController::class, 'index']);
        Route::post('/', [BackupController::class, 'store'])->middleware('throttle:10,1')->name('.store');
        Route::put('/einstellungen', [BackupController::class, 'settings'])->name('.settings');
        Route::post('/zurueckspielen', [BackupController::class, 'restore'])->middleware('throttle:5,1')->name('.restore');
        Route::get('/{name}', [BackupController::class, 'download'])->where('name', 'nova-sicherung-[a-z0-9-]+\.zip')->name('.download');
        Route::delete('/{name}', [BackupController::class, 'destroy'])->where('name', 'nova-sicherung-[a-z0-9-]+\.zip')->name('.destroy');
    });
    Route::middleware('feature:google-bewertungen')->prefix('google-bewertungen')->name('reviews')->group(function () {
        Route::get('/', [ReviewsController::class, 'index']);
        Route::put('/', [ReviewsController::class, 'update'])->middleware('throttle:20,1')->name('.update');
        Route::post('/abrufen', [ReviewsController::class, 'refresh'])->middleware('throttle:10,1')->name('.refresh');
    });
    Route::middleware('feature:kundenkonto')->prefix('kunden')->name('customers')->group(function () {
        Route::get('/', [CustomerController::class, 'index']);
        Route::delete('/{user}', [CustomerController::class, 'destroy'])->name('.destroy');
    });
    Route::get('/statistik', StatsController::class)->middleware('feature:statistik')->name('stats');
    Route::middleware('feature:turbo')->prefix('turbo')->name('turbo')->group(function () {
        Route::get('/', [TurboController::class, 'index']);
        Route::put('/', [TurboController::class, 'update'])->name('.update');
        Route::delete('/', [TurboController::class, 'clear'])->name('.clear');
    });
    Route::put('/menue', [MenuController::class, 'update'])->name('menu.update');
    Route::post('/seiten/live-vorschau', [PageController::class, 'livePreview'])->name('pages.live-preview');
    Route::post('/seiten/baustein-vorschau', [PageController::class, 'blockPreview'])->name('pages.block-preview');
    Route::post('/seiten/vorlagen', [PageController::class, 'storeTemplate'])->name('pages.templates');
    Route::delete('/seiten/vorlagen/{id}', [PageController::class, 'destroyTemplate'])->where('id', '[a-z0-9]{1,24}')->name('pages.templates.destroy');
    Route::get('/seiten/{page}/leinwand', [PageController::class, 'canvas'])->name('pages.canvas');
    Route::post('/seiten', [PageController::class, 'store'])->name('pages.store');
    Route::get('/seiten/{page}', [PageController::class, 'edit'])->name('pages.edit');
    Route::put('/seiten/{page}', [PageController::class, 'update'])->name('pages.update');
    Route::get('/seiten/{page}/vorschau', [PageController::class, 'preview'])->name('pages.preview');
    Route::post('/seiten/{page}/versionen/{revision}', [PageController::class, 'restoreRevision'])->name('pages.revisions.restore');
    Route::delete('/seiten/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
    Route::post('/seiten/{page}/uebersetzung', [LanguageController::class, 'translate'])->middleware(['feature:mehrsprachigkeit', 'throttle:20,1'])->name('pages.translate');
    Route::middleware('feature:mehrsprachigkeit')->prefix('sprachen')->name('languages')->group(function () {
        Route::get('/', [LanguageController::class, 'index']);
        Route::put('/', [LanguageController::class, 'update'])->name('.update');
    });
    Route::post('/papierkorb/{id}', [PageController::class, 'restore'])->whereNumber('id')->name('pages.restore');
    Route::delete('/papierkorb/{id}', [PageController::class, 'forceDelete'])->whereNumber('id')->name('pages.force-delete');

    Route::get('/medien', [MediaController::class, 'index'])->name('media.index');
    Route::get('/medien/liste', [MediaController::class, 'list'])->name('media.list');
    Route::post('/medien/video-vorschaubild', [MediaController::class, 'videoThumbnail'])->middleware('throttle:20,1')->name('media.video-thumb');
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
    Route::middleware('feature:seo-pro')->group(function () {
        Route::get('/seo/mitbewerber', [SeoToolsController::class, 'competitors'])->name('seo.competitors');
        Route::post('/seo/mitbewerber', [SeoToolsController::class, 'addCompetitor'])->middleware('throttle:10,1')->name('seo.competitors.store');
        Route::post('/seo/mitbewerber/{competitor}/aktualisieren', [SeoToolsController::class, 'refreshCompetitor'])->middleware('throttle:10,1')->name('seo.competitors.refresh');
        Route::delete('/seo/mitbewerber/{competitor}', [SeoToolsController::class, 'removeCompetitor'])->name('seo.competitors.destroy');
        Route::post('/seo/massnahmen', [SeoToolsController::class, 'toggleMeasure'])->name('seo.measures.toggle');
    });
    Route::middleware('feature:geo')->group(function () {
        Route::get('/seo/ki-sichtbarkeit', [GeoController::class, 'index'])->name('geo');
        Route::put('/seo/ki-sichtbarkeit', [GeoController::class, 'update'])->name('geo.update');
    });
    Route::get('/seo/weiterleitungen', [SeoToolsController::class, 'redirects'])->name('seo.redirects');
    Route::post('/seo/weiterleitungen', [SeoToolsController::class, 'storeRedirect'])->name('seo.redirects.store');
    Route::delete('/seo/weiterleitungen/{redirect}', [SeoToolsController::class, 'destroyRedirect'])->name('seo.redirects.destroy');
    Route::delete('/seo/nicht-gefunden', [SeoToolsController::class, 'clearNotFound'])->name('seo.notfound.clear');

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

    Route::get('/barrierefreiheit', [AccessibilityController::class, 'index'])->name('accessibility');
    Route::post('/barrierefreiheit/erklaerung', [AccessibilityController::class, 'statement'])->name('accessibility.statement');
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
    Route::put('/einstellungen/website', [SettingsController::class, 'website'])->name('settings.website');
    Route::put('/einstellungen/email', [SettingsController::class, 'mail'])->name('settings.mail');
    Route::post('/einstellungen/email/test', [SettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.mail.test');

    Route::get('/lizenz', [LicenseController::class, 'index'])->name('license');
    Route::post('/lizenz', [LicenseController::class, 'store'])->middleware('throttle:10,1')->name('license.store');
    Route::post('/lizenz/pruefen', [LicenseController::class, 'refresh'])->middleware('throttle:10,1')->name('license.refresh');
    Route::delete('/lizenz', [LicenseController::class, 'destroy'])->name('license.destroy');

    Route::get('/plugins/marktplatz', [MarketplaceController::class, 'index'])->name('plugins.market');
    Route::post('/plugins/marktplatz/{slug}', [MarketplaceController::class, 'install'])->middleware('throttle:20,1')->where('slug', '[a-z0-9-]+')->name('plugins.market.install');

    Route::get('/lizenzserver', [LicenseServerController::class, 'index'])->name('license-server');
    Route::get('/lizenzserver/preise', [LicenseServerController::class, 'pricing'])->name('license-server.pricing');
    Route::put('/lizenzserver/preise', [LicenseServerController::class, 'updatePricing'])->name('license-server.pricing.update');
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

// Modul Mehrsprachigkeit: /en, /en/about-us. Ist die Sprache nicht eingeschaltet, gilt /xx als normale Seite.
Route::get('/{locale}', [SiteController::class, 'localeHome'])->where('locale', '[a-z]{2}')->name('home.locale');
Route::get('/{locale}/{slug}', [SiteController::class, 'localeShow'])
    ->where(['locale' => '[a-z]{2}', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])->name('page.locale');

// Öffentliche Seiten, z. B. /ueber-uns. Muss als letzte Route stehen.
Route::get('/{slug}', [SiteController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page.show');
