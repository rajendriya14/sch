<?php

namespace Services;

use Models\FfrSavingsAsset;
use Models\FfrSavingsTransaction;

/**
 * Pencatatan pengurangan/penambahan aset "Tabunganku".
 *
 * Semua perubahan saldo aset WAJIB lewat sini agar:
 *   1. setiap perubahan punya jejak transaksi (buku besar),
 *   2. arus dana (konsumsi) bisa dibedakan dari perpindahan aset (transfer),
 *   3. untung/rugi terealisasi tercatat saat menjual,
 *   4. aset yang habis diarsipkan, bukan dihapus.
 */
class SavingsTransactionService
{
    private const EPSILON = 0.00005;

    /**
     * Catat satu transaksi dan perbarui saldo aset.
     *
     * @param array $input asset_id, tx_type, tx_date, quantity, price, fee, tax,
     *                     category, note, target_asset_id, target_price
     * @return array{ok:bool, message:string, ids?:array}
     */
    public function record(array $input): array
    {
        $assetId = (int) ($input['asset_id'] ?? 0);
        $asset = FfrSavingsAsset::find($assetId);
        if (!$asset) {
            return ['ok' => false, 'message' => 'Aset tidak ditemukan.'];
        }

        $type = (string) ($input['tx_type'] ?? 'out');
        if (!in_array($type, FfrSavingsTransaction::INPUT_TYPES, true)) {
            return ['ok' => false, 'message' => 'Jenis transaksi tidak dikenal.'];
        }

        $date = trim((string) ($input['tx_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $isCash = FfrSavingsAsset::isCash($asset['type_key'] ?? '');
        $balance = (float) $asset['quantity'];

        // ---------- Setor / tambah ----------
        if ($type === 'in') {
            $quantity = $this->parseDecimal($input['quantity'] ?? '');
            if ($quantity <= 0) {
                return ['ok' => false, 'message' => 'Jumlah setor harus lebih dari 0.'];
            }

            $price = $isCash ? 1.0 : $this->parseMoney($input['price'] ?? '');
            if (!$isCash && $price <= 0) {
                // Harga boleh kosong dari pemanggil lain: pakai harga kini.
                $price = (float) ($asset['current_price'] ?? 0) > 0
                    ? (float) $asset['current_price']
                    : (float) ($asset['avg_price'] ?? 0);
            }
            if (!$isCash && $price <= 0) {
                return ['ok' => false, 'message' => 'Harga beli wajib diisi.'];
            }

            $fee = $this->parseMoney($input['fee'] ?? '') ?? 0.0;
            $tax = $this->parseMoney($input['tax'] ?? '') ?? 0.0;
            $gross = $quantity * FfrSavingsAsset::unitValue($asset, $price);

            $newBalance = $balance + $quantity;
            $newAvg = $this->weightedAverage($asset, $balance, $quantity, $price);

            FfrSavingsAsset::setBalance($assetId, $newBalance, $newAvg);
            if ((float) ($asset['is_active'] ?? 1) === 0.0 || !empty($asset['archived_at'])) {
                FfrSavingsAsset::unarchive($assetId);
            }

            FfrSavingsTransaction::create([
                'asset_id'      => $assetId,
                'tx_date'       => $date,
                'tx_type'       => 'in',
                'quantity'      => $quantity,
                'price'         => $price,
                'gross_amount'  => $gross,
                'fee'           => $fee,
                'tax'           => $tax,
                'amount'        => $gross + $fee + $tax,
                'balance_after' => $newBalance,
                'category'      => $input['category'] ?? null,
                'note'          => $input['note'] ?? null,
            ]);

            return ['ok' => true, 'message' => $this->quantityLabel($quantity, $asset) . ' ditambahkan.'];
        }

        // ---------- Koreksi ----------
        if ($type === 'adjust') {
            $delta = $this->parseSigned($input['quantity'] ?? '');
            if (abs($delta) < self::EPSILON) {
                return ['ok' => false, 'message' => 'Selisih koreksi tidak boleh 0.'];
            }

            $newBalance = $balance + $delta;
            if ($newBalance < -self::EPSILON) {
                return ['ok' => false, 'message' => 'Koreksi membuat saldo menjadi negatif.'];
            }
            $newBalance = max(0.0, $newBalance);

            $price = $isCash ? 1.0 : $this->parseMoney($input['price'] ?? '') ?? (float) $asset['avg_price'];
            $unitValue = FfrSavingsAsset::unitValue($asset, $price);
            $amount = abs($delta) * $unitValue;

            FfrSavingsAsset::setBalance($assetId, $newBalance);
            if ($newBalance <= self::EPSILON) {
                FfrSavingsAsset::archive($assetId);
            }

            FfrSavingsTransaction::create([
                'asset_id'      => $assetId,
                'tx_date'       => $date,
                'tx_type'       => 'adjust',
                'quantity'      => abs($delta),
                'price'         => $price,
                'gross_amount'  => $amount,
                'amount'        => $amount,
                'balance_after' => $newBalance,
                'category'      => 'koreksi',
                'note'          => $input['note'] ?? 'Koreksi saldo',
            ]);

            return [
                'ok' => true,
                'message' => 'Saldo dikoreksi ' . ($delta < 0 ? '−' : '+') .
                    $this->quantityLabel(abs($delta), $asset) . '.',
            ];
        }

        // ---------- Tarik / jual ----------
        if ($type === 'out') {
            $quantity = $this->parseDecimal($input['quantity'] ?? '');
            if ($quantity <= 0) {
                return ['ok' => false, 'message' => 'Jumlah yang ditarik harus lebih dari 0.'];
            }
            if ($quantity > $balance + self::EPSILON) {
                return [
                    'ok' => false,
                    'message' => 'Jumlah melebihi saldo (' . $this->quantityLabel($balance, $asset) . ').',
                ];
            }

            $price = $isCash ? 1.0 : $this->parseMoney($input['price'] ?? '');
            if (!$isCash && $price <= 0) {
                $price = (float) ($asset['sell_price'] ?? 0) > 0
                    ? (float) $asset['sell_price']
                    : (float) ($asset['current_price'] ?? 0);
            }
            if (!$isCash && $price <= 0) {
                return ['ok' => false, 'message' => 'Harga jual wajib diisi.'];
            }

            $fee = $this->parseMoney($input['fee'] ?? '') ?? 0.0;
            $tax = $this->parseMoney($input['tax'] ?? '') ?? 0.0;
            $unitValue = FfrSavingsAsset::unitValue($asset, $price);
            $gross = $quantity * $unitValue;
            $net = max(0.0, $gross - $fee - $tax);

            $costBasis = $this->costBasis($asset, $quantity);
            $realized = $net - $costBasis;

            $newBalance = max(0.0, $balance - $quantity);

            FfrSavingsAsset::setBalance($assetId, $newBalance);
            if ($newBalance <= self::EPSILON) {
                FfrSavingsAsset::archive($assetId);
            }

            FfrSavingsTransaction::create([
                'asset_id'      => $assetId,
                'tx_date'       => $date,
                'tx_type'       => 'out',
                'quantity'      => $quantity,
                'price'         => $price,
                'gross_amount'  => $gross,
                'fee'           => $fee,
                'tax'           => $tax,
                'amount'        => $net,
                'cost_basis'    => $costBasis,
                'realized_gain' => $realized,
                'balance_after' => $newBalance,
                'category'      => $input['category'] ?? null,
                'note'          => $input['note'] ?? null,
            ]);

            $message = $this->quantityLabel($quantity, $asset) . ' ditarik (Rp ' .
                number_format($net, 0, ',', '.') . ').';
            if ($newBalance <= self::EPSILON) {
                $message .= ' Aset diarsipkan karena saldo habis.';
            }
            if (abs($realized) >= 1) {
                $message .= ' Realisasi ' . ($realized < 0 ? 'rugi' : 'untung') . ' Rp ' .
                    number_format(abs($realized), 0, ',', '.') . '.';
            }

            return ['ok' => true, 'message' => $message];
        }

        // ---------- Transfer antar aset ----------
        if ($type === 'transfer') {
            $targetId = (int) ($input['target_asset_id'] ?? 0);
            if ($targetId === $assetId) {
                return ['ok' => false, 'message' => 'Aset tujuan harus berbeda.'];
            }

            $target = FfrSavingsAsset::find($targetId);
            if (!$target) {
                return ['ok' => false, 'message' => 'Aset tujuan tidak ditemukan.'];
            }

            $quantity = $this->parseDecimal($input['quantity'] ?? '');
            if ($quantity <= 0) {
                return ['ok' => false, 'message' => 'Jumlah yang dipindah harus lebih dari 0.'];
            }
            if ($quantity > $balance + self::EPSILON) {
                return [
                    'ok' => false,
                    'message' => 'Jumlah melebihi saldo (' . $this->quantityLabel($balance, $asset) . ').',
                ];
            }

            $price = $isCash ? 1.0 : $this->parseMoney($input['price'] ?? '');
            if (!$isCash && $price <= 0) {
                $price = (float) ($asset['sell_price'] ?? 0) > 0
                    ? (float) $asset['sell_price']
                    : (float) ($asset['current_price'] ?? 0);
            }
            if (!$isCash && $price <= 0) {
                return ['ok' => false, 'message' => 'Harga jual aset sumber wajib diisi.'];
            }

            $fee = $this->parseMoney($input['fee'] ?? '') ?? 0.0;
            $tax = $this->parseMoney($input['tax'] ?? '') ?? 0.0;
            $sourceUnit = FfrSavingsAsset::unitValue($asset, $price);
            $gross = $quantity * $sourceUnit;
            $net = max(0.0, $gross - $fee - $tax);

            $targetIsCash = FfrSavingsAsset::isCash($target['type_key'] ?? '');
            $targetPrice = $targetIsCash ? 1.0 : $this->parseMoney($input['target_price'] ?? '');
            if ($targetIsCash) {
                $targetPrice = 1.0;
            } elseif ($targetPrice <= 0) {
                $targetPrice = (float) $target['current_price'];
            }
            $targetUnit = FfrSavingsAsset::unitValue($target, $targetPrice);
            if ($targetUnit <= 0) {
                return ['ok' => false, 'message' => 'Harga aset tujuan belum diisi.'];
            }
            $targetQty = $net / $targetUnit;

            $costBasis = $this->costBasis($asset, $quantity);
            $realized = $net - $costBasis;

            $group = bin2hex(random_bytes(8));
            $sourceBalance = max(0.0, $balance - $quantity);
            $targetBalance = (float) $target['quantity'] + $targetQty;
            $targetAvg = $this->weightedAverage($target, (float) $target['quantity'], $targetQty, $targetPrice);

            FfrSavingsAsset::setBalance($assetId, $sourceBalance);
            FfrSavingsAsset::setBalance($targetId, $targetBalance, $targetAvg);
            FfrSavingsAsset::unarchive($targetId);

            if ($sourceBalance <= self::EPSILON) {
                FfrSavingsAsset::archive($assetId);
            }

            FfrSavingsTransaction::create([
                'asset_id'      => $assetId,
                'tx_date'       => $date,
                'tx_type'       => 'transfer_out',
                'quantity'      => $quantity,
                'price'         => $price,
                'gross_amount'  => $gross,
                'fee'           => $fee,
                'tax'           => $tax,
                'amount'        => $net,
                'cost_basis'    => $costBasis,
                'realized_gain' => $realized,
                'balance_after' => $sourceBalance,
                'counterpart_asset_id' => $targetId,
                'transfer_group' => $group,
                'category'      => $input['category'] ?? 'rebalance',
                'note'          => $input['note'] ?? null,
            ]);

            FfrSavingsTransaction::create([
                'asset_id'      => $targetId,
                'tx_date'       => $date,
                'tx_type'       => 'transfer_in',
                'quantity'      => $targetQty,
                'price'         => $targetPrice,
                'gross_amount'  => $net,
                'amount'        => $net,
                'balance_after' => $targetBalance,
                'counterpart_asset_id' => $assetId,
                'transfer_group' => $group,
                'category'      => $input['category'] ?? 'rebalance',
                'note'          => $input['note'] ?? null,
            ]);

            return [
                'ok' => true,
                'message' => $this->quantityLabel($quantity, $asset) . ' dipindah ke "' .
                    $target['name'] . '" (' . $this->quantityLabel($targetQty, $target) . ').',
            ];
        }

        return ['ok' => false, 'message' => 'Jenis transaksi belum didukung.'];
    }

    /** Rata-rata tertimbang setelah penambahan. */
    private function weightedAverage(array $asset, float $oldQty, float $addQty, float $addPrice): float
    {
        if (FfrSavingsAsset::isCash($asset['type_key'] ?? '')) {
            return 1.0;
        }

        $oldAvg = (float) ($asset['avg_price'] ?? 0);
        $total = $oldQty + $addQty;
        if ($total <= self::EPSILON) {
            return $addPrice;
        }

        return (($oldQty * $oldAvg) + ($addQty * $addPrice)) / $total;
    }

    /** Modal (harga beli rata-rata) untuk sejumlah unit yang dilepas. */
    private function costBasis(array $asset, float $quantity): float
    {
        $avg = (float) ($asset['avg_price'] ?? 0);
        return $quantity * FfrSavingsAsset::unitValue($asset, $avg);
    }

    /** Label jumlah yang ramah: "5 gram", "Rp 1.500.000", "1.000 lembar". */
    private function quantityLabel(float $quantity, array $asset): string
    {
        if (FfrSavingsAsset::isCash($asset['type_key'] ?? '')) {
            return 'Rp ' . number_format($quantity, 2, ',', '.');
        }

        $text = rtrim(rtrim(number_format($quantity, 4, ',', '.'), '0'), ',');
        $unit = trim((string) ($asset['unit_label'] ?? '')) ?: 'unit';

        return $text . ' ' . $unit;
    }

    /** "1.500.000,50" → 1500000.5 ; kosong → null. */
    private function parseMoney(mixed $value): ?float
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $clean = preg_replace('/[^0-9,.\-]/', '', $text);
        if ($clean === '' || $clean === '-') {
            return null;
        }
        if (strpos($clean, ',') !== false) {
            return (float) str_replace(',', '.', str_replace('.', '', $clean));
        }
        return (float) str_replace('.', '', $clean);
    }

    /** Jumlah unit; "0,5" tetap 0,5 (bukan 5). */
    private function parseDecimal(mixed $value): float
    {
        $text = trim((string) $value);
        if ($text === '') {
            return 0.0;
        }
        $clean = preg_replace('/[^0-9,.\-]/', '', $text);
        if (strpos($clean, ',') !== false) {
            return (float) str_replace(',', '.', str_replace('.', '', $clean));
        }
        if (preg_match('/^-?\d*\.\d{1,4}$/', $clean)) {
            return (float) $clean;
        }
        return (float) str_replace('.', '', $clean);
    }

    /** Selisih koreksi bertanda: "-2" atau "-0,5" tetap negatif. */
    private function parseSigned(mixed $value): float
    {
        $text = trim((string) $value);
        if ($text === '') {
            return 0.0;
        }
        $negative = str_starts_with($text, '-');
        $number = abs($this->parseDecimal($text));

        return $negative ? -$number : $number;
    }
}
