<?php

namespace App\Console\Commands;

use App\Services\CreditPurchases;
use App\Services\Paddle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReconcilePaddlePayments extends Command
{
    /**
     * @var string
     */
    protected $signature = 'paddle:reconcile';

    /**
     * @var string
     */
    protected $description = 'Add the credits for paid checkouts whose Paddle webhook never arrived';

    public function handle(Paddle $paddle, CreditPurchases $purchases): int
    {
        if (! $paddle->enabled()) {
            $this->components->info('Payments are off, so there is nothing to check.');

            return self::SUCCESS;
        }

        $completed = $purchases->reconcile();

        if ($completed > 0) {
            // Webhooks that don't arrive usually mean a wrong URL or secret.
            Log::warning("paddle:reconcile added credits that Paddle's webhook should have added. Check the webhook destination with php artisan paddle:check.", [
                'purchases' => $completed,
            ]);
        }

        $this->components->info($completed > 0
            ? "Added the credits for {$completed} paid ".Str::plural('purchase', $completed).'.'
            : 'No paid checkouts are waiting for credits.');

        return self::SUCCESS;
    }
}
