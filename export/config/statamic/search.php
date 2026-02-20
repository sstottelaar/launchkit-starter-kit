<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default search index
    |--------------------------------------------------------------------------
    |
    | This option controls the search index that gets queried when performing
    | search functions without explicitly selecting another index.
    |
    */

    'default' => env('STATAMIC_DEFAULT_SEARCH_INDEX', 'site'),

    /*
    |--------------------------------------------------------------------------
    | Search Indexes
    |--------------------------------------------------------------------------
    |
    | Here you can define all of the available search indexes.
    |
    */

    'indexes' => [

        'site' => [
            'driver' => 'local',
            'searchables' => ['collection:pages', 'collection:news'],
            'fields' => ['title', 'content'],
            'transformers' => [
                'content' => \App\Search\ContentTransformer::class,
            ],
            'filter' => function ($item) {
                return $item->status() === 'published';
            },
            'property_weights' => [
                'title' => 10,
                'content' => 2,
            ],
            'min_characters' => 2,
            'use_stemming' => true,
        ],

        'cp' => [
            'driver' => 'local',
            'searchables' => ['content', 'users', 'addons'],
            'fields' => ['title'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Defaults
    |--------------------------------------------------------------------------
    |
    | Here you can specify default configuration to be applied to all indexes
    | that use the corresponding driver. For instance, if you have two
    | indexes that use the "local" driver, both of them can have the
    | same base configuration. You may override for each index.
    |
    */

    'drivers' => [

        'local' => [
            'path' => storage_path('statamic/search'),
        ],

        'algolia' => [
            'credentials' => [
                'id' => env('ALGOLIA_APP_ID', ''),
                'secret' => env('ALGOLIA_SECRET', ''),
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Search Defaults
    |--------------------------------------------------------------------------
    |
    | Here you can specify default configuration to be applied to all indexes
    | regardless of the driver. You can override these per driver or per index.
    |
    */

    'defaults' => [
        'fields' => ['title'],
    ],

];
