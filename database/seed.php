<?php
/** Legacy sample-data entry point, intentionally disabled after approved cleanup. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Sample seeding is disabled.');
}
fwrite(STDERR, "Sample seeding is disabled. Use the separately stored UCI demonstration dataset.\n");
exit(1);
