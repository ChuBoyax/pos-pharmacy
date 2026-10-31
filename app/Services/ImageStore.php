<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Where uploaded images live.
 *
 * - With a Vercel Blob token (production on Vercel) files go to Vercel Blob, because the
 *   serverless disk is wiped between requests; the stored reference is the full https URL.
 * - Otherwise (local Herd, tests) files go to the "public" disk; the reference is a relative path.
 *
 * Both kinds of reference can coexist in the database, so url() and delete() accept either.
 */
class ImageStore
{
    private const BLOB_API = 'https://vercel.com/api/blob';

    public function __construct(private ?string $blobToken = null) {}

    /**
     * @return string the reference to save on the model (URL or relative path)
     */
    public function put(string $bytes, string $directory, string $extension, string $contentType): string
    {
        $path = trim($directory, '/').'/'.Str::uuid().'.'.$extension;

        if (! $this->blobToken) {
            Storage::disk('public')->put($path, $bytes);

            return $path;
        }

        return Http::withToken($this->blobToken)
            ->withHeaders([
                'x-api-version' => '11',
                'x-vercel-blob-access' => 'public',
                'x-content-type' => $contentType,
                // File names are unique, so the CDN may cache them for good.
                'x-cache-control-max-age' => '31536000',
            ])
            ->withBody($bytes, $contentType)
            ->timeout(30)
            ->put(self::BLOB_API.'/?pathname='.rawurlencode($path))
            ->throw()
            ->json('url');
    }

    public function url(?string $reference): ?string
    {
        if (! $reference) {
            return null;
        }

        return $this->isBlob($reference) ? $reference : '/storage/'.$reference;
    }

    /**
     * Best effort: a file that can't be removed must not fail the user's request.
     */
    public function delete(?string $reference): void
    {
        if (! $reference) {
            return;
        }

        if (! $this->isBlob($reference)) {
            Storage::disk('public')->delete($reference);

            return;
        }

        try {
            Http::withToken((string) $this->blobToken)
                ->withHeaders(['x-api-version' => '11'])
                ->timeout(15)
                ->post(self::BLOB_API.'/delete', ['urls' => [$reference]])
                ->throw();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function isBlob(string $reference): bool
    {
        return str_starts_with($reference, 'https://');
    }
}
