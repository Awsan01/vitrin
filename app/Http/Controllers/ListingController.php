<?php

namespace App\Http\Controllers;

use App\Enums\Language;
use App\Enums\Marketplace;
use App\Enums\PhotoStyle;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\UnsupportedImageException;
use App\Http\Requests\StoreListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Services\ListingGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ListingController extends Controller
{
    /**
     * The upload form.
     */
    public function create(): Response
    {
        return Inertia::render('listings/create', [
            'marketplaces' => Marketplace::options(),
            'languages' => Language::options(),
            'photoStyles' => PhotoStyle::options(),
            'costs' => ListingGenerator::prices(),
            'uploadLimits' => [
                'max_mb' => (int) floor(config('vitrin.uploads.max_kb') / 1024),
                'min_dimension' => config('vitrin.uploads.min_dimension'),
            ],
        ]);
    }

    public function store(StoreListingRequest $request, ListingGenerator $generator): RedirectResponse
    {
        try {
            $listing = $generator->start($request->user(), $request->toNewListing());
        } catch (InsufficientCreditsException $e) {
            return back()->withErrors(['credits' => $e->getMessage()]);
        } catch (UnsupportedImageException $e) {
            // A file can pass validation and still fail to decode (a truncated
            // JPEG, for example). Nothing has been charged at this point.
            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Generating your listing. This usually takes under a minute.']);

        return to_route('listings.show', $listing);
    }

    /**
     * The results page. While items are still generating, the page polls
     * this endpoint for fresh props.
     */
    public function show(Request $request, Listing $listing): Response
    {
        $listing->load(['copies', 'photos']);

        return Inertia::render('listings/show', [
            'listing' => (new ListingResource($listing))->resolve($request),
            'costs' => ListingGenerator::prices(),
        ]);
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        $listing->deleteWithFiles();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing deleted.']);

        return to_route('dashboard');
    }
}
