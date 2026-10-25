<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Models\Listing;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingExportController extends Controller
{
    /**
     * All finished listings as a CSV that opens cleanly in Excel, including
     * Turkish and Arabic text (UTF-8 with a byte-order mark).
     */
    public function __invoke(Listing $listing): StreamedResponse
    {
        $copies = $listing->copies()->where('status', GenerationStatus::Completed)->get();
        $filename = str($listing->name ?: 'listing')->slug()->limit(60, '').'-listings.csv';

        return response()->streamDownload(function () use ($copies) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Marketplace', 'Language', 'Title', 'Highlight', 'Bullet points',
                'Description', 'Description (HTML)', 'Keywords', 'Category',
            ]);

            foreach ($copies as $copy) {
                fputcsv($out, [
                    $copy->marketplace->label(),
                    $copy->language->englishName(),
                    $copy->title,
                    $copy->highlight,
                    implode("\n", $copy->bullets ?? []),
                    $copy->description,
                    $copy->descriptionHtml(),
                    implode(', ', $copy->keywords ?? []),
                    $copy->category,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
