<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingCardResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The seller's listings, newest first.
     */
    public function __invoke(Request $request): Response
    {
        $listings = $request->user()->listings()
            ->with([
                'copies:id,listing_id,marketplace,language,status',
                'photos:id,listing_id,style,status',
            ])
            ->latest()
            ->paginate(12);

        return Inertia::render('dashboard', [
            'listings' => ListingCardResource::collection($listings->getCollection())->resolve($request),
            'pagination' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'total' => $listings->total(),
                'prev_url' => $listings->previousPageUrl(),
                'next_url' => $listings->nextPageUrl(),
            ],
        ]);
    }
}
