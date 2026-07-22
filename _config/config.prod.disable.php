<?php
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
return [
    'site' => [
        'domain'        => $host,
        'admin_domain'  => 'servicedomain.com', // admin only works here
        'admin_path'    => '/admin',
    ],
    'admin' => [
        'title'     => 'ParkPod',
    ],
    'database' => [
        'path' => '/db/db.sqlite'
    ]
];
