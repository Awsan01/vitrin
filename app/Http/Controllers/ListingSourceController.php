<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingSourceController extends Controller
{
    /**
     * The seller's original photo (resized), only for its owner.
     */
    public function __invoke(Listing $listing): StreamedResponse
    {
        $disk = Storage::disk(Listing::disk());

        abort_unless($disk->exists($listing->source_path), 404);

        return $disk->response($listing->source_path, null, [
            'Cache-Control' => 'private, max-age=604800, immutable',
        ]);
    }
}
