<?php

return [
    'api_path' => [
        'include' => ['api/v1', 'sanctum/csrf-cookie'],
    ],
    'export_path' => 'docs/openapi.json',
    'info' => [
        'version' => '1.0.0',
        'description' => 'CourseCI backend API for the Vue SPA and Android application.',
    ],
    'ui' => [
        'title' => 'CourseCI API',
    ],
    'security_strategy' => null,
];
