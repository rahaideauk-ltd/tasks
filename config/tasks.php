<?php

return [
    // true: generated tasks go straight to the client; false: admin reviews drafts and publishes a round.
    'auto_publish' => (bool) env('TASKS_AUTO_PUBLISH', false),
];
