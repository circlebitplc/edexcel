<?php
declare(strict_types=1);

/**
 * Crontab entry for 15-minute class reminders.
 * Implementation lives in tools/ so it still runs if this folder is not writable.
 */
require __DIR__ . '/../tools/classroom_reminders.php';
