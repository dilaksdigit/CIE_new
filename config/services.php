<?php

// SOURCE: BUILD§Step2 — Python worker base URL for vector similarity (G4); env override for Docker/local
// local_llm / openai — read via config() in Artisan (e.g. cie:vector-retry-process) so values work with config:cache.
return [
    'python_worker' => [
        'url' => env('PYTHON_WORKER_URL', env('PYTHON_API_URL', 'http://python-worker:8000')),
        'internal_service_token' => env('INTERNAL_SERVICE_TOKEN', ''),
        'vector_similarity_timeout_seconds' => (int) env('PYTHON_VECTOR_SIMILARITY_TIMEOUT_SECONDS', 60),
    ],
    'local_llm' => [
        'mode' => env('LOCAL_LLM_MODE', ''),
        'base_url' => env('LOCAL_LLM_BASE_URL', ''),
    ],
    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],
];
