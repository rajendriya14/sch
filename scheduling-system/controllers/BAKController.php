<?php

namespace Controllers;

use Models\ActualInput;
use Models\BakAttendance;
use Models\MaxPotentialEarning;
use Models\SellerInput;

/**
 * BAK — Berita Acara Kerja
 *
 * index() : halaman wizard 3 langkah (React, assets/js/BAKStepper.tsx).
 * api()   : REST API JSON yang dipanggil komponen tersebut.
 *
 * Routing API (lihat .htaccess): /api/bak/<path> ->
 *   index.php?page=bak&action=api&path=<path>
 *
 *   GET  /api/bak/max-potential-earnings
 *   GET  /api/bak/seller-input
 *   POST /api/bak/seller-input
 *   GET  /api/bak/actual-input
 *   POST /api/bak/actual-input
 *   POST /api/bak/actual-input/{id}/approve
 *
 *   Absensi (Step 1A–1C):
 *   GET  /api/bak/attendance?period=YYYY-MM
 *   GET  /api/bak/attendance/periods
 *   POST /api/bak/attendance            (multipart file, atau JSON {text, filename, period})
 *   POST /api/bak/attendance/preview    (parse saja, belum disimpan)
 *   POST /api/bak/attendance/prices     {period, prices:{posisi:harga}}
 *   POST /api/bak/attendance/positions  {period, positions:[...]}
 *   POST /api/bak/attendance/sync       {period} -> sinkron ke Max Potential Earnings
 *   POST /api/bak/attendance/remove     {period}
 */
class BAKController
{
    /**
     * Alasan variance Step 2 — harus sama dengan SELLER_VARIANCE_REASONS di frontend,
     * ditambah alasan otomatis dari AUTO_VARIANCE_REASONS (dihasilkan saat angka
     * antar step berbeda, mis. HC/quantity berkurang).
     */
    private const SELLER_REASONS = [
        'Client reduction',
        'Market rate',
        'Contract revision',
        'HC optimization',
        'Other',
        'Quantity shortage',
        'Quantity excess',
        'Price variance',
        'Value variance',
        'HC shortage',
        'HC excess',
    ];

    /** Alasan variance Step 3 — harus sama dengan FINANCE_VARIANCE_REASONS + alasan otomatis. */
    private const FINANCE_REASONS = [
        'Deduction',
        'SLA miss',
        'Discount',
        'Reward',
        'Doc variance',
        'Other',
        'Quantity shortage',
        'Quantity excess',
        'Price variance',
        'Value variance',
        'HC shortage',
        'HC excess',
    ];

    /** Jenis billing rate card — harus sama dengan BILLING_TYPES di frontend. */
    private const BILLING_TYPES = ['Hours', 'Headcount', 'Interaction'];

    /** Ambang yang sama dengan konstanta di BAKStepper.tsx. */
    private const FINANCE_REASON_REQUIRED_PCT = 5.0;
    private const DOC_REQUIRED_PCT            = 15.0;

    /**
     * Halaman utama BAK.
     */
    public function index(): void
    {
        $pageName = 'bak';
        $subPage  = 'bak';

        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/bak/index.php';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Dispatcher REST API JSON.
     */
    public function api(): void
    {
        $path   = trim((string) ($_GET['path'] ?? ''), '/');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $parts  = $path === '' ? [] : explode('/', $path);

        try {
            if ($path === 'max-potential-earnings' && $method === 'GET') {
                $summary = MaxPotentialEarning::summary();
                $this->respond([
                    'items'       => MaxPotentialEarning::all(),
                    'categories'  => $summary['categories'],
                    'grand_total' => $summary['grand_total'],
                ]);
            }

            if ($path === 'max-potential-earnings' && $method === 'POST') {
                $this->saveMaxPotentialEarnings();
                $summary = MaxPotentialEarning::summary();
                $this->respond([
                    'items'       => MaxPotentialEarning::all(),
                    'categories'  => $summary['categories'],
                    'grand_total' => $summary['grand_total'],
                ]);
            }

            if ($path === 'seller-input' && $method === 'GET') {
                // Accrual Input selalu mengikuti rate card Max Potential.
                SellerInput::syncFromMaxPotential();
                $this->respond(['items' => SellerInput::all()]);
            }

            if ($path === 'seller-input' && $method === 'POST') {
                $this->respond(['items' => $this->saveSellerInput()]);
            }

            if ($path === 'seller-input/reset' && $method === 'POST') {
                SellerInput::resetManual();
                $this->respond(['items' => SellerInput::all()]);
            }

            if ($path === 'actual-input' && $method === 'GET') {
                // Nilai acuan Actual Input mengikuti Accrual Input terbaru.
                ActualInput::syncReferences();
                $this->respond(['items' => ActualInput::all()]);
            }

            if ($path === 'actual-input' && $method === 'POST') {
                $this->respond(['items' => $this->saveActualInput()]);
            }

            if ($path === 'actual-input/reset' && $method === 'POST') {
                ActualInput::resetManual();
                ActualInput::syncReferences();
                $this->respond(['items' => ActualInput::all()]);
            }

            /* ---------------------- Absensi (Step 1A–1C) ---------------------- */

            if ($path === 'attendance/periods' && $method === 'GET') {
                $this->respond(['periods' => BakAttendance::periods()]);
            }

            if ($path === 'attendance' && $method === 'GET') {
                $period = $this->attendancePeriod($_GET['period'] ?? null);
                $this->respond([
                    'summary' => BakAttendance::summary($period),
                    'periods' => BakAttendance::periods(),
                ]);
            }

            if ($path === 'attendance/preview' && $method === 'POST') {
                $input  = $this->readAttendanceSource();
                $matrix = \Helpers\SpreadsheetReader::read($input['bytes'], $input['filename']);
                $parsed = BakAttendance::parse($matrix);

                $this->respond([
                    'rows'        => count($parsed['rows']),
                    'period'      => $input['period'] ?: $parsed['period'],
                    'other_codes' => $parsed['other_codes'],
                    'skipped'     => $parsed['skipped'],
                    'sample'      => array_slice($parsed['rows'], 0, 5),
                ]);
            }

            if ($path === 'attendance' && $method === 'POST') {
                $input  = $this->readAttendanceSource();
                $matrix = \Helpers\SpreadsheetReader::read($input['bytes'], $input['filename']);
                $parsed = BakAttendance::parse($matrix);

                $period = $input['period'] ?: $parsed['period'];
                if (!$period) {
                    throw new \InvalidArgumentException(
                        'Periode tidak bisa ditentukan dari berkas. Pilih periode secara manual.'
                    );
                }

                $summary = BakAttendance::save(
                    $period,
                    $parsed['rows'],
                    $input['filename'],
                    (string) ($this->currentUsername() ?? '')
                );
                $summary['other_codes'] = $parsed['other_codes'];

                $this->respond([
                    'summary' => $summary,
                    'periods' => BakAttendance::periods(),
                ]);
            }

            if ($path === 'attendance/prices' && $method === 'POST') {
                $body   = $this->readJsonBody();
                $period = $this->attendancePeriod($body['period'] ?? null);
                $prices = $body['prices'] ?? [];

                if (!is_array($prices)) {
                    throw new \InvalidArgumentException('Field "prices" harus berupa objek posisi => harga.');
                }

                BakAttendance::savePrices($period, $prices);

                $this->respond(['summary' => BakAttendance::summary($period)]);
            }

            if ($path === 'attendance/positions' && $method === 'POST') {
                $body      = $this->readJsonBody();
                $period    = $this->attendancePeriod($body['period'] ?? null);
                $positions = $body['positions'] ?? [];

                if (!is_array($positions)) {
                    throw new \InvalidArgumentException('Field "positions" harus berupa array.');
                }

                BakAttendance::addPositions(
                    $period,
                    $positions,
                    $this->nullableString($body['category'] ?? null)
                );

                $this->respond(['summary' => BakAttendance::summary($period)]);
            }

            if ($path === 'attendance/employees' && $method === 'POST') {
                $body    = $this->readJsonBody();
                $period  = $this->attendancePeriod($body['period'] ?? null);
                $entries = $body['entries'] ?? [];

                if (!is_array($entries)) {
                    throw new \InvalidArgumentException('Field "entries" harus berupa array.');
                }

                $this->respond(['summary' => BakAttendance::saveEmployeeDays($period, $entries)]);
            }

            if ($path === 'attendance/sync' && $method === 'POST') {
                $body   = $this->readJsonBody();
                $period = $this->attendancePeriod($body['period'] ?? null);

                $result = BakAttendance::syncToMaxPotential($period);
                $result['summary'] = BakAttendance::summary($period);

                $this->respond($result);
            }

            if ($path === 'attendance/remove' && $method === 'POST') {
                $body   = $this->readJsonBody();
                $period = $this->attendancePeriod($body['period'] ?? null);

                BakAttendance::remove($period);

                $this->respond([
                    'summary' => BakAttendance::summary($period),
                    'periods' => BakAttendance::periods(),
                ]);
            }

            if (
                $method === 'POST'
                && count($parts) === 3
                && $parts[0] === 'actual-input'
                && $parts[2] === 'approve'
            ) {
                $id = filter_var($parts[1], FILTER_VALIDATE_INT);
                if ($id === false || $id <= 0) {
                    throw new \InvalidArgumentException('id actual input tidak valid.');
                }

                $body   = $this->readJsonBody();
                $status = isset($body['status'])
                    ? strtoupper((string) $body['status'])
                    : ActualInput::STATUS_LOCKED;

                $this->respond(ActualInput::setStatus((int) $id, $status));
            }

            $this->fail("Endpoint tidak ditemukan: {$method} /{$path}", 404);
        } catch (\InvalidArgumentException $e) {
            $this->fail($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            $this->fail($e->getMessage(), 409);
        } catch (\Throwable $e) {
            $this->fail('Kesalahan server: ' . $e->getMessage(), 500);
        }
    }

    /* --------------------------------------------------------------------- */
    /* Handlers                                                              */
    /* --------------------------------------------------------------------- */

    /**
     * Perbarui price/unit, billing type & Max Potential manual pada rate card,
     * sekaligus menambah role baru bila item dikirim tanpa id.
     *
     * Subtotal (hasil hitung WFM) selalu dihitung ulang di server dari
     * price × rate; rate sendiri berasal dari sinkron absensi.
     */
    private function saveMaxPotentialEarnings(): void
    {
        $db    = \Config\Database::getInstance();
        $items = $this->readItems();

        $db->beginTransaction();
        try {
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException("Item #{$index} bukan objek.");
                }

                $billingType = $this->nullableString($item['billing_type'] ?? null);
                if ($billingType !== null && !in_array($billingType, self::BILLING_TYPES, true)) {
                    throw new \InvalidArgumentException("Item #{$index}: billing_type \"{$billingType}\" tidak dikenal.");
                }

                $price  = $this->nullableDecimal($item['price_per_unit'] ?? null, "Item #{$index}: price_per_unit");
                $manual = array_key_exists('manual_amount', $item)
                    ? $this->nullableDecimal($item['manual_amount'], "Item #{$index}: manual_amount")
                    : false;
                $rate = array_key_exists('manual_rate', $item)
                    ? $this->nullableDecimal($item['manual_rate'], "Item #{$index}: manual_rate")
                    : false;

                $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT);
                $hasId = $id !== false && $id !== null && $id > 0;

                // Hapus role (baris accrual & actual ikut terhapus via cascade).
                if (!empty($item['delete'])) {
                    if (!$hasId) {
                        continue;
                    }
                    MaxPotentialEarning::delete((int) $id);
                    continue;
                }

                if (!$hasId) {
                    // Tanpa id -> role baru yang ditambahkan manual.
                    $role = trim((string) ($item['role'] ?? ''));
                    MaxPotentialEarning::create(
                        $role,
                        $price,
                        $manual === false ? null : $manual,
                        $billingType,
                        $this->nullableString($item['category'] ?? null),
                        $rate === false ? null : $rate
                    );
                    continue;
                }

                MaxPotentialEarning::update((int) $id, $price, null, $billingType, $manual, $rate);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Simpan (upsert) baris seller input.
     *
     * @return array<int, array>
     */
    private function saveSellerInput(): array
    {
        $db    = \Config\Database::getInstance();
        $items = $this->readItems();

        $db->beginTransaction();
        try {
            $saved = [];
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException("Item #{$index} bukan objek.");
                }

                $maxPotentialId = filter_var($item['max_potential_id'] ?? null, FILTER_VALIDATE_INT);
                if ($maxPotentialId === false || $maxPotentialId <= 0) {
                    throw new \InvalidArgumentException("Item #{$index}: max_potential_id tidak valid.");
                }

                $sellerInput = $this->nullableDecimal($item['seller_input'] ?? null, "Item #{$index}: seller_input");
                $quantity    = $this->nullableDecimal($item['quantity'] ?? null, "Item #{$index}: quantity");
                $pricePerUnit = $this->nullableDecimal($item['price_per_unit'] ?? null, "Item #{$index}: price_per_unit");
                $reason      = $this->nullableString($item['variance_reason'] ?? null);
                if ($reason !== null && !in_array($reason, self::SELLER_REASONS, true)) {
                    throw new \InvalidArgumentException("Item #{$index}: variance_reason \"{$reason}\" tidak dikenal.");
                }

                $saved[] = SellerInput::upsert(
                    (int) $maxPotentialId,
                    $sellerInput,
                    $reason,
                    $this->nullableString($item['notes'] ?? null),
                    $quantity,
                    $pricePerUnit
                );
            }

            // Commit hanya bila seluruh item valid, supaya batch tidak
            // tersimpan sebagian lalu gagal di tengah jalan.
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $saved;
    }

    /**
     * Simpan (upsert) baris actual input.
     *
     * @return array<int, array>
     */
    private function saveActualInput(): array
    {
        $db    = \Config\Database::getInstance();
        $items = $this->readItems();

        $db->beginTransaction();
        try {
            $saved = [];
            foreach ($items as $index => $item) {
                if (!is_array($item)) {
                    throw new \InvalidArgumentException("Item #{$index} bukan objek.");
                }

                $sellerInputId = $this->resolveSellerInputId($item, $index);
                $seller        = SellerInput::find($sellerInputId);
                if ($seller === null) {
                    throw new \InvalidArgumentException("Item #{$index}: seller_input_id {$sellerInputId} tidak ditemukan.");
                }

                $actualInput = $this->nullableDecimal($item['actual_input'] ?? null, "Item #{$index}: actual_input");
                $reason      = $this->nullableString($item['variance_reason_finance'] ?? null);
                if ($reason !== null && !in_array($reason, self::FINANCE_REASONS, true)) {
                    throw new \InvalidArgumentException("Item #{$index}: variance_reason_finance \"{$reason}\" tidak dikenal.");
                }
                $docs     = $this->nullableString($item['supporting_docs'] ?? null);
                $quantity = $this->nullableDecimal($item['quantity'] ?? null, "Item #{$index}: quantity");
                $actPrice = $this->nullableDecimal($item['price_per_unit'] ?? null, "Item #{$index}: price_per_unit");
                $notes    = $this->nullableString($item['notes'] ?? null);

                $status = strtoupper((string) ($item['status'] ?? ActualInput::STATUS_DRAFT));
                if (!in_array($status, ActualInput::WRITABLE_STATUSES, true)) {
                    // LOCKED hanya boleh lewat endpoint approve.
                    $status = ActualInput::STATUS_DRAFT;
                }

                // Aturan yang sama dengan validasi frontend — dijaga juga di server
                // supaya tidak bisa dilewati lewat request langsung.
                $sellerValue = $seller['seller_input'] === null ? null : (float) $seller['seller_input'];
                if ($actualInput !== null && $sellerValue !== null && $sellerValue !== 0.0) {
                    $pct = abs((($actualInput - $sellerValue) / $sellerValue) * 100);

                    if ($pct > self::FINANCE_REASON_REQUIRED_PCT && $reason === null) {
                        throw new \InvalidArgumentException(
                            "Item #{$index}: variance " . round($pct, 1) . '% > ' . self::FINANCE_REASON_REQUIRED_PCT
                            . '% — variance_reason_finance wajib diisi.'
                        );
                    }
                    if ($pct > self::DOC_REQUIRED_PCT && $docs === null) {
                        throw new \InvalidArgumentException(
                            "Item #{$index}: variance " . round($pct, 1) . '% > ' . self::DOC_REQUIRED_PCT
                            . '% — supporting_docs wajib diisi.'
                        );
                    }
                }

                $saved[] = ActualInput::upsert($sellerInputId, $actualInput, $reason, $docs, $status, $quantity, $notes, $actPrice);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        return $saved;
    }

    /**
     * Tentukan seller_input_id dari item actual input.
     *
     * seller_input_id dipakai bila valid; kalau tidak, dicocokkan lewat
     * max_potential_id (dikirim frontend saat baris seller belum punya id).
     */
    private function resolveSellerInputId(array $item, int $index): int
    {
        $sellerInputId = filter_var($item['seller_input_id'] ?? null, FILTER_VALIDATE_INT);
        if ($sellerInputId !== false && $sellerInputId > 0) {
            return (int) $sellerInputId;
        }

        $maxPotentialId = filter_var($item['max_potential_id'] ?? null, FILTER_VALIDATE_INT);
        if ($maxPotentialId !== false && $maxPotentialId > 0) {
            $seller = SellerInput::findByMaxPotential((int) $maxPotentialId);
            if ($seller !== null) {
                return (int) $seller['id'];
            }
        }

        throw new \InvalidArgumentException(
            "Item #{$index}: seller_input_id tidak valid dan tidak bisa dicocokkan lewat max_potential_id."
        );
    }

    /* --------------------------------------------------------------------- */
    /* Helpers                                                               */
    /* --------------------------------------------------------------------- */

    /**
     * Baca & validasi array "items" dari body JSON.
     */
    private function readItems(): array
    {
        $body  = $this->readJsonBody();
        $items = $body['items'] ?? null;

        if (!is_array($items) || $items === []) {
            throw new \InvalidArgumentException('Field "items" wajib diisi dan tidak boleh kosong.');
        }

        return $items;
    }

    /**
     * Periode absensi dari request; jatuh ke bulan berjalan bila kosong.
     */
    private function attendancePeriod($value): string
    {
        $period = trim((string) ($value ?? ''));
        if ($period === '') {
            return date('Y-m');
        }
        if (!preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            throw new \InvalidArgumentException('Periode harus berformat YYYY-MM.');
        }
        if ((int) $m[1] < 2000 || (int) $m[1] > 2100 || (int) $m[2] < 1 || (int) $m[2] > 12) {
            throw new \InvalidArgumentException('Periode di luar rentang yang wajar.');
        }

        return $m[1] . '-' . $m[2];
    }

    /**
     * Sumber berkas absensi: unggahan multipart, atau teks tempel (paste).
     *
     * @return array{bytes:string, filename:string, period:string}
     */
    private function readAttendanceSource(): array
    {
        $maxBytes = 8 * 1024 * 1024;

        // 1) Unggahan berkas (.xlsx/.csv)
        if (!empty($_FILES['file']) && is_array($_FILES['file'])) {
            $file = $_FILES['file'];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Berkas gagal diunggah (kode ' . (int) $file['error'] . ').');
            }
            if (($file['size'] ?? 0) > $maxBytes) {
                throw new \InvalidArgumentException('Ukuran berkas melebihi 8 MB.');
            }

            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                throw new \InvalidArgumentException('Berkas unggahan tidak valid.');
            }

            $bytes = (string) file_get_contents($tmp);
            if ($bytes === '') {
                throw new \InvalidArgumentException('Berkas kosong.');
            }

            return [
                'bytes'    => $bytes,
                'filename' => (string) ($file['name'] ?? 'absensi'),
                'period'   => $this->attendancePeriod($_POST['period'] ?? null),
            ];
        }

        // 2) Teks tempel dari Excel (dipakai bila tidak ada berkas)
        $body = $this->readJsonBody();
        $text = (string) ($body['text'] ?? '');

        if (trim($text) === '') {
            throw new \InvalidArgumentException('Tidak ada berkas maupun teks absensi yang dikirim.');
        }
        if (strlen($text) > $maxBytes) {
            throw new \InvalidArgumentException('Teks absensi terlalu besar (maks 8 MB).');
        }

        return [
            'bytes'    => $text,
            'filename' => (string) ($body['filename'] ?? 'paste-absensi.txt'),
            'period'   => $this->attendancePeriod($body['period'] ?? null),
        ];
    }

    /**
     * Username yang sedang login (null bila tidak ada sesi).
     */
    private function currentUsername(): ?string
    {
        $user = \Services\AuthService::user();
        if (!is_array($user)) {
            return null;
        }
        $name = trim((string) ($user['username'] ?? ''));

        return $name === '' ? null : $name;
    }

    /**
     * Body request sebagai array (kosong bila body tidak ada).
     */
    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Body request bukan JSON yang valid.');
        }

        return $decoded;
    }

    /**
     * Angka non-negatif, atau null bila kosong.
     */
    private function nullableDecimal($value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("{$label} harus berupa angka.");
        }

        $number = (float) $value;
        if ($number < 0) {
            throw new \InvalidArgumentException("{$label} tidak boleh negatif.");
        }

        return round($number, 2);
    }

    /**
     * String yang di-trim, atau null bila kosong.
     */
    private function nullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Kirim response sukses lalu hentikan request.
     */
    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['success' => true, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    /**
     * Kirim response error lalu hentikan request.
     */
    private function fail(string $message, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['success' => false, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }
}
