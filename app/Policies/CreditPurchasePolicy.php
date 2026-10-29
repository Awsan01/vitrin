<?php

namespace App\Policies;

use App\Models\CreditPurchase;
use App\Models\User;

class CreditPurchasePolicy
{
    public function view(User $user, CreditPurchase $purchase): bool
    {
        return $purchase->user_id === $user->id;
    }
}
