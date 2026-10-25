<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCreditsException;
use App\Models\Listing;
use App\Models\ListingPhoto;
use App\Services\ListingGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingPhotoController extends Controller
{
    /**
     * Show or download a generated photo.
     */
    public function show(Request $request, Listing $listing, ListingPhoto $photo): StreamedResponse
    {
        $disk = Storage::disk(Listing::disk());

        abort_unless($photo->path && $disk->exists($photo->path), 404);

        $headers = ['Cache-Control' => 'private, max-age=604800, immutable'];

        return $request->boolean('download')
            ? $disk->download($photo->path, $photo->downloadName(), $headers)
            : $disk->response($photo->path, null, $headers);
    }

    public function regenerate(Listing $listing, ListingPhoto $photo, ListingGenerator $generator): RedirectResponse
    {
        try {
            $started = $listing->product_facts !== null && $generator->regeneratePhoto($photo);
        } catch (InsufficientCreditsException $e) {
            return $this->toast('error', $e->getMessage());
        }

        if (! $started) {
            return $this->toast('warning', 'This photo is still being made.');
        }

        return $this->toast('success', "Making the {$photo->style->label()} photo again.");
    }
}
