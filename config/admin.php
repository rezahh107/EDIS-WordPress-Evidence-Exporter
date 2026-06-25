<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

return [
    'assets' => [
        [
            'dependencies' => [],
            'handle' => 'edis-evidence-admin',
            'path' => 'assets/css/admin.css',
            'type' => 'css',
        ],
        [
            'dependencies' => [
                'wp-api-fetch',
                'wp-i18n',
            ],
            'handle' => 'edis-evidence-admin',
            'path' => 'assets/js/admin.js',
            'type' => 'js',
        ],
    ],
    'capability' => 'edis_export_evidence',
    'icon' => 'dashicons-media-archive',
    'menu_slug' => 'edis-evidence',
    'menu_title' => 'EDIS Evidence',
    'page_title' => 'EDIS Evidence',
    'pages' => [
        [
            'id' => 'overview',
            'menu_title' => 'Overview',
            'slug' => 'edis-evidence',
            'template' => 'templates/admin/overview.php',
            'title' => 'Overview',
        ],
        [
            'id' => 'create-export',
            'menu_title' => 'Create Export',
            'slug' => 'edis-evidence-create',
            'template' => 'templates/admin/create-export.php',
            'title' => 'Create Export',
        ],
        [
            'id' => 'data-sources',
            'menu_title' => 'Data Sources',
            'slug' => 'edis-evidence-sources',
            'template' => 'templates/admin/data-sources.php',
            'title' => 'Data Sources',
        ],
        [
            'id' => 'data-coverage',
            'menu_title' => 'Data Coverage',
            'slug' => 'edis-evidence-coverage',
            'template' => 'templates/admin/data-coverage.php',
            'title' => 'Data Coverage',
        ],
        [
            'id' => 'diagnostics',
            'menu_title' => 'Diagnostics',
            'slug' => 'edis-evidence-diagnostics',
            'template' => 'templates/admin/diagnostics.php',
            'title' => 'Diagnostics',
        ],
        [
            'id' => 'settings',
            'menu_title' => 'Settings',
            'slug' => 'edis-evidence-settings',
            'template' => 'templates/admin/settings.php',
            'title' => 'Settings',
        ],
        [
            'id' => 'help',
            'menu_title' => 'Help',
            'slug' => 'edis-evidence-help',
            'template' => 'templates/admin/help.php',
            'title' => 'Help',
        ],
    ],
    'position' => 81,
    'routes' => [
        [
            'method' => 'POST',
            'path' => '/export-preflight',
        ],
        [
            'method' => 'POST',
            'path' => '/export-jobs',
        ],
        [
            'method' => 'GET',
            'path' => '/export-jobs/{job_id}',
        ],
        [
            'method' => 'POST',
            'path' => '/export-jobs/{job_id}/advance',
        ],
        [
            'method' => 'POST',
            'path' => '/export-jobs/{job_id}/resume',
        ],
        [
            'method' => 'POST',
            'path' => '/export-jobs/{job_id}/retry',
        ],
        [
            'method' => 'POST',
            'path' => '/export-jobs/{job_id}/cancel',
        ],
        [
            'method' => 'GET',
            'path' => '/documents',
        ],
        [
            'method' => 'GET',
            'path' => '/diagnostics',
        ],
        [
            'method' => 'POST',
            'path' => '/diagnostics/worker-test',
        ],
        [
            'method' => 'POST',
            'path' => '/inspector-selections',
        ],
        [
            'method' => 'GET',
            'path' => '/site-health/storage',
        ],
    ],
];
