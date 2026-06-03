<?php
// SOURCE: CLAUDE.md — GSC/GA4 sync CA bundle fix
return [
    'curl_options' => [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => env('CURL_CA_BUNDLE', '/etc/ssl/certs/ca-certificates.crt'),
    ],
];
