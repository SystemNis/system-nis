<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font as XlFont;

/**
 * ReconciliationExportController
 * ════════════════════════════════
 * Exports the Master Reconciliation Sheet to .xlsx, matching the exact
 * column layout the client specified.
 */
class ReconciliationExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $units = Unit::with(['cluster', 'activeBuyer'])
            ->when($request->filled('cluster'), fn ($q) => $q->where('cluster_id', $request->input('cluster')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('payment_type'), fn ($q) => $q->where('payment_type', $request->input('payment_type')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = '%' . $request->input('search') . '%';
                $q->where(function ($inner) use ($s) {
                    $inner->where('block', 'like', $s)
                          ->orWhere('unit_number', 'like', $s)
                          ->orWhere('house_type', 'like', $s)
                          ->orWhereHas('activeBuyer', fn ($b) => $b->where('name', 'like', $s))
                          ->orWhereHas('cluster', fn ($c) => $c->where('name', 'like', $s));
                });
            })
            ->orderBy('cluster_id')
            ->orderBy('block')
            ->orderBy('unit_number')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reconciliation');

        // ── Header row ───────────────────────────────────────────────────
        $headers = [
            'A1' => 'NAME',
            'B1' => 'CLUSTER',
            'C1' => 'BLOCK',
            'D1' => 'HARGA JUAL',
            'E1' => 'KAVLING LIMIT',
            'F1' => 'TOTAL ANGSURAN',
            'G1' => 'SISA ANGSURAN',
            'H1' => 'PENERIMAAN',
            'I1' => 'SISA PENERIMAAN',
        ];
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $headerRange = 'A1:I1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'name' => 'Arial', 'size' => 11],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9D9D9'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '999999']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Data rows ────────────────────────────────────────────────────
        $row = 2;
        foreach ($units as $unit) {
            $sheet->setCellValue("A{$row}", $unit->activeBuyer?->name ?? '');
            $sheet->setCellValue("B{$row}", $unit->cluster?->name ?? '');
            $sheet->setCellValue("C{$row}", $unit->unit_label);
            $sheet->setCellValue("D{$row}", $unit->harga_penjualan);
            $sheet->setCellValue("E{$row}", $unit->kavling_value);
            $sheet->setCellValue("F{$row}", $unit->total_penerimaan);
            $sheet->setCellValue("G{$row}", $unit->sisa_penerimaan);
            $sheet->setCellValue("H{$row}", $unit->cumulative_nis_share);
            $sheet->setCellValue("I{$row}", $unit->remaining_kavling_headroom);
            $row++;
        }

        $lastRow = $row - 1;

        // ── Number formatting ────────────────────────────────────────────
        if ($lastRow >= 2) {
            $sheet->getStyle("D2:I{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0;(#,##0);"-"');

            $sheet->getStyle("A2:I{$lastRow}")->applyFromArray([
                'font'    => ['name' => 'Arial', 'size' => 10],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D0D0D0']],
                ],
            ]);

            // Zebra striping
            for ($r = 2; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:I{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F7F7F7');
                }
            }
        }

        // ── Column widths ───────────────────────────────────────────────
        $widths = ['A' => 22, 'B' => 14, 'C' => 10, 'D' => 16, 'E' => 16, 'F' => 17, 'G' => 16, 'H' => 16, 'I' => 17];
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $sheet->freezePane('A2');

        $filename = 'NIS_Reconciliation_' . now()->format('Y-m-d_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}