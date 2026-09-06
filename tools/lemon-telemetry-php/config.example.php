<?php
/**
 * Copy to config.php on the server (config.php is gitignored).
 * 1. Set a long random telemetry_secret (same as app telemetry.local.json / TELEMETRY_SECRET)
 * 2. Set stats_password for the dashboard
 * 3. Ensure data/ is writable; enable PDO SQLite
 */
return [
    'telemetry_secret' => 'change-me-long-random-secret',
    'stats_password' => 'change-me-stats',
];
