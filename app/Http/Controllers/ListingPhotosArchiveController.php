<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Models\Listing;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ListingPhotosArchiveController extends Controller
{
    /**
     * The original photo plus every generated photo in one ZIP.
     */
    public function __invoke(Listing $listing): BinaryFileResponse
    {
        $disk = Storage::disk(Listing::disk());
        $photos = $listing->photos()->where('status', GenerationStatus::Completed)->get();

        abort_if($photos->isEmpty(), 404);

        $slug = str($listing->name ?: 'product')->slug()->limit(60, '');
        $zipPath = tempnam(sys_get_temp_dir(), 'vitrin-zip');

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);

        if ($disk->exists($listing->source_path)) {
            $zip->addFromString("{$slug}-original.jpg", $disk->get($listing->source_path));
        }

        foreach ($photos as $photo) {
            if ($photo->path && $disk->exists($photo->path)) {
                $zip->addFromString($photo->downloadName(), $disk->get($photo->path));
            }
        }

        $zip->close();

        return response()->download($zipPath, "{$slug}-photos.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }
}
