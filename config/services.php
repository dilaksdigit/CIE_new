<?php

// SOURCE: BUILD§Step2 — Python worker base URL for vector similarity (G4); env override for Docker/local
return [
    'python_worker' => [
        'url' => env('PYTHON_WORKER_URL', 'http://python-worker:8000'),
    ],
];
