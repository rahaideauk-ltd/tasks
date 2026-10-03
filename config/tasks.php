<?php

return [
    // true: generated tasks go straight to the client; false: admin reviews drafts and publishes a round.
    'auto_publish' => (bool) env('TASKS_AUTO_PUBLISH', false),

    // Suggested kinds for project facts (dynamic fields). Admin and Claude may use any other kind too.
    // Labels: fact.<kind> in lang/*.json.
    'fact_kinds' => ['keyword', 'competitor', 'feature', 'audience', 'offer', 'location'],

    // Claude rewrites the project memory after each analysis; keep it within this size.
    'memory_max_words' => 800,
];
