<?php

use Illuminate\Support\Facades\Schedule;

/*
| Shared hosting (like Hostinger) has no process supervisor to keep a queue
| worker alive, so the scheduler starts one every minute from a single cron
| entry. It exits once the queue is empty (or after ~55 seconds between jobs),
| and withoutOverlapping() stops two workers from running at the same time.
|
| On a VPS, run `php artisan queue:work` under Supervisor instead and remove
| this entry.
*/
Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('queue:prune-failed --hours=168')->daily();

// Removes checkouts that were opened but never paid (CreditPurchase::prunable).
Schedule::command('model:prune')->daily();
