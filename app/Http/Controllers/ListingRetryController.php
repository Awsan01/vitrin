<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Listing;
use App\Services\ListingGenerator;
use Illuminate\Http\RedirectResponse;

class ListingRetryController extends Controller
{
    /**
     * Run a listing again, with the same photo and choices, after its photo
     * couldn't be analyzed.
     */
    public function __invoke(Listing $listing, ListingGenerator $generator): RedirectResponse
    {
        if (! $listing->canRetry()) {
            return $this->toast('warning', 'Only a listing that failed can be tried again.');
        }

        try {
            $started = $generator->retry($listing);
        } catch (InsufficientCreditsException $e) {
            return $this->toast('error', $e->getMessage());
        }

        if (! $started) {
            return $this->toast('warning', 'This listing is already being generated.');
        }

        return $this->toast('success', 'Trying again with the same photo and choices.');
    }
}
