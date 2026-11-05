<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The full outcome of one batch upload, kept on disk so the Upload Results
 * page and the skipped-rows download can show every skipped row. The toast
 * only has room for the counts; a file with 2,600 skipped rows needs a page.
 *
 * One JSON file per upload under storage/app/private/batch-results, readable
 * only by the user who uploaded it. Files older than KEEP_DAYS are removed
 * whenever a new one is written.
 */
class BatchResult
{
    private const KEEP_DAYS = 7;

    /**
     * $skippedRows: each ['line', 'label', 'reason', 'group', 'row'], where
     * 'row' is the row's cells exactly as they were in the file and 'group' is
     * the reason without its row-specific values, for the per-reason counts.
     */
    public static function store(
        string $title,
        string $backUrl,
        array $header,
        array $skippedRows,
        array $counts
    ): string {
        $dir = self::dir();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        self::prune($dir);

        $id = (string) Str::uuid();

        file_put_contents(self::path($id), json_encode([
            'id' => $id,
            'user_id' => auth()->id(),
            'title' => $title,
            'back_url' => $backUrl,
            'created_at' => now()->toDateTimeString(),
            'counts' => $counts,
            'header' => $header,
            'skipped' => $skippedRows,
        ], JSON_INVALID_UTF8_SUBSTITUTE));

        return $id;
    }

    /** The stored result, or null if it is missing, expired or someone else's. */
    public static function find(string $id): ?array
    {
        if (! Str::isUuid($id) || ! is_file(self::path($id))) {
            return null;
        }

        $result = json_decode(file_get_contents(self::path($id)), true);

        return $result && (string) $result['user_id'] === (string) auth()->id() ? $result : null;
    }

    private static function dir(): string
    {
        return storage_path('app/private/batch-results');
    }

    private static function path(string $id): string
    {
        return self::dir() . DIRECTORY_SEPARATOR . $id . '.json';
    }

    private static function prune(string $dir): void
    {
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
