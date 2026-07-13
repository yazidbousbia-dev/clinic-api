<?php
 
return [
 
    'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie'],
 
    'allowed_methods' => ['*'],
 
    'allowed_origins' => [
        'https://clinic-dashboard-vert.vercel.app',
        'http://localhost:3000',
    ],
 
    // Matches ANY Vercel preview URL for this project, e.g.:
    // https://clinic-dashboard-kqg9xlm4i-yazidbousbia-devs-projects.vercel.app
    'allowed_origins_patterns' => [
        '#^https://clinic-dashboard.*\.vercel\.app$#',
    ],
 
    'allowed_headers' => ['*'],
 
    'exposed_headers' => [],
 
    'max_age' => 0,
 
    'supports_credentials' => true,
 
];
