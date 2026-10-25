<?php

namespace App\Console\Commands;

use App\Models\CreditTransaction;
use App\Models\User;
use App\Services\CreditLedger;
use Illuminate\Console\Command;

class GrantCredits extends Command
{
    /**
     * @var string
     */
    protected $signature = 'credits:grant
        {email : The account to top up}
        {amount : Number of credits to add}
        {--note=Manual top-up : Shown in the user\'s credit history}';

    /**
     * @var string
     */
    protected $description = 'Add credits to an account (until online payments are connected)';

    public function handle(CreditLedger $ledger): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $amount = (int) $this->argument('amount');

        if (! $user) {
            $this->error('No account uses that email address.');

            return self::FAILURE;
        }

        if ($amount <= 0) {
            $this->error('The amount must be a positive number.');

            return self::FAILURE;
        }

        $ledger->add($user, $amount, CreditTransaction::GRANT, $this->option('note'));

        $this->info("Added {$amount} credits to {$user->email}. New balance: {$user->credits}.");

        return self::SUCCESS;
    }
}
