<?php

namespace App\Listeners;

use Statamic\Facades\Entry;
use Statamic\Events\EntrySaved;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateSearchJson
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EntrySaved $event)
    {
        $collections = ['pages'];

        $data = Entry::query()
            ->whereIn('collection', $collections)
            ->get()
            ->transform(function ($page) {
                return [
                    'title' => $page->title,
                    'url' => $page->url,
                    'blueprint' => $page->blueprint->handle,
                ];
            })
            ->toArray();

        file_put_contents(public_path() . '/search/data.json', json_encode($data));
    }
}
