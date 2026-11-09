<?php

namespace Models;

use Config\Database;
use PDO;

/**
 * Model untuk biaya operasional tambahan BAK (Step 2).
 * Menyimpan pos biaya per periode (Performance Incentive, Facility/Seat Cost, dsb.),
 * yang kemudian disinkronkan ke Max Potential Earnings, Accrual Input, dan Actual Input.
 */
class BakOperationalCost
{
    /** Template pos biaya awal untuk periode baru */
    public const DEFAULT_ITEMS = [
        [
            'category'   => 'B. Operational Cost',
            'item_name'  => 'Performance Incentive',
            'amount'     => 0,
            'sort_order' => 1,
        ],
        [
            'category'   => 'B. Operational Cost',
            'item_name'  => 'Facility/ Seat Cost',
            'amount'     => 0,
            'sort_order' => 2,
        ],
    ];

    /**
     * Ambil semua baris operational cost untuk periode tertentu.
     * Jika periode tersebut belum ada data, otomatis di-seed dengan template default.
     */
    public static function allByPeriod(string $period): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT id, period, category, item_name, amount, notes, sort_order
             FROM bak_operational_costs
             WHERE period = ?
             ORDER BY category ASC, sort_order ASC, id ASC'
        );
        $stmt->execute([$period]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            self::seedDefaults($period);
            $stmt->execute([$period]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $rows;
    }

    /**
     * Seed item default untuk satu periode.
     */
    public static function seedDefaults(string $period): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'INSERT INTO bak_operational_costs (period, category, item_name, amount, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach (self::DEFAULT_ITEMS as $item) {
            $stmt->execute([
                $period,
                $item['category'],
                $item['item_name'],
                $item['amount'],
                $item['sort_order'],
            ]);
        }
    }

    /**
     * Simpan (Create / Update / Delete) daftar operational cost untuk satu periode.
     */
    public static function saveItems(string $period, array $items): array
    {
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            $ins = $db->prepare(
                'INSERT INTO bak_operational_costs (period, category, item_name, amount, notes, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $upd = $db->prepare(
                'UPDATE bak_operational_costs
                 SET category = ?, item_name = ?, amount = ?, notes = ?, sort_order = ?
                 WHERE id = ? AND period = ?'
            );
            $del = $db->prepare(
                'DELETE FROM bak_operational_costs WHERE id = ? AND period = ?'
            );

            foreach ($items as $idx => $item) {
                $id = !empty($item['id']) ? (int) $item['id'] : 0;
                $isDelete = !empty($item['delete']);

                if ($isDelete && $id > 0) {
                    $del->execute([$id, $period]);
                    continue;
                }

                $category = trim((string) ($item['category'] ?? 'B. Operational Cost'));
                if ($category === '') {
                    $category = 'B. Operational Cost';
                }
                $itemName = trim((string) ($item['item_name'] ?? ''));
                if ($itemName === '') {
                    continue;
                }
                $amount = round((float) ($item['amount'] ?? 0), 2);
                $notes = !empty($item['notes']) ? trim((string) $item['notes']) : null;
                $sortOrder = isset($item['sort_order']) ? (int) $item['sort_order'] : $idx + 1;

                if ($id > 0) {
                    $upd->execute([$category, $itemName, $amount, $notes, $sortOrder, $id, $period]);
                } else {
                    $ins->execute([$period, $category, $itemName, $amount, $notes, $sortOrder]);
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return self::allByPeriod($period);
    }

    /**
     * Sinkronkan pos biaya operasional ke Max Potential Earnings.
     * Dari Max Potential, data otomatis mengalir ke Accrual Input dan Actual Input.
     */
    public static function syncToMaxPotential(string $period): array
    {
        $items = self::allByPeriod($period);
        $db = Database::getInstance();

        $find = $db->prepare('SELECT id, role FROM max_potential_earnings WHERE role = ?');
        $ins = $db->prepare(
            'INSERT INTO max_potential_earnings
                (role, category, billing_type, price_per_unit, quantity, subtotal, manual_amount, is_custom)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $upd = $db->prepare(
            'UPDATE max_potential_earnings
             SET category = ?, billing_type = ?, price_per_unit = ?, quantity = ?, subtotal = ?, manual_amount = ?, is_custom = 1
             WHERE id = ?'
        );

        foreach ($items as $item) {
            $role = (string) $item['item_name'];
            $cat  = (string) $item['category'];
            $amt  = round((float) $item['amount'], 2);

            $find->execute([$role]);
            $existing = $find->fetch();

            if (!$existing) {
                $ins->execute([$role, $cat, 'Headcount', $amt, 1, $amt, $amt]);
            } else {
                $upd->execute([$cat, 'Headcount', $amt, 1, $amt, $amt, (int) $existing['id']]);
            }
        }

        SellerInput::syncFromMaxPotential();
        ActualInput::syncReferences();

        return MaxPotentialEarning::summary();
    }
}

