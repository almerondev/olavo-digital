<?php

return [
    'live' => [
        // simulated: sem encoder nem conta de vídeo. O provedor real entra como nova implementação.
        'driver' => env('LIVE_DRIVER', 'simulated'),
        'webhook_secret' => env('LIVE_WEBHOOK_SECRET'),
    ],

    'uploads' => [
        // local: arquivos em disco, com URLs assinadas pelo Laravel. s3: R2, com URLs pré-assinadas.
        'driver' => env('UPLOADS_DRIVER', 'local'),
        'private_disk' => 'uploads_private',
        'public_disk' => 'uploads_public',
        'url_ttl_minutes' => 10,
        'image_widths' => [480, 960, 1920],

        // Cada classe define o limite em MB e os formatos aceitos, conferidos pelo conteúdo real do arquivo.
        'classes' => [
            'image' => [
                'max_mb' => 15,
                'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
                'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            ],
            'pdf' => [
                'max_mb' => 100,
                'extensions' => ['pdf'],
                'mimes' => ['application/pdf'],
            ],
            'document' => [
                'max_mb' => 100,
                'extensions' => ['doc', 'docx'],
                'mimes' => [
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ],
            ],
            'presentation' => [
                'max_mb' => 100,
                'extensions' => ['ppt', 'pptx'],
                'mimes' => [
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                ],
            ],
            'spreadsheet' => [
                'max_mb' => 100,
                'extensions' => ['xls', 'xlsx'],
                'mimes' => [
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ],
            ],
            'audio' => [
                'max_mb' => 300,
                'extensions' => ['mp3', 'm4a', 'ogg', 'wav'],
                'mimes' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'video/mp4', 'audio/ogg', 'audio/wav', 'audio/x-wav'],
            ],
            'subtitle' => [
                'max_mb' => 5,
                'extensions' => ['vtt'],
                'mimes' => ['text/vtt'],
            ],
            'video' => [
                'max_mb' => 5120,
                'extensions' => ['mp4', 'webm'],
                'mimes' => ['video/mp4', 'video/webm'],
            ],
        ],
    ],
];
