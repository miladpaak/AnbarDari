<?php
return [
    'app_name' => 'انبارداری آنلاین آکا',
    'base_url' => '',
    'timezone' => 'Asia/Tehran',
    'db' => [
        'host' => 'localhost',
        'name' => 'your_database_name',
        'user' => 'your_database_user',
        'pass' => 'your_database_password',
        'charset' => 'utf8mb4',
    ],
    // Optional: fill this section to import products and WooCommerce sales from WordPress.
    'wordpress_db' => [
        'host' => 'localhost',
        'name' => '',
        'user' => '',
        'pass' => '',
        'charset' => 'utf8mb4',
        'prefix' => 'wp_',
    ],
    'security' => [
        'session_name' => 'AKHACO_DP_SESSION',
    ],
];
