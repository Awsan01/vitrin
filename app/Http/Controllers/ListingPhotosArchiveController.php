<?php

namespace App\Http\Controllers;

use App\Enums\GenerationStatus;
use App\Models\Listing;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
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

        $files = [$listing->source_path => "{$slug}-original.jpg"];

        foreach ($photos as $photo) {
            if ($photo->path) {
                $files[$photo->path] = $photo->downloadName();
            }
        }

        return response()->download($this->zip($disk, $files), "{$slug}-photos.zip", ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /**
     * Build the archive in a temp file. Each photo is copied to its own temp
     * file first: ZipArchive only reads added files when it closes, so this
     * keeps memory flat however large the photos are.
     *
     * @param  array<string, string>  $files  Storage path => name inside the ZIP
     */
    protected function zip(Filesystem $disk, array $files): string
    {
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'vitrin-zip');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the photo archive.');
        }

        $copies = [];
        $open = true;

        try {
            foreach ($files as $path => $name) {
                $stream = $disk->exists($path) ? $disk->readStream($path) : null;

                if (! is_resource($stream)) {
                    continue;
                }

                $copy = (string) tempnam(sys_get_temp_dir(), 'vitrin-photo');
                $copies[] = $copy;
                file_put_contents($copy, $stream);
                fclose($stream);

                $zip->addFile($copy, $name);
            }

            // An archive with no entries is deleted on close, leaving nothing to send.
            abort_if($zip->numFiles === 0, 404);

            // close() releases the archive whether or not it succeeds.
            $open = false;

            if (! $zip->close()) {
                throw new RuntimeException('Could not write the photo archive.');
            }
        } catch (Throwable $e) {
            // Discard pending entries, or PHP writes them when $zip is freed.
            if ($open) {
                $zip->unchangeAll();
            }

            @unlink($zipPath);

            throw $e;
        } finally {
            foreach ($copies as $copy) {
                @unlink($copy);
            }
        }

        return $zipPath;
    }
}
