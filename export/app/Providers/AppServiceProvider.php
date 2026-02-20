<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Statamic\Facades\CP\Nav;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Nav::extend(function ($nav) {
            $seoGlobal = \Statamic\Facades\GlobalSet::findByHandle('seo');
            if ($seoGlobal) {
                $variables = $seoGlobal->inSelectedSite() ?? $seoGlobal->inDefaultSite();
                $nav->content('SEO')
                    ->url($variables->editUrl())
                    ->icon('search-magnifying-glass')
                    ->can('edit seo globals');
            }
        });
    }
}
