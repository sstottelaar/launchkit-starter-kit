<?php

namespace App\Providers;

use App\Listeners\GenerateSearchJson;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Statamic\Events\EntrySaved;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        EntrySaved::class => [
            // GenerateSearchJson::class,
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
