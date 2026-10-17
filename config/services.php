<?php
return [
    'muse' => ['base_url'=> env('MUSE_BASE_URL'), 'token'=> env('MUSE_API_TOKEN'), 'webhook_secret'=> env('MUSE_WEBHOOK_SECRET'), 'timeout'=> (int) env('MUSE_TIMEOUT', 15)],
];
