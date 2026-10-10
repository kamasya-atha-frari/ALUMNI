<?php
/**
 * Application Configuration
 * Istanbul University MIS Alumni Portal
 */

return [
    'app' => [
        'name' => 'Istanbul University MIS Alumni Portal',
        'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost:8080',
        'env' => getenv('APP_ENV') ?: 'development', // 'development' or 'production'
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'iu_mis_alumni',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    'institution' => [
        'university' => 'Istanbul University',
        'faculty' => 'Faculty of Economics',
        'department' => 'Management Information Systems (YBS)',
        'contact_email' => 'mis-alumni@istanbul.edu.tr',
    ]
];
