<?php

namespace App\Modules\PlatformAdmin\Providers;

use App\Modules\PlatformAdmin\Models\Page;
use App\Modules\PlatformAdmin\Models\Setting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Boots the Super Admin area (Phase 28) and shares platform settings and
 * footer CMS links with the public layout.
 */
class PlatformAdminServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        $this->loadViewsFrom(__DIR__.'/../Views', 'platform-admin');

        Route::middleware('web')->group(__DIR__.'/../routes.php');

        View::composer('layouts.public', function ($view): void {
            $view->with([
                'announcement' => Setting::get('announcement'),
                'supportEmail' => Setting::get('support_email'),
                'supportPhone' => Setting::get('support_phone'),
                'footerPages' => Schema::hasTable('cms_pages')
                    ? Page::query()->where('is_published', true)->where('in_footer', true)->orderBy('title')->get(['slug', 'title'])
                    : collect(),
            ]);
        });
    }
}
