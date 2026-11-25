<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'azure_speech' => [
        'key' => env('AZURE_SPEECH_KEY'),
        'region' => env('AZURE_SPEECH_REGION'),
    ],

    'adult' => [
        'password' => env('ADULT_PASS'),
    ],

    'mteam' => [
        'endpoint' => env('MTEAM_API_ENDPOINT'),
        'key' => env('MTEAM_API_KEY'),
        'media_categories' => [
            'movies' => [
                'label' => '电影',
                'type' => 'movie',
                'path' => 'movies',
                'route' => 'movies.category',
                'child_route' => 'movies.child-category',
                'search' => [
                    'mode' => 'movie',
                ],
                'children' => [
                    'movies-hd' => ['label' => '电影-高清', 'type' => 'movie', 'ids' => ['419']],
                    'movies-bluray' => ['label' => '电影-蓝光', 'type' => 'movie', 'ids' => ['421']],
                    'movies-remux' => ['label' => '电影-Remux', 'type' => 'movie', 'ids' => ['439']],
                    'movies-dvd' => ['label' => '电影-DVD', 'type' => 'movie', 'ids' => ['420']],
                    'movies-sd' => ['label' => '电影-标清', 'type' => 'movie', 'ids' => ['401']],
                ],
            ],
            'tv' => [
                'label' => '电视剧',
                'type' => 'tv',
                'path' => 'tv-shows',
                'route' => 'tv-shows.category',
                'child_route' => 'tv-shows.child-category',
                'search' => [
                    'mode' => 'tvshow',
                ],
                'children' => [
                    'tv-hd' => ['label' => '影剧/综艺-高清', 'type' => 'tv', 'ids' => ['402']],
                    'tv-bluray' => ['label' => '影剧/综艺-蓝光', 'type' => 'tv', 'ids' => ['438']],
                    'documentaries' => ['label' => '教育纪录', 'type' => 'tv', 'ids' => ['404']],
                    'tv-dvd' => ['label' => '影剧/综艺-DVD', 'type' => 'tv', 'ids' => ['435']],
                    'tv-sd' => ['label' => '影剧/综艺-标清', 'type' => 'tv', 'ids' => ['403']],
                ],
            ],
            'animation' => [
                'label' => '动画',
                'type' => 'animation',
                'path' => 'animation',
                'route' => 'animation.category',
                'child_route' => 'animation.child-category',
                'children' => [
                    'animation-anime' => ['label' => '动漫', 'type' => 'animation', 'ids' => ['405']],
                    'animation-bluray' => ['label' => '动漫-蓝光', 'type' => 'animation', 'ids' => ['453']],
                ],
                'search' => [
                    'mode' => 'normal',
                ],
            ],
            'games' => [
                'label' => '游戏',
                'type' => 'game',
                'path' => 'games',
                'route' => 'games.category',
                'child_route' => 'games.child-category',
                'children' => [
                    'games-pc' => ['label' => 'PC游戏', 'type' => 'game', 'ids' => ['423']],
                    'games-tv' => ['label' => 'TV游戏', 'type' => 'game', 'ids' => ['448']],
                ],
                'search' => [
                    'mode' => 'normal',
                ],
            ],
            'adult' => [
                'label' => '成人',
                'type' => 'adult',
                'exclude_from_homepage' => true,
                'path' => 'adult',
                'route' => 'adult.category',
                'child_route' => 'adult.child-category',
                'request' => [
                    'mode' => 'adult',
                ],
                'search' => [
                    'mode' => 'adult',
                ],
                'children' => [
                    'adult-hd-uncensored' => ['label' => '高清无码', 'type' => 'adult', 'ids' => ['429']],
                    'adult-sd-uncensored' => ['label' => '标清无码', 'type' => 'adult', 'ids' => ['430']],
                    'adult-bluray-uncensored' => ['label' => '蓝光无码', 'type' => 'adult', 'ids' => ['432']],
                    'adult-dvd-uncensored' => ['label' => 'DVD无码', 'type' => 'adult', 'ids' => ['426']],
                    'adult-bluray-censored' => ['label' => '蓝光有码', 'type' => 'adult', 'ids' => ['431']],
                    'adult-hd-censored' => ['label' => '高清有码', 'type' => 'adult', 'ids' => ['410']],
                    'adult-sd-censored-424' => ['label' => '标清有码', 'type' => 'adult', 'ids' => ['424']],
                    'adult-sd-censored-437' => ['label' => '标清有码', 'type' => 'adult', 'ids' => ['437']],
                    'adult-animation' => ['label' => '动画', 'type' => 'adult', 'ids' => ['412']],
                ],
            ],
        ],
        'page_size' => (int) env('MTEAM_MOVIE_PAGE_SIZE', 100),
        'cache_ttl' => (int) env('MTEAM_MOVIE_CACHE_TTL', 86400),
        'homepage_category_limit' => 12,
        'refresh_cron' => env('MTEAM_MOVIE_REFRESH_CRON', '*/15 * * * *'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
