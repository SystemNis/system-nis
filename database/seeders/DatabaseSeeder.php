<?php

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\Unit;
use App\Models\Buyer;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Cluster ────────────────────────────────────────────────────────
        $assher = Cluster::create([
            'name'     => 'ASSHER',
            'location' => 'Area Barat, Blok A',
        ]);

        $meridian = Cluster::create([
            'name'     => 'MERIDIAN',
            'location' => 'Area Timur, Blok M',
        ]);

        // ── Unit A-0 (matches the reference card exactly) ─────────────────
        // LT=75m² → Kavling = 75 × 4,000,000 = Rp 300,000,000
        // Harga = 1,000,000,000 | DP = 50,000,000
        // CB (10 installments) → angsuran = (1,000,000,000 - 50,000,000) / 10 = 95,000,000 ≈ 100,000,000
        $unitA0 = Unit::create([
            'cluster_id'         => $assher->id,
            'block'              => 'A',
            'unit_number'        => '0',
            'house_type'         => 'STANDARD',
            'luas_bangunan'      => 20,
            'luas_tanah'         => 75,
            'payment_type'       => 'Cash Bertahap',
            'harga_penjualan'    => 1_000_000_000,
            'down_payment'       => 50_000_000,
            'angsuran_per_bulan' => 100_000_000,
            'max_installments'   => 10,
            'status'             => 'active',
        ]);

        $buyerHalberg = Buyer::create([
            'unit_id'       => $unitA0->id,
            'name'          => 'HALBERG NG',
            'contract_date' => Carbon::parse('2024-01-01'),
            'is_active'     => true,
        ]);

        // ── Payments for A-0 (matching reference image) ───────────────────
        // Payments ledger:
        //   1. Rp 100,000,000 - 1 Jan  ✅ Correct
        //   2. Rp 100,000,000 - 1 Feb  ✅ Correct
        //   3. Rp  90,000,000 - 1 Mar  ❌ Underpay (Short: -10,000,000)
        //   4. Rp 110,000,000 - 1 Apr  ⚠️ Overpay  (Excess: +10,000,000)
        //   5–10: not yet paid

        $kavlingValue = $unitA0->kavling_value; // 300,000,000
        $nisRate      = Unit::NIS_SHARE_RATE;   // 0.30

        $paymentsData = [
            [
                'installment_number' => 1,
                'payment_date'       => '2024-01-01',
                'due_date'           => '2024-01-01',
                'actual_amount'      => 100_000_000,
            ],
            [
                'installment_number' => 2,
                'payment_date'       => '2024-02-01',
                'due_date'           => '2024-02-01',
                'actual_amount'      => 100_000_000,
            ],
            [
                'installment_number' => 3,
                'payment_date'       => '2024-03-01',
                'due_date'           => '2024-03-01',
                'actual_amount'      => 90_000_000,
            ],
            [
                'installment_number' => 4,
                'payment_date'       => '2024-04-01',
                'due_date'           => '2024-04-01',
                'actual_amount'      => 110_000_000,
            ],
        ];

        $runningNis = 0;

        foreach ($paymentsData as $p) {
            $target   = $unitA0->angsuran_per_bulan; // 100,000,000
            $actual   = $p['actual_amount'];
            $variance = $actual - $target;

            $status = match(true) {
                $variance > 0 => 'overpay',
                $variance < 0 => 'underpay',
                default       => 'correct',
            };

            $supposedNis = (int) ($target * $nisRate);   // 30,000,000
            $actualNis   = (int) ($actual * $nisRate);   // varies

            // Apply kavling ceiling
            $headroom   = max(0, $kavlingValue - $runningNis);
            $cappedNis  = min($actualNis, $headroom);
            $runningNis += $cappedNis;

            $overflow        = ($actualNis > $headroom) && ($headroom >= 0);
            $clearedHere     = $overflow && ($headroom > 0);

            Payment::create([
                'unit_id'                      => $unitA0->id,
                'buyer_id'                     => $buyerHalberg->id,
                'installment_number'           => $p['installment_number'],
                'payment_date'                 => $p['payment_date'],
                'due_date'                     => $p['due_date'],
                'target_amount'                => $target,
                'actual_amount'                => $actual,
                'variance'                     => $variance,
                'payment_status'               => $status,
                'supposed_nis_share'           => $supposedNis,
                'actual_nis_share'             => $actualNis,
                'cumulative_nis_share'         => $runningNis,
                'capped_nis_share'             => $cappedNis,
                'land_ceiling_overflow'        => $overflow,
                'land_cleared_on_this_payment' => $clearedHere,
                'reported_by'                  => 'APP-SYSTEM',
            ]);
        }

        // ── A few more units for the dashboard KPIs ───────────────────────

        $unitA1 = Unit::create([
            'cluster_id'         => $assher->id,
            'block'              => 'A',
            'unit_number'        => '1',
            'house_type'         => 'STANDARD',
            'luas_bangunan'      => 20,
            'luas_tanah'         => 75,
            'payment_type'       => 'Cash Bertahap',
            'harga_penjualan'    => 1_000_000_000,
            'down_payment'       => 50_000_000,
            'angsuran_per_bulan' => 100_000_000,
            'max_installments'   => 10,
            'status'             => 'unpaid',
        ]);

        $unitM1 = Unit::create([
            'cluster_id'         => $meridian->id,
            'block'              => 'M',
            'unit_number'        => '1',
            'house_type'         => 'PREMIUM',
            'luas_bangunan'      => 36,
            'luas_tanah'         => 90,
            'payment_type'       => 'KPR',
            'harga_penjualan'    => 1_500_000_000,
            'down_payment'       => 150_000_000,
            'angsuran_per_bulan' => 11_250_000, // ÷ 120 months
            'max_installments'   => 120,
            'status'             => 'active',
        ]);

        Buyer::create([
            'unit_id'       => $unitM1->id,
            'name'          => 'SARI DEWI',
            'contract_date' => Carbon::parse('2024-01-15'),
            'is_active'     => true,
        ]);
    }
}
