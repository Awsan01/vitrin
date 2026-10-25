<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Listing;
use App\Models\ListingCopy;
use App\Services\ListingGenerator;
use Illuminate\Http\RedirectResponse;

class ListingCopyController extends Controller
{
    public function regenerate(Listing $listing, ListingCopy $copy, ListingGenerator $generator): RedirectResponse
    {
        try {
            $started = $listing->product_facts !== null && $generator->regenerateCopy($copy);
        } catch (InsufficientCreditsException $e) {
            return $this->toast('error', $e->getMessage());
        }

        if (! $started) {
            return $this->toast('warning', 'This listing is still being written.');
        }

        return $this->toast('success', "Rewriting the {$copy->marketplace->label()} listing in {$copy->language->englishName()}.");
    }
}
