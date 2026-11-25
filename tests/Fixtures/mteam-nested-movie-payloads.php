<?php

return [
    [
        'name' => 'IMDb poster',
        'imdb' => [
            'image' => [
                'url' => 'https://images.example.test/imdb-poster.jpg',
            ],
        ],
    ],
    [
        'name' => 'Douban cover',
        'douban' => [
            'cover' => [
                'large' => 'https://images.example.test/douban-cover.jpg',
            ],
        ],
    ],
    [
        'name' => 'Media info poster',
        'mediaInfo' => [
            'poster' => [
                'src' => 'https://images.example.test/media-info-poster.jpg',
            ],
        ],
    ],
    [
        'name' => 'Snake case media image',
        'media_info' => [
            'image' => [
                'original' => 'https://images.example.test/media-info-image.jpg',
            ],
        ],
    ],
    [
        'name' => 'Unrelated URL',
        'metadata' => [
            'image' => 'https://images.example.test/not-a-poster.jpg',
        ],
    ],
    [
        'name' => 'Image list poster',
        'imageList' => [
            'https://images.example.test/image-list-poster.jpg',
        ],
    ],
    [
        'name' => 'Image list skips invalid entries',
        'imageList' => [
            '//images.example.test/protocol-relative.jpg',
            'javascript:alert(1)',
            ['url' => 'https://images.example.test/not-a-direct-image-list-url.jpg'],
            'https://images.example.test/image-list-later-poster.jpg',
        ],
    ],
    [
        'name' => 'Image list rejects invalid entries',
        'imageList' => [
            '//images.example.test/protocol-relative.jpg',
            'data:image/svg+xml,<svg></svg>',
            ['url' => 'https://images.example.test/not-a-direct-image-list-url.jpg'],
        ],
    ],
    [
        'name' => 'Top-level poster takes priority over image list',
        'posterUrl' => 'https://images.example.test/explicit-poster.jpg',
        'imageList' => [
            'https://images.example.test/image-list-poster-that-must-not-win.jpg',
        ],
    ],
];
