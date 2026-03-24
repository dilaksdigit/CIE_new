<?php

// SOURCE: CIE_Master_Developer_Build_Spec.docx §5 — operational config; thresholds remain in business_rules table
return [
    'business_rules_cache_ttl' => (int) env('BUSINESS_RULES_CACHE_TTL', 300),
];
