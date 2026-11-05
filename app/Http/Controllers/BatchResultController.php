<?php

namespace App\Http\Controllers;

use App\Support\BatchResult;

/**
 * The "View details" page a batch upload's toast links to, and the download
 * of the rows it skipped. See App\Support\BatchResult for where these live.
 */
class BatchResultController extends Controller
{
    public function show(string $id)
    {
        $result = BatchResult::find($id) ?? abort(404);

        // Most skipped files have one or two causes repeated thousands of
        // times; the counts per cause are what tell you what to fix.
        $skipped = collect($result['skipped']);

        $groups = $skipped->countBy('group')->sortDesc();

        $onFileGroups = $skipped
            ->reject(fn ($s) => $s['fixable'] ?? true)
            ->pluck('group')
            ->flip();

        $toFix = $skipped->filter(fn ($s) => $s['fixable'] ?? true)->count();

        return view('batch-results.show', compact('result', 'groups', 'onFileGroups', 'toFix'));
    }

    /**
     * The rows that need fixing, exactly as they were uploaded, with an
     * upload_error column in front saying why. Rows skipped because their
     * contract is already on file are left out — there is nothing to fix.
     * Every upload reads columns by header name and ignores any it does not
     * know, so the fixed file can be uploaded again as it is, upload_error
     * column and all.
     */
    public function skippedCsv(string $id)
    {
        $result = BatchResult::find($id) ?? abort(404);
        $header = $result['header'];
        $result['skipped'] = array_filter($result['skipped'], fn ($s) => $s['fixable'] ?? true);

        return response()->streamDownload(function () use ($result, $header) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM, or Excel shows an ñ in the file as Ã±.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['upload_error'], $header));

            foreach ($result['skipped'] as $skip) {
                // A row with the wrong number of cells is padded or cut to fit
                // the header, so its cells stay under the right columns.
                $row = array_slice(array_pad($skip['row'], count($header), ''), 0, count($header));
                fputcsv($out, array_merge(["line {$skip['line']}: {$skip['reason']}"], $row));
            }

            fclose($out);
        }, 'skipped_rows_' . now()->format('Ymd_His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
