<?php

namespace App\Listeners;

use Statamic\Facades\Entry;
use Statamic\Events\EntrySaved;
use Illuminate\Support\Facades\File;

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
            ->where('published', true)
            ->get()
            ->transform(function ($page) {
                return [
                    'title' => $page->title,
                    'url' => $page->url,
                    'blueprint' => $page->blueprint->handle,
                ];
            })
            ->toArray();

        $directory = public_path() . "/search";
        $file = $directory . '/data.json';

        File::ensureDirectoryExists($directory);
        File::put($file, json_encode($data));
    }
}
