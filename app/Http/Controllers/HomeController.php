<?php

namespace App\Http\Controllers;

use App\Services\ListingGenerator;
use App\Services\Paddle;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * The public landing page, including the pricing Paddle's website
     * review asks for.
     */
    public function __invoke(Paddle $paddle): Response
    {
        return Inertia::render('welcome', [
            'signupCredits' => (int) config('vitrin.credits.signup'),
            'costs' => ListingGenerator::prices(),
            'packs' => $paddle->packs(),
        ]);
    }
}
