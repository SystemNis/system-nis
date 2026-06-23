<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ReconciliationImportController
 * ════════════════════════════════
 * Handles .xlsx bulk import of units.
 *
 * Expected column layout (row 1 = header, rows 2+ = data):
 *   A: TAHAP   — numeric 1/2/3  → "Tahap 1" / "Tahap 2" / "Tahap 3"
 *   B: CLUSTER — cluster name   → auto-created if not found in DB
 *   C: BLOK    — block letter(s)
 *   D: UNIT    — unit number
 *   E: LT      — luas tanah (m²)
 *
 * Import behaviour:
 *   - SKIP rows where CLUSTER+BLOK+UNIT already exists for that cluster
 *     (same unique constraint as the DB: cluster_id + block + unit_number)
 *   - SKIP rows missing any of the 4 required fields (CLUSTER, BLOK, UNIT, LT)
 *   - Auto-create Cluster records for any cluster name not yet in the DB
 *   - All other Unit fields (house_type, harga_penjualan, etc.) are left NULL
 *     so they can be filled in progressively via the Reconciliation UI
 *   - Returns a summary: imported / skipped-duplicate / skipped-invalid / errors
 */
class ReconciliationImportController extends Controller
{
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,xls|max:10240',
        ], [
            'import_file.required' => 'Please select an Excel file to import.',
            'import_file.mimes'    => 'Only .xlsx and .xls files are supported.',
            'import_file.max'      => 'File must be under 10MB.',
        ]);

        $file = $request->file('import_file');
        $path = $file->getRealPath();

        // ── Parse the workbook ────────────────────────────────────────────
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable $e) {
            return back()->withErrors(['import_file' => 'Could not read the Excel file: ' . $e->getMessage()]);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        // Drop header row
        array_shift($rows);

        // ── Counters ─────────────────────────────────────────────────────
        $imported   = 0;
        $duplicates = 0;
        $invalid    = 0;
        $errors     = [];

        // ── Cluster cache ─────────────────────────────────────────────────
        // Load all existing clusters into memory so we don't query on every row.
        // We'll add to this cache as new clusters are created.
        $clusterCache = Cluster::pluck('id', 'name')
            ->mapWithKeys(fn($id, $name) => [strtoupper(trim($name)) => $id])
            ->toArray();

        // ── Tahap map ─────────────────────────────────────────────────────
        $tahapMap = [
            '1' => 'Tahap 1',
            '2' => 'Tahap 2',
            '3' => 'Tahap 3',
        ];

        foreach ($rows as $rowIndex => $row) {
            // $row is 0-indexed: [TAHAP, CLUSTER, BLOK, UNIT, LT, ...]
            $rawTahap   = isset($row[0]) ? trim((string) $row[0]) : '';
            $rawCluster = isset($row[1]) ? trim((string) $row[1]) : '';
            $rawBlok    = isset($row[2]) ? trim((string) $row[2]) : '';
            $rawUnit    = isset($row[3]) ? trim((string) $row[3]) : '';
            $rawLt      = isset($row[4]) ? trim((string) $row[4]) : '';

            // Skip completely empty rows (trailing blank rows at end of sheet)
            if ($rawCluster === '' && $rawBlok === '' && $rawUnit === '' && $rawLt === '') {
                continue;
            }

            // ── Validate required fields ───────────────────────────────────
            if ($rawCluster === '' || $rawBlok === '' || $rawUnit === '' || $rawLt === '' || !is_numeric($rawLt)) {
                $invalid++;
                if (count($errors) < 10) {
                    $dataRowNum = $rowIndex + 2; // +2: 1-indexed + header row
                    $errors[] = "Row {$dataRowNum}: missing or invalid required field "
                        . "(CLUSTER='{$rawCluster}', BLOK='{$rawBlok}', UNIT='{$rawUnit}', LT='{$rawLt}').";
                }
                continue;
            }

            // ── Normalize ─────────────────────────────────────────────────
            $tahap       = isset($tahapMap[$rawTahap]) ? $tahapMap[$rawTahap] : null;
            $clusterName = strtoupper($rawCluster);        // "ASSCHER"
            $block       = strtoupper($rawBlok);           // "A"
            $unitNumber  = (string) intval($rawUnit);      // "1" (strip leading zeros if any)
            $luasTanah   = (int) $rawLt;

            // ── Resolve cluster (create if new) ───────────────────────────
            if (!isset($clusterCache[$clusterName])) {
                $cluster = Cluster::create([
                    'name' => ucfirst(strtolower($rawCluster)), // "Asscher" style
                ]);
                $clusterCache[$clusterName] = $cluster->id;
            }

            $clusterId = $clusterCache[$clusterName];

            // ── Check for duplicate ───────────────────────────────────────
            $exists = Unit::withTrashed()
                ->where('cluster_id', $clusterId)
                ->where('block', $block)
                ->where('unit_number', $unitNumber)
                ->exists();

            if ($exists) {
                $duplicates++;
                continue;
            }

            // ── Insert ────────────────────────────────────────────────────
            try {
                Unit::create([
                    'tahap'       => $tahap,
                    'cluster_id'  => $clusterId,
                    'block'       => $block,
                    'unit_number' => $unitNumber,
                    'luas_tanah'  => $luasTanah,
                    'status'      => 'unpaid',
                ]);
                $imported++;
            } catch (\Throwable $e) {
                $invalid++;
                if (count($errors) < 10) {
                    $errors[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                }
            }
        }

        // ── Build result message ──────────────────────────────────────────
        $parts = [];
        if ($imported > 0)   $parts[] = "{$imported} imported";
        if ($duplicates > 0) $parts[] = "{$duplicates} skipped (already exist)";
        if ($invalid > 0)    $parts[] = "{$invalid} skipped (invalid/missing data)";

        $message = 'Import complete: ' . implode(', ', $parts) . '.';

        if (!empty($errors)) {
            $message .= ' First errors: ' . implode(' | ', $errors);
        }

        return redirect()
            ->route('reconciliation.index')
            ->with('success', $message);
    }
}