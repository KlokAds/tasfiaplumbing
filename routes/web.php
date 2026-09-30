<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\BreadcrumbController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DraftController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PriceController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SeoHealthController;
use App\Http\Controllers\Admin\SeoSettingController;
use App\Http\Controllers\Admin\ServiceCategoryController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\HeroController;
use App\Http\Controllers\Admin\HomeStaticController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\PageSeoController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\CacheController;
use App\Http\Controllers\Admin\ContentCheckController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\FrontendController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Frontend SPA Routes (Inertia.js - Without Page Reload)
|--------------------------------------------------------------------------
*/
// Email "opened" pixel: marks that enquiry as read (signed link, so it cannot be guessed).
Route::get('/mail/seen/{message}', [\App\Http\Controllers\MailSeenController::class, 'show'])->middleware('signed')->name('mail.seen');

// Fresh upload only (closed for good once it has run): connect MySQL and load the website data.
// These middlewares read the database, which is not available yet.
$installWithout = [
    \App\Http\Middleware\SiteAccess::class,
    \App\Http\Middleware\UseLibraryFiles::class,
    \App\Http\Middleware\PublishScheduledArticles::class,
    \App\Http\Middleware\HandleInertiaRequests::class,
];
Route::get('/install', [\App\Http\Controllers\InstallController::class, 'show'])->withoutMiddleware($installWithout)->name('install');
Route::post('/install', [\App\Http\Controllers\InstallController::class, 'store'])->withoutMiddleware($installWithout)->middleware('throttle:10,1')->name('install.store');

// First run only (closed once a user exists): create the owner account.
Route::get('/setup', [\App\Http\Controllers\SetupController::class, 'show'])->name('setup');
Route::post('/setup', [\App\Http\Controllers\SetupController::class, 'store'])->middleware('throttle:10,1')->name('setup.store');

Route::get('/', [FrontendController::class, 'index'])->name('home');
Route::get('/about', [FrontendController::class, 'about'])->name('about');
Route::get('/services', [FrontendController::class, 'services'])->name('services');
Route::get('/services/{category}', [FrontendController::class, 'serviceCategory'])->name('service.category');
Route::get('/service/{slug}', [FrontendController::class, 'serviceDetail'])->name('service.detail');
Route::get('/pricing', [FrontendController::class, 'pricing'])->name('pricing');
Route::get('/locations', [FrontendController::class, 'locations'])->name('locations');
Route::get('/locations/{slug}', [FrontendController::class, 'locationDetail'])->name('location.detail');
Route::permanentRedirect('/articles', '/blogs');
Route::get('/search', [\App\Http\Controllers\SearchController::class, 'index'])->middleware('throttle:60,1')->name('search');
Route::get('/search/suggest', [\App\Http\Controllers\SearchController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
Route::get('/cache/img/{width}/{path}', [\App\Http\Controllers\ImageController::class, 'show'])
    ->whereNumber('width')->where('path', '.*')->middleware('throttle:300,1')->name('image.resized')
    // An image needs no session or cookies, and a response with cookies is never kept by the CDN.
    ->withoutMiddleware([
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \App\Http\Middleware\SiteAccess::class,
        \App\Http\Middleware\UseLibraryFiles::class,
        \App\Http\Middleware\PublishScheduledArticles::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);
Route::get('/projects', [FrontendController::class, 'projects'])->name('projects');
Route::get('/blogs', [FrontendController::class, 'blogs'])->name('blogs');
Route::get('/blogs/{slug}', [FrontendController::class, 'blogDetail'])->name('blog.detail');
Route::get('/reviews', [FrontendController::class, 'reviews'])->name('reviews');
// Old URL (indexed and linked from other sites) keeps its value with a permanent redirect.
Route::permanentRedirect('/testimonials', '/reviews');
Route::get('/contact', [FrontendController::class, 'contact'])->name('contact');
Route::get('/privacy-policy', [FrontendController::class, 'privacyPolicy'])->name('privacy-policy');
Route::get('/terms-of-service', [FrontendController::class, 'termsOfService'])->name('terms-of-service');

// Secure message submission with rate limiting and bot honeypot
Route::post('/messages', [FrontendController::class, 'storeMessage'])
    ->middleware('throttle:5,1')
    ->name('messages.store');

// Sitemap XML for SEO
Route::get('/sitemap.xml', [FrontendController::class, 'sitemap'])->name('sitemap');
// A plain-text guide to the site for AI assistants (ChatGPT, Perplexity, Google AI): llmstxt.org
Route::get('/llms.txt', \App\Http\Controllers\LlmsController::class)->name('llms');

Route::get('/robots.txt', fn () => response(\App\Support\SiteSchema::robots(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']))
    ->name('robots');

// Cache clearing lives in Admin > System > Cache (POST, permission system.cache).
Route::get('/clear', fn () => redirect('/admin/system/cache'))->middleware('auth');


/*
|--------------------------------------------------------------------------
| Admin Authentication Routes
|--------------------------------------------------------------------------
*/
// Login redirect fallback for standard Laravel auth middleware
Route::get('/login', fn() => redirect()->route('admin.login'))->name('login');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/forgot-password', [\App\Http\Controllers\Admin\PasswordResetController::class, 'showRequest'])->name('password.request');
    Route::post('/forgot-password', [\App\Http\Controllers\Admin\PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [\App\Http\Controllers\Admin\PasswordResetController::class, 'showReset'])->middleware('throttle:20,1')->name('password.reset');
    Route::post('/reset-password', [\App\Http\Controllers\Admin\PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Admin (every section is gated by a permission; Super Admin passes all gates)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', \App\Http\Middleware\EnsureUserIsActive::class])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Bulk delete for every list (each item uses that list's own delete rules and permission).
    Route::post('/bulk/{resource}/delete', [\App\Http\Controllers\Admin\BulkController::class, 'destroy'])->middleware('throttle:20,1')->name('bulk.delete');
    Route::get('/checklist', [DashboardController::class, 'checklist'])->middleware('can:settings.view')->name('checklist');

    // My account, notifications and autosave: every signed-in user
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::post('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');

    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/drafts/{type}/{record?}', [DraftController::class, 'show'])->whereNumber('record')->name('drafts.show');
    Route::put('/drafts/{type}/{record?}', [DraftController::class, 'store'])->whereNumber('record')->middleware('throttle:120,1')->name('drafts.store');
    Route::delete('/drafts/{type}/{record?}', [DraftController::class, 'destroy'])->whereNumber('record')->name('drafts.destroy');

    Route::get('/content-check/title', [ContentCheckController::class, 'title'])->middleware('throttle:120,1')->name('content-check.title');
    Route::post('/content-check/quality', [ContentCheckController::class, 'quality'])->middleware('throttle:60,1')->name('content-check.quality');

    // ---------------- Content ----------------
    // Articles: writers see their own; publishing needs articles.publish
    Route::middleware('can:articles.create')->group(function () {
        Route::get('/blogs', [BlogController::class, 'index'])->name('blogs.index');
        Route::post('/blogs', [BlogController::class, 'store'])->name('blogs.store');
        Route::post('/blogs/{blog}', [BlogController::class, 'update'])->name('blogs.update');
        Route::delete('/blogs/{blog}', [BlogController::class, 'destroy'])->name('blogs.destroy');
    });
    Route::middleware('can:articles.publish')->group(function () {
        Route::post('/blogs/{blog}/approve', [BlogController::class, 'approve'])->name('blogs.approve');
        Route::post('/blogs/{blog}/reject', [BlogController::class, 'reject'])->name('blogs.reject');
        Route::post('/revisions/{revision}/approve', [BlogController::class, 'approveRevision'])->name('revisions.approve');
        Route::post('/revisions/{revision}/reject', [BlogController::class, 'rejectRevision'])->name('revisions.reject');
    });
    Route::post('/blogs-bulk/assign-service', [BlogController::class, 'bulkAssignService'])->middleware('can:articles.edit_all')->name('blogs.bulk-service');

    Route::get('/services', [ServiceController::class, 'index'])->middleware('can:services.view')->name('services.index');
    Route::post('/services', [ServiceController::class, 'store'])->middleware('can:services.create')->name('services.store');
    Route::post('/services/{id}', [ServiceController::class, 'update'])->middleware('can:services.edit')->name('services.update');
    Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->middleware('can:services.delete')->name('services.destroy');

    Route::get('/service-categories', [ServiceCategoryController::class, 'index'])->middleware('can:categories.view')->name('service-categories.index');
    Route::post('/service-categories', [ServiceCategoryController::class, 'store'])->middleware('can:categories.create')->name('service-categories.store');
    Route::post('/service-categories/{category}', [ServiceCategoryController::class, 'update'])->middleware('can:categories.edit')->name('service-categories.update');
    Route::delete('/service-categories/{category}', [ServiceCategoryController::class, 'destroy'])->middleware('can:categories.delete')->name('service-categories.destroy');

    Route::get('/locations', [LocationController::class, 'index'])->middleware('can:locations.view')->name('locations.index');
    Route::post('/locations', [LocationController::class, 'store'])->middleware('can:locations.create')->name('locations.store');
    Route::post('/locations/{location}', [LocationController::class, 'update'])->middleware('can:locations.edit')->name('locations.update');
    Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->middleware('can:locations.delete')->name('locations.destroy');

    Route::get('/pricing', [PriceController::class, 'index'])->middleware('can:pricing.view')->name('pricing.index');
    Route::post('/pricing', [PriceController::class, 'store'])->middleware('can:pricing.create')->name('pricing.store');
    Route::post('/pricing/reviewed', [PriceController::class, 'markReviewed'])->middleware('can:pricing.edit')->name('pricing.reviewed');
    Route::put('/pricing/{price}', [PriceController::class, 'update'])->middleware('can:pricing.edit')->name('pricing.update');
    Route::delete('/pricing/{price}', [PriceController::class, 'destroy'])->middleware('can:pricing.delete')->name('pricing.destroy');

    Route::get('/faqs', [FaqController::class, 'index'])->middleware('can:faqs.view')->name('faqs.index');
    Route::post('/faqs', [FaqController::class, 'store'])->middleware('can:faqs.create')->name('faqs.store');
    Route::put('/faqs/{faq}', [FaqController::class, 'update'])->middleware('can:faqs.edit')->name('faqs.update');
    Route::delete('/faqs/{faq}', [FaqController::class, 'destroy'])->middleware('can:faqs.delete')->name('faqs.destroy');

    Route::get('/projects', [ProjectController::class, 'index'])->middleware('can:projects.view')->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('can:projects.create')->name('projects.store');
    Route::post('/projects/{id}', [ProjectController::class, 'update'])->middleware('can:projects.edit')->name('projects.update');
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy'])->middleware('can:projects.delete')->name('projects.destroy');

    Route::permanentRedirect('/testimonials', '/admin/reviews');
    Route::get('/reviews', [ReviewController::class, 'index'])->middleware('can:reviews.view')->name('reviews.index');
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('can:reviews.create')->name('reviews.store');
    Route::post('/reviews/google', [ReviewController::class, 'saveGoogle'])->middleware('can:reviews.google')->name('reviews.google');
    Route::post('/reviews/imported/{googleReview}/toggle', [ReviewController::class, 'toggleGoogle'])->middleware('can:reviews.edit')->name('reviews.imported.toggle');
    Route::post('/reviews/google/refresh', [ReviewController::class, 'refreshGoogle'])->middleware(['can:reviews.view', 'throttle:10,1'])->name('reviews.google.refresh');
    Route::post('/reviews/{review}', [ReviewController::class, 'update'])->middleware('can:reviews.edit')->name('reviews.update');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->middleware('can:reviews.delete')->name('reviews.destroy');

    // ---------------- Website ----------------
    Route::middleware('can:homepage.view')->group(function () {
        Route::get('/hero', [HeroController::class, 'index'])->name('hero.index');
        Route::get('/home-static', [HomeStaticController::class, 'index'])->name('home-static.index');
        Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
        Route::get('/website-text', [\App\Http\Controllers\Admin\WebsiteTextController::class, 'index'])->name('website-text.index');
    });
    Route::middleware('can:homepage.edit')->group(function () {
        Route::post('/hero', [HeroController::class, 'store'])->name('hero.store');
        Route::delete('/hero/extra', [HeroController::class, 'destroyExtra'])->name('hero.destroy-extra');
        Route::post('/home-static', [HomeStaticController::class, 'update'])->name('home-static.update');
        Route::post('/home-static/counters', [HomeStaticController::class, 'storeCounter'])->name('home-static.counter.store');
        Route::delete('/home-static/counters/{id}', [HomeStaticController::class, 'destroyCounter'])->name('home-static.counter.destroy');
        Route::post('/home-static/skills', [HomeStaticController::class, 'storeSkill'])->name('home-static.skill.store');
        Route::delete('/home-static/skills/{id}', [HomeStaticController::class, 'destroySkill'])->name('home-static.skill.destroy');
        Route::post('/website-text', [\App\Http\Controllers\Admin\WebsiteTextController::class, 'update'])->name('website-text.update');
        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::post('/partners/{id}', [PartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{id}', [PartnerController::class, 'destroy'])->name('partners.destroy');
    });

    Route::get('/about', [AboutController::class, 'index'])->middleware('can:about.view')->name('about.index');
    Route::post('/about', [AboutController::class, 'update'])->middleware('can:about.edit')->name('about.update');

    Route::get('/breadcrumbs', [BreadcrumbController::class, 'index'])->middleware('can:banners.view')->name('breadcrumbs.index');
    Route::post('/breadcrumbs', [BreadcrumbController::class, 'update'])->middleware('can:banners.edit')->name('breadcrumbs.update');

    Route::get('/media', [MediaController::class, 'index'])->middleware('can:media.view')->name('media.index');
    Route::get('/media/browse', [MediaController::class, 'browse'])->middleware('can:media.view')->name('media.browse');
    Route::post('/media', [MediaController::class, 'store'])->middleware(['can:media.create', 'throttle:60,1'])->name('media.store');
    Route::post('/media/alt', [MediaController::class, 'updateAlt'])->middleware('can:media.edit')->name('media.alt');
    Route::post('/media/delete', [MediaController::class, 'destroy'])->middleware('can:media.delete')->name('media.destroy');
    Route::post('/media/folders', [MediaController::class, 'createFolder'])->middleware('can:media.create')->name('media.folders.create');
    Route::post('/media/folders/rename', [MediaController::class, 'renameFolder'])->middleware('can:media.edit')->name('media.folders.rename');
    Route::post('/media/folders/delete', [MediaController::class, 'deleteFolder'])->middleware('can:media.delete')->name('media.folders.delete');
    Route::post('/media/transfer', [MediaController::class, 'transfer'])->middleware('can:media.view')->name('media.transfer');
    Route::get('/media/download', [MediaController::class, 'download'])->middleware('can:media.view')->name('media.download');
    Route::post('/media/download-zip', [MediaController::class, 'downloadZip'])->middleware(['can:media.view', 'throttle:10,1'])->name('media.download-zip');

    // ---------------- SEO ----------------
    Route::get('/seo/health', [SeoHealthController::class, 'index'])->middleware('can:seo_health.view')->name('seo.health');

    Route::get('/page-seo', [PageSeoController::class, 'index'])->middleware('can:page_seo.view')->name('page-seo.index');
    Route::put('/page-seo/{key}', [PageSeoController::class, 'update'])->middleware('can:page_seo.edit')->name('page-seo.update');

    Route::get('/redirects', [RedirectController::class, 'index'])->middleware('can:redirects.view')->name('redirects.index');
    Route::get('/redirects/export', [RedirectController::class, 'export'])->middleware('can:redirects.view')->name('redirects.export');
    Route::post('/redirects', [RedirectController::class, 'store'])->middleware('can:redirects.create')->name('redirects.store');
    Route::post('/redirects/import', [RedirectController::class, 'import'])->middleware('can:redirects.create')->name('redirects.import');
    Route::put('/redirects/{redirect}', [RedirectController::class, 'update'])->middleware('can:redirects.edit')->name('redirects.update');
    Route::delete('/redirects/{redirect}', [RedirectController::class, 'destroy'])->middleware('can:redirects.delete')->name('redirects.destroy');
    Route::post('/not-found/{log}/resolve', [RedirectController::class, 'resolveNotFound'])->middleware('can:redirects.edit')->name('not-found.resolve');

    Route::get('/seo/settings', [SeoSettingController::class, 'index'])->middleware('can:seo_settings.view')->name('seo.settings');
    Route::post('/seo/settings', [SeoSettingController::class, 'update'])->middleware('can:seo_settings.edit')->name('seo.settings.update');

    // ---------------- Leads ----------------
    Route::get('/messages', [MessageController::class, 'index'])->middleware('can:enquiries.view')->name('messages.index');
    Route::post('/messages/{id}/read', [MessageController::class, 'markAsRead'])->middleware('can:enquiries.edit')->name('messages.read');
    Route::delete('/messages/{id}', [MessageController::class, 'destroy'])->middleware('can:enquiries.delete')->name('messages.destroy');

    // ---------------- Administration ----------------
    Route::middleware('can:settings.view')->group(function () {
        Route::get('/settings', fn () => redirect()->route('admin.settings.contact'));
        Route::get('/settings/contact', [SettingController::class, 'contactIndex'])->name('settings.contact');
        Route::get('/settings/footer', [SettingController::class, 'footerIndex'])->name('settings.footer');
        Route::get('/settings/business', [SeoSettingController::class, 'business'])->name('settings.business');
    });
    Route::middleware('can:settings.edit')->group(function () {
        Route::post('/settings/business', [SeoSettingController::class, 'updateBusiness'])->name('settings.business.update');
        Route::post('/settings/contact', [SettingController::class, 'contactUpdate'])->name('settings.contact.update');
        Route::post('/settings/footer', [SettingController::class, 'footerUpdate'])->name('settings.footer.update');
    });

    Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:users.create')->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:users.edit')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('can:roles.view')->name('roles.index');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:roles.create')->name('roles.store');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('can:roles.edit')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.delete')->name('roles.destroy');

    Route::prefix('insights')->name('insights.')->controller(\App\Http\Controllers\Admin\InsightsController::class)->group(function () {
        Route::middleware('can:analytics.view')->group(function () {
            Route::get('/', 'reports')->name('reports');
            Route::get('/indexing', 'indexing')->name('indexing');
            Route::post('/indexing/inspect', 'inspect')->middleware('throttle:30,1')->name('indexing.inspect');
            Route::post('/indexing/batch', 'inspectBatch')->middleware('throttle:3,10')->name('indexing.batch');
        });
        Route::middleware('can:analytics.connect')->group(function () {
            Route::get('/google', 'google')->name('google');
            Route::post('/google/client', 'saveClient')->name('google.client');
            Route::post('/google/connect', 'connect')->name('google.connect');
            Route::get('/google/callback', 'callback')->name('google.callback');
            Route::post('/google/disconnect', 'disconnect')->name('google.disconnect');
            Route::post('/google/properties', 'saveProperties')->name('google.properties');
            Route::post('/google/reviews/sync', 'syncReviews')->middleware('throttle:6,1')->name('google.reviews.sync');
            Route::post('/google/sync', 'saveSync')->name('google.sync');
            Route::post('/google/sync/{task}/run', 'runSync')->whereIn('task', ['reports', 'index', 'reviews'])->middleware('throttle:10,1')->name('google.sync.run');
        });
    });

    Route::prefix('system')->name('system.')->group(function () {
        Route::get('/cache', [CacheController::class, 'index'])->middleware('can:system.cache')->name('cache');
        Route::middleware('can:system.settings')->group(function () {
            Route::get('/settings', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'index'])->name('settings');
            Route::post('/settings', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'update'])->name('settings.update');
            Route::get('/settings/preview', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'preview'])->name('settings.preview');
            Route::post('/settings/debug', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'debug'])->name('settings.debug');
            Route::post('/settings/test-mail', [\App\Http\Controllers\Admin\SystemSettingsController::class, 'testMail'])->middleware('throttle:5,1')->name('settings.test-mail');
        });
        Route::post('/cache/clear', [CacheController::class, 'clear'])->middleware(['can:system.cache', 'throttle:10,1'])->name('cache.clear');

        Route::middleware('can:system.update')->group(function () {
            Route::get('/update', [SystemUpdateController::class, 'index'])->name('update');
            Route::post('/update/check', [SystemUpdateController::class, 'check'])->middleware('throttle:10,1')->name('update.check');
            Route::post('/update/github', [SystemUpdateController::class, 'saveGithub'])->name('update.github');
            Route::post('/update/deploy', [SystemUpdateController::class, 'deploy'])->middleware('throttle:3,10')->name('update.deploy');
            Route::get('/update/logs/latest', [SystemUpdateController::class, 'latest'])->name('update.latest');
            Route::get('/update/logs/{log}', [SystemUpdateController::class, 'log'])->name('update.log');
        });
    });
});
