<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Shared CSV reading for the batch-upload screens.
 *
 * Extracted once a third importer needed the same parsing. MachineController
 * still carries its own copy of readCsv()/rowToPayload()/streamCsv() — it works
 * and predates this, so it was left alone rather than refactored in passing.
 */
trait ImportsCsv
{
    /**
     * Returns [header, rows].
     *
     * The BOM strip is not optional: Excel on Windows writes UTF-8 CSVs with a
     * byte-order mark, which lands on the first header cell and turns "name"
     * into "\xEF\xBB\xBFname". The first column is the key in every one of these
     * files, so without this every single row skips as unkeyed.
     */
    protected function readCsv(UploadedFile $file): array
    {
        // Read record by record with fgetcsv rather than line by line: a cell
        // typed with Alt+Enter in Excel is saved as a quoted value containing
        // a line break, and splitting on lines cut such a row into two halves
        // that both failed the column count. rowToPayload() trims the break.
        $records = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle !== false) {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                // A blank line comes back as [null]; skipped, as before.
                if ($row !== [null]) {
                    $records[] = $row;
                }
            }

            fclose($handle);
        }

        $header = array_map('trim', array_shift($records) ?: []);

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        return [$header, $records];
    }

    /**
     * Maps one CSV row onto the known columns, ignoring anything extra.
     *
     * Columns the file leaves out entirely come back as '' rather than absent,
     * so a file carrying only the required columns is read as blanks instead of
     * throwing undefined-key warnings on every optional field. Rows whose column
     * count doesn't match the header return null — padding them would mis-align
     * every value on the row.
     */
    protected function rowToPayload(array $header, array $row, array $columns): ?array
    {
        if (count($row) !== count($header)) {
            return null;
        }

        $data = array_intersect_key(array_combine($header, $row), array_flip($columns));
        $data = array_map(fn ($v) => trim((string) $v), $data);

        return $data + array_fill_keys($columns, '');
    }

    /**
     * A blank cell means "not stated", which takes the column default — that is
     * why this cannot just be FILTER_VALIDATE_BOOLEAN, which reads '' as false
     * and would import every row inactive when the column is left out.
     */
    protected function csvBool(string $value, bool $default): bool
    {
        return $value === '' ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** Streams a template download built from the same rows the page displays. */
    protected function streamCsv(array $columns, array $rows, string $filename)
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * The flash message every batch upload ends with: what landed, and why the
     * rest didn't. Reasons are de-duplicated and capped so one malformed file
     * cannot produce a thousand-line alert.
     */
    protected function batchSummary(string $noun, int $imported, int $skipped, array $reasons, string $extra = ''): string
    {
        $message = "Imported {$imported} {$noun}(s).";

        // Anything the import created as a side effect — e.g. suppliers added
        // because a row named one that wasn't on file yet.
        if ($extra !== '') {
            $message .= " {$extra}";
        }

        if ($skipped > 0) {
            $unique = array_values(array_unique($reasons));
            $message .= " {$skipped} row(s) skipped: " . implode('; ', array_slice($unique, 0, 5))
                . (count($unique) > 5 ? '; …' : '.');
        }

        return $message;
    }
}
