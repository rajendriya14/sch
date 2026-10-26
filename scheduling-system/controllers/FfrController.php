<?php
// Controller Family Financial Report (pencatatan keuangan bulanan).
namespace Controllers;

use Helpers\FormHelper;
use Helpers\MoneyHelper;
use Models\FfrBucket;
use Models\FfrCategory;
use Models\FfrIncome;
use Models\FfrItem;
use Models\FfrPeriod;
use Models\FfrSavingsAsset;
use Models\FfrGoldPrice;
use Models\FfrSavingsSnapshot;
use Models\FfrSavingsTransaction;
use Models\FfrSavingsType;
use Services\FfrService;
use Services\GoldPriceService;
use Services\SavingsService;
use Services\SavingsTransactionService;

class FfrController
{
    private FfrService $service;

    public function __construct()
    {
        $this->service = new FfrService();
    }

    /** Nama modul — dipakai di navbar halaman FFR. */
    private const APP_LABEL = 'Family Financial Report';

    /**
     * Render halaman FFR.
     *
     * FFR tampil mandiri: tanpa sidebar Workforce, nama navbar memakai nama
     * modul, dan brand diarahkan kembali ke Portal (satu-satunya jalan keluar
     * karena sidebar disembunyikan).
     */
    private function render(string $view, array $vars = []): void
    {
        $hideSidebar = true;
        $brandTitle  = self::APP_LABEL;
        $brandIcon   = 'bi-piggy-bank';
        $brandUrl    = 'index.php?page=portal';
        $appLabel    = self::APP_LABEL;
        $pageName    = 'ffr';

        // EXTR_SKIP: variabel view tidak boleh menimpa pengaturan layout di atas.
        extract($vars, EXTR_SKIP);

        require __DIR__ . '/../views/layouts/header.php';
        require __DIR__ . '/../views/ffr/' . $view . '.php';
        require __DIR__ . '/../views/layouts/footer.php';
    }

    /**
     * Ambil periode dari request: mendukung pilihan terpisah (month + year)
     * dari pemilih di halaman, dan tetap menerima 'period' (YYYY-MM) untuk
     * form-form POST yang mengirim periode tersembunyi.
     */
    private function periodFromRequest(array $src): array
    {
        if (isset($src['month']) || isset($src['year'])) {
            $period = $this->service->periodFromParts($src['month'] ?? null, $src['year'] ?? null);
        } else {
            $period = $this->service->normalizePeriod($src['period'] ?? null);
        }

        return $this->service->getOrCreatePeriod($period);
    }

    /**
     * Dashboard — ringkasan bulan + trend + rekomendasi singkat.
     */
    public function index(): void
    {
        $period = $this->periodFromRequest($_GET);
        $id = (int) $period['id'];

        $dashboard    = $this->service->dashboard($id);
        $trend        = $this->service->trend($id, 6);
        $comparison   = $this->service->categoryComparison($id);
        $topItems     = $this->service->topItems($id, 5);
        $insights     = $this->service->insights($id);
        $recommend    = $this->service->recommendations($id);
        $periodLabel  = $this->service->periodLabelLong($period['period']);
        $periodOptions = $this->service->periodOptions();
        $hasAnyData   = $dashboard['income'] > 0 || $dashboard['plan_total'] > 0;

        $this->render('dashboard', compact(
            'period', 'periodOptions', 'dashboard', 'trend', 'comparison',
            'topItems', 'insights', 'recommend', 'periodLabel', 'hasAnyData'
        ));
    }

    /**
     * Rencana Bulan — input pemasukan + alokasi dana ke setiap bucket.
     */
    public function plan(): void
    {
        $isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');

        // Saat menyimpan, periode harus diambil dari data POST (hidden field),
        // bukan dari query string form action — kalau tidak, simpanan selalu
        // jatuh ke bulan berjalan.
        $period = $this->periodFromRequest($isPost ? $_POST : $_GET);
        $id = (int) $period['id'];

        if ($isPost) {
            $this->handlePlanSave($period);
            return;
        }

        $incomes   = FfrIncome::byPeriod($id);
        $summaries = $this->service->bucketSummaries($id);
        $total     = FfrIncome::total($id);
        $allocated = \Models\FfrPlan::total($id);

        // Peta alokasi: nominal, persen, dan sumber input terakhir per bucket.
        $allocation = \Models\FfrPlan::mapAllocation($id);
        $saving     = (float) \Models\FfrPlan::totalSaving($id);

        // Deteksi pemasukan berubah: alokasi yang tersimpan sebagai persen
        // akan menghasilkan nominal berbeda bila dihitung dari pemasukan kini.
        $recalcNeeded = false;
        $recalcInfo   = [];
        foreach ($allocation as $bucketId => $row) {
            if ($row['source'] !== 'percent' || $row['percent'] === null) {
                continue;
            }
            $expected = (float) round($row['percent'] / 100 * $total);
            if (abs($expected - $row['amount']) >= 1) {
                $recalcNeeded = true;
                $recalcInfo[$bucketId] = [
                    'stored'   => $row['amount'],
                    'expected' => $expected,
                    'percent'  => $row['percent'],
                ];
            }
        }

        $periodLabel   = $this->service->periodLabelLong($period['period']);
        $periodOptions = $this->service->periodOptions();
        $prevLabel     = $this->service->previousPeriodLabel($period['period']);

        $this->render('plan', compact(
            'period', 'periodOptions', 'incomes', 'summaries',
            'total', 'allocated', 'periodLabel', 'prevLabel',
            'allocation', 'saving', 'recalcNeeded', 'recalcInfo'
        ));
    }

    /**
     * Simpan pemasukan + alokasi bucket (tombol Simpan Rencana).
     */
    private function handlePlanSave(array $period): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }

        if ($this->service->isClosed($period)) {
            FormHelper::setFlash('Bulan ini sudah ditutup buku — buka dulu untuk mengubah rencana.', 'warning');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }

        $id = (int) $period['id'];

        // --- Pemasukan: baris dikirim sebagai array ---
        $savedIncome = $this->saveIncomeRows($id);
        $unnamed = $this->lastUnnamedIncomeCount;

        // --- Alokasi bucket ---
        // Tiap baris mengirim tiga nilai: nominal rupiah, persen, dan sumber
        // input terakhir ('rp' atau 'percent'). Server menghitung ulang angka
        // final supaya tidak bergantung pada pembulatan JavaScript.
        $incomeTotal = FfrIncome::total($id);
        $allocations = $_POST['allocation'] ?? [];
        $percents    = $_POST['allocation_pct'] ?? [];
        $sources     = $_POST['allocation_src'] ?? [];
        $existing    = \Models\FfrPlan::mapAllocation($id);

        foreach ($allocations as $bucketId => $rawAmount) {
            $bucketId = (int) $bucketId;
            $source   = (string) ($sources[$bucketId] ?? 'rp');
            $source   = in_array($source, ['rp', 'percent'], true) ? $source : 'rp';
            $percent  = $this->parseDecimal($percents[$bucketId] ?? '');
            $amountIn = trim((string) $rawAmount);

            // Kolom kosong = jangan sentuh nilai lama. Ini yang membuat tombol
            // "Hapus Isi" aman: mengosongkan layar tidak menghapus data
            // tersimpan. Untuk benar-benar menjadikan 0, ketik angka 0.
            if ($amountIn === '' && $percent <= 0) {
                continue;
            }

            if ($source === 'percent' && $incomeTotal > 0) {
                // Persen yang menang; nominal rupiah dihitung dari pemasukan.
                $amount = (float) round($percent / 100 * $incomeTotal);
            } else {
                // Tanpa pemasukan, persen tidak bisa dipakai sebagai acuan —
                // nominal rupiah dipegang supaya alokasi lama tidak hilang.
                if ($source === 'percent') {
                    $source = 'rp';
                }
                $amount  = $this->parseAmount($amountIn);
                $percent = $incomeTotal > 0
                    ? round($amount / $incomeTotal * 100, 2)
                    : ($existing[$bucketId]['percent'] ?? null);
            }

            \Models\FfrPlan::setAllocation($id, $bucketId, $amount, $percent, $source);
        }

        $over = array_sum(array_map(
            fn($key) => $this->parseDecimal($percents[$key] ?? ''),
            array_keys($allocations)
        ));
        $warning = $over > 100.01 ? ' Total persen ' . number_format($over, 2, ',', '.') . '% melebihi 100%.' : '';
        $note    = $unnamed > 0 ? ' ' . $unnamed . ' baris tanpa nama disimpan sebagai "Pemasukan".' : '';

        FormHelper::setFlash('Rencana bulan ' . $this->service->periodLabelLong($period['period']) . ' tersimpan (' . $savedIncome . ' pemasukan).' . $note . $warning, $over > 100.01 ? 'warning' : 'success');
        $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
    }

    /** Jumlah baris pemasukan terakhir yang namanya kosong (otomatis diberi nama). */
    private int $lastUnnamedIncomeCount = 0;

    /**
     * Tulis ulang seluruh baris pemasukan satu periode.
     *
     * Baris dengan jumlah > 0 tapi nama kosong tidak lagi dibuang — dulu baris
     * seperti itu hilang begitu saja saat disimpan. Sekarang namanya diisi
     * "Pemasukan" supaya uang yang sudah diketik tetap tercatat.
     *
     * @return int jumlah baris yang tersimpan
     */
    private function saveIncomeRows(int $periodId): int
    {
        FfrIncome::clearPeriod($periodId);
        $sources = $_POST['income_source'] ?? [];
        $amounts = $_POST['income_amount'] ?? [];
        $owners  = $_POST['income_owner'] ?? [];
        $saved   = 0;
        $unnamed = 0;

        foreach ($amounts as $i => $rawAmount) {
            $amount = $this->parseAmount($rawAmount);
            if ($amount <= 0) {
                continue;
            }

            $source = trim((string) ($sources[$i] ?? ''));
            if ($source === '') {
                $source = 'Pemasukan';
                $unnamed++;
            }

            FfrIncome::add($periodId, $source, $amount, $owners[$i] ?? 'Bersama');
            $saved++;
        }

        $this->lastUnnamedIncomeCount = $unnamed;
        return $saved;
    }

    /**
     * Simpan pemasukan saja (tombol "Simpan Pemasukan").
     *
     * Alokasi dana tidak disentuh, jadi angka yang sudah tersimpan tetap utuh
     * saat pengguna baru menyelesaikan pengisian pemasukan.
     */
    public function saveIncome(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect('index.php?page=ffr&action=plan');
        }

        $period = $this->periodFromRequest($_POST);
        if ($this->service->isClosed($period)) {
            FormHelper::setFlash('Bulan ini sudah ditutup buku — buka dulu untuk mengubah pemasukan.', 'warning');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }

        $id   = (int) $period['id'];
        $rows = $this->saveIncomeRows($id);
        $note = $this->lastUnnamedIncomeCount > 0
            ? ' ' . $this->lastUnnamedIncomeCount . ' baris tanpa nama disimpan sebagai "Pemasukan".'
            : '';

        FormHelper::setFlash(
            $rows > 0
                ? 'Pemasukan tersimpan (' . $rows . ' baris, total ' . MoneyHelper::rupiah(FfrIncome::total($id)) . ').' . $note
                : 'Pemasukan dikosongkan.',
            'success'
        );
        $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
    }

    /**
     * Hitung ulang seluruh alokasi dari persen tersimpan.
     *
     * Dipakai banner "Pemasukan berubah — hitung ulang dari %?" di layar
     * Rencana Bulan. Hanya baris yang tersimpan sebagai persen yang dihitung
     * ulang; baris yang dikunci sebagai nominal rupiah dibiarkan apa adanya.
     */
    public function recalcPlan(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect('index.php?page=ffr&action=plan');
        }

        $period = $this->periodFromRequest($_POST);
        if ($this->service->isClosed($period)) {
            FormHelper::setFlash('Bulan ini sudah ditutup buku — buka dulu untuk mengubah rencana.', 'warning');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }

        $id     = (int) $period['id'];
        $income = FfrIncome::total($id);
        $peta   = \Models\FfrPlan::mapAllocation($id);
        $count  = 0;

        foreach ($peta as $bucketId => $row) {
            if ($row['source'] !== 'percent' || $row['percent'] === null) {
                continue;
            }
            $amount = (float) round($row['percent'] / 100 * $income);
            \Models\FfrPlan::setAllocation($id, (int) $bucketId, $amount, $row['percent'], 'percent');
            $count++;
        }

        FormHelper::setFlash(
            $count > 0
                ? 'Alokasi dihitung ulang dari persen untuk ' . $count . ' kelompok.'
                : 'Tidak ada alokasi berbasis persen untuk dihitung ulang.',
            $count > 0 ? 'success' : 'warning'
        );
        $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
    }

    /**
     * Kunci seluruh alokasi sebagai nominal rupiah.
     *
     * Tombol "Biarkan" pada banner: nominal yang tampil sekarang dipertahankan
     * apa adanya, sedangkan persen menjadi angka turunan — jadi tidak lagi
     * memicu hitung ulang saat pemasukan berubah.
     */
    public function lockPlan(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect('index.php?page=ffr&action=plan');
        }

        $period = $this->periodFromRequest($_POST);
        if ($this->service->isClosed($period)) {
            FormHelper::setFlash('Bulan ini sudah ditutup buku — buka dulu untuk mengubah rencana.', 'warning');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }

        $id     = (int) $period['id'];
        $income = FfrIncome::total($id);
        $peta   = \Models\FfrPlan::mapAllocation($id);

        foreach ($peta as $bucketId => $row) {
            // Nominal yang tersimpan dipertahankan apa adanya; persen hanya
            // dihitung ulang dari pemasukan sekarang agar tampilannya konsisten.
            $amount  = $row['amount'];
            $percent = $income > 0 ? round($amount / $income * 100, 2) : 0.0;
            \Models\FfrPlan::setAllocation($id, (int) $bucketId, $amount, $percent, 'rp');
        }

        FormHelper::setFlash('Alokasi dikunci sebagai nominal rupiah. Persen menjadi angka turunan.', 'success');
        $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
    }

    /**
     * Daftar Belanja per bucket.
     */
    public function items(): void
    {
        $period = $this->periodFromRequest($_GET);
        $id = (int) $period['id'];

        $buckets = FfrBucket::all();
        $bucketId = (int) ($_GET['bucket'] ?? 0);
        if ($bucketId === 0 && $buckets) {
            $bucketId = (int) $buckets[0]['id'];
        }

        $detail = $this->service->bucketDetail($id, $bucketId);
        if (!$detail) {
            FormHelper::setFlash('Bucket tidak ditemukan.', 'warning');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
            return;
        }

        $summaries     = $this->service->bucketSummaries($id);
        $catalog       = FfrCategory::all();
        $periodLabel   = $this->service->periodLabelLong($period['period']);
        $periodOptions = $this->service->periodOptions();
        $isClosed      = $this->service->isClosed($period);

        $this->render('items', compact(
            'period', 'periodOptions', 'buckets', 'detail', 'summaries',
            'catalog', 'periodLabel', 'isClosed'
        ));
    }

    /**
     * Simpan item baru atau ubah item yang sudah ada.
     */
    public function saveItem(): void
    {
        $period = $this->periodFromRequest($_POST);
        $id = (int) $period['id'];
        $bucketId = (int) ($_POST['bucket_id'] ?? 0);
        $back = 'index.php?page=ffr&action=items&period=' . $period['period'] . '&bucket=' . $bucketId;

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }
        if ($this->service->isClosed($period)) {
            FormHelper::setFlash('Bulan ini sudah ditutup buku.', 'warning');
            $this->redirect($back);
        }

        // Kategori boleh diketik langsung (jalan pintas membuat kategori baru).
        $categoryId = $_POST['category_id'] ?? '';
        if (($categoryId === '' || $categoryId === 'baru') && !empty($_POST['category_new'])) {
            $categoryId = FfrCategory::findOrCreate((string) $_POST['category_new']) ?? '';
        }

        $data = [
            'period_id'     => $id,
            'bucket_id'     => $bucketId,
            'category_id'   => $categoryId === '' ? null : (int) $categoryId,
            'name'          => $_POST['name'] ?? '',
            'freq_type'     => $_POST['freq_type'] ?? 'monthly',
            'unit_price'    => $this->parseAmount($_POST['unit_price'] ?? '0'),
            'qty'           => (float) str_replace(',', '.', (string) ($_POST['qty'] ?? '1')),
            'actual_amount' => $this->parseOptionalAmount($_POST['actual_amount'] ?? ''),
            'is_priority'   => !empty($_POST['is_priority']) ? 1 : 0,
            'notes'         => $_POST['notes'] ?? '',
            'sort_order'    => (int) ($_POST['sort_order'] ?? 0),
        ];

        if (trim((string) $data['name']) === '') {
            FormHelper::setFlash('Nama item wajib diisi.', 'danger');
            $this->redirect($back);
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            FfrItem::update($itemId, $data);
            FormHelper::setFlash('Item "' . $data['name'] . '" diperbarui.', 'success');
        } else {
            FfrItem::create($data);
            FormHelper::setFlash('Item "' . $data['name'] . '" ditambahkan.', 'success');
        }

        $this->redirect($back);
    }

    /**
     * Catat realisasi cepat (aktual) langsung dari tabel tanpa buka form.
     */
    public function updateActual(): void
    {
        header('Content-Type: application/json');

        // Endpoint AJAX: token dikirim lewat header, bukan field form.
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sesi form kedaluwarsa. Muat ulang halaman.']);
            exit;
        }

        $itemId = (int) ($_POST['item_id'] ?? 0);
        $item = FfrItem::find($itemId);
        if (!$item) {
            echo json_encode(['success' => false, 'error' => 'Item tidak ditemukan.']);
            exit;
        }

        $period = FfrPeriod::find((int) $item['period_id']);
        if ($period && $this->service->isClosed($period)) {
            echo json_encode(['success' => false, 'error' => 'Bulan ini sudah ditutup buku.']);
            exit;
        }

        $actual = $this->parseOptionalAmount($_POST['actual_amount'] ?? '');

        FfrItem::update($itemId, [
            'bucket_id'     => (int) $item['bucket_id'],
            'category_id'   => $item['category_id'],
            'name'          => $item['name'],
            'freq_type'     => $item['freq_type'],
            'unit_price'    => $item['unit_price'],
            'qty'           => $item['qty'],
            'actual_amount' => $actual,
            'is_priority'   => $item['is_priority'],
            'notes'         => $item['notes'],
            'sort_order'    => $item['sort_order'],
        ]);

        // Kirim balik angka hasil hitung ulang supaya halaman bisa langsung
        // memperbarui status, sisa dana, dan total tanpa perlu refresh.
        $periodId = (int) $item['period_id'];
        $bucketId = (int) $item['bucket_id'];
        $detail   = $this->service->bucketDetail($periodId, $bucketId);
        $summaries = $this->service->bucketSummaries($periodId);

        $fresh = null;
        foreach ($detail['items'] ?? [] as $row) {
            if ((int) $row['id'] === $itemId) {
                $fresh = $row;
                break;
            }
        }

        $buckets = [];
        foreach ($summaries as $s) {
            $buckets[] = [
                'id'          => (int) $s['bucket']['id'],
                'plan_total'  => $s['plan_total'],
                'plan_label'  => MoneyHelper::short($s['plan_total']),
                'plan_title'  => MoneyHelper::rupiah($s['plan_total']),
                'badge'       => $s['status']['badge'],
                'status'      => $s['status']['label'],
            ];
        }

        $allocated = (float) ($detail['allocated'] ?? 0);
        $planTotal = (float) ($detail['plan_total'] ?? 0);
        $remaining = (float) ($detail['remaining'] ?? 0);
        $actualTotal = (float) ($detail['actual_total'] ?? 0);
        $actualPct = $allocated > 0
            ? min(100, MoneyHelper::percent($actualTotal, $allocated, 1))
            : ($actualTotal > 0 ? 100 : 0);

        echo json_encode([
            'success' => true,
            'item'    => [
                'id'              => $itemId,
                'actual_amount'   => $actual,
                'actual_display'  => $actual === null ? '' : MoneyHelper::rupiah($actual, false),
                'status'          => $fresh['status'] ?? $this->service->itemStatus($item),
            ],
            'bucket'  => [
                'id'           => $bucketId,
                'allocated'    => $allocated,
                'plan_total'   => $planTotal,
                'actual_total' => $actualTotal,
                'actual_pct'   => $actualPct,
                'remaining'    => $remaining,
                'percent'      => $allocated > 0
                    ? min(100, MoneyHelper::percent($planTotal, $allocated, 1))
                    : ($planTotal > 0 ? 100 : 0),
                'status'       => $detail['status'] ?? $this->service->bucketStatus($allocated, $planTotal),
                'empty_alert'  => ($allocated <= 0 && $planTotal > 0),
            ],
            // Footer tabel = total kelompok yang sedang dibuka, bukan semua kelompok.
            'totals'  => ['plan_total' => $planTotal, 'actual_total' => $actualTotal],
            'buckets' => $buckets,
        ]);
        exit;
    }

    public function deleteItem(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $item = FfrItem::find($id);
        $periodLabel = '';

        if ($item) {
            $period = FfrPeriod::find((int) $item['period_id']);
            $periodLabel = $period['period'] ?? '';
            FfrItem::delete($id);
            FormHelper::setFlash('Item "' . $item['name'] . '" dihapus.', 'success');
        }

        $redirect = 'index.php?page=ffr&action=items';
        if ($periodLabel !== '') {
            $redirect .= '&period=' . $periodLabel . '&bucket=' . (int) $item['bucket_id'];
        }
        $this->redirect($redirect);
    }

    /**
     * Pindahkan semua item satu bucket ke bucket lain.
     */
    public function moveBucket(): void
    {
        $period = $this->periodFromRequest($_POST);
        $fromId = (int) ($_POST['from_bucket'] ?? 0);
        $toId = (int) ($_POST['to_bucket'] ?? 0);
        $back = 'index.php?page=ffr&action=items&period=' . $period['period'] . '&bucket=' . $fromId;

        if (!FormHelper::verifyCsrf() || $fromId === 0 || $toId === 0 || $fromId === $toId) {
            FormHelper::setFlash('Pemindahan bucket tidak valid.', 'danger');
            $this->redirect($back);
        }
        if ($this->service->isClosed($period)) {
            FormHelper::setFlash('Bulan ini sudah ditutup buku.', 'warning');
            $this->redirect($back);
        }

        $moved = 0;
        foreach (FfrItem::byPeriod((int) $period['id'], $fromId) as $item) {
            FfrItem::update((int) $item['id'], [
                'bucket_id'     => $toId,
                'category_id'   => $item['category_id'],
                'name'          => $item['name'],
                'freq_type'     => $item['freq_type'],
                'unit_price'    => $item['unit_price'],
                'qty'           => $item['qty'],
                'actual_amount' => $item['actual_amount'],
                'is_priority'   => $item['is_priority'],
                'notes'         => $item['notes'],
                'sort_order'    => $item['sort_order'],
            ]);
            $moved++;
        }

        FormHelper::setFlash($moved . ' item dipindahkan.', 'success');
        $this->redirect('index.php?page=ffr&action=items&period=' . $period['period'] . '&bucket=' . $toId);
    }

    /**
     * Ledger — semua data satu bulan dalam satu halaman.
     */
    public function ledger(): void
    {
        $period = $this->periodFromRequest($_GET);
        $id = (int) $period['id'];

        $ledger = $this->service->ledger($id);
        $periodLabel = $this->service->periodLabelLong($period['period']);
        $isClosed = $this->service->isClosed($period);

        $this->render('ledger', compact('period', 'periodLabel', 'isClosed', 'ledger'));
    }

    /**
     * Ekspor ledger periode terpilih sebagai CSV (bisa dibuka di Excel).
     */
    public function exportLedger(): void
    {
        $period = $this->periodFromRequest($_GET);
        $ledger = $this->service->ledger((int) $period['id']);
        $label  = $this->service->periodLabelLong($period['period']);

        $filename = 'ledger-ffr-' . $period['period'] . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');

        // BOM agar Excel membaca karakter Indonesia dengan benar.
        fwrite($out, "\xEF\xBB\xBF");

        // $escape wajib eksplisit: PHP 8.4 menandai pemanggilan tanpa $escape sebagai deprecated.
        $row = fn(array $cells) => fputcsv($out, $cells, ';', '"', '\\');

        $row(['Ledger Family Financial Report', $label]);
        $row([]);

        $row(['PEMASUKAN']);
        $row(['Sumber', 'Pemilik', 'Jumlah']);
        foreach ($ledger['incomes'] as $income) {
            $row([$income['source'], $income['owner'], (float) $income['amount']]);
        }
        $row(['Total pemasukan', '', $ledger['income']]);
        $row([]);

        foreach ($ledger['sections'] as $section) {
            $row(['KELOMPOK: ' . $section['bucket']['name']]);
            $row(['Dana dialokasikan', $section['allocated']]);
            $row(['Rencana item', $section['plan_total']]);
            $row(['Realisasi', $section['actual_total']]);
            $row([]);
            $row(['No', 'Item', 'Kategori', 'Frekuensi', 'Harga', 'Qty', 'Per Bulan', 'Aktual', 'Selisih', 'Status']);
            $no = 0;
            foreach ($section['items'] as $item) {
                $no++;
                $row([
                    $no,
                    $item['name'],
                    $item['category_name'] ?? '',
                    $item['freq_label'],
                    (float) $item['unit_price'],
                    (float) $item['qty'],
                    $item['monthly_cost'],
                    $item['actual_value'] === null ? '' : $item['actual_value'],
                    $item['variance'] === null ? '' : $item['variance'],
                    $item['status']['label'],
                ]);
            }
            $row(['', 'Subtotal', '', '', '', '', $section['plan_total'], $section['actual_total'], '', '']);
            $row([]);
        }

        $row(['RINGKASAN']);
        $row(['Total pemasukan', $ledger['income']]);
        $row(['Total dialokasikan', $ledger['allocated']]);
        $row(['Belum dialokasikan', $ledger['unallocated']]);
        $row(['Total rencana item', $ledger['plan_total']]);
        $row(['Total realisasi', $ledger['actual_total']]);

        fclose($out);
        exit;
    }

    /**
     * Layar Rekomendasi — opsi tindakan saat rencana melebihi dana.
     */
    public function recommendation(): void
    {
        $period = $this->periodFromRequest($_GET);
        $id = (int) $period['id'];

        $data = $this->service->recommendations($id);

        $periodLabel   = $this->service->periodLabelLong($period['period']);
        $periodOptions = $this->service->periodOptions();

        $this->render('recommendation', compact('period', 'periodLabel', 'periodOptions', 'data'));
    }

    /**
     * Salin rencana + item dari bulan sebelumnya.
     */
    public function copyPrevious(): void
    {
        $period = $this->periodFromRequest($_POST);

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa.', 'danger');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }
        if ($this->service->isClosed($period)) {
            FormHelper::setFlash('Bulan ini sudah ditutup buku.', 'warning');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }

        $result = $this->service->copyFromPrevious((int) $period['id'], $period['period']);

        if ($result['success']) {
            FormHelper::setFlash(
                'Disalin dari bulan lalu: ' . $result['items'] . ' item, ' . $result['plans'] . ' alokasi. Realisasi dikosongkan.',
                'success'
            );
        } else {
            FormHelper::setFlash($result['error'], 'warning');
        }

        $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
    }

    /**
     * Tutup / buka buku bulan.
     */
    public function toggleStatus(): void
    {
        $period = $this->periodFromRequest($_POST);

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa.', 'danger');
            $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
        }

        $newStatus = $this->service->isClosed($period) ? 'open' : 'closed';
        FfrPeriod::setStatus((int) $period['id'], $newStatus);

        FormHelper::setFlash(
            $newStatus === 'closed'
                ? 'Buku ' . $this->service->periodLabelLong($period['period']) . ' ditutup. Perubahan dikunci.'
                : 'Buku ' . $this->service->periodLabelLong($period['period']) . ' dibuka kembali.',
            'success'
        );

        $this->redirect('index.php?page=ffr&action=plan&period=' . $period['period']);
    }

    /**
     * Hapus satu periode beserta seluruh pemasukan, alokasi, dan itemnya.
     */
    public function deletePeriod(): void
    {
        // Sengaja TIDAK memakai periodFromRequest(): periode yang belum ada
        // tidak boleh dibuat hanya untuk dihapus.
        $label  = $this->service->normalizePeriod($_POST['period'] ?? null);
        $period = \Models\FfrPeriod::findByPeriod($label);

        if (!$period || !FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Periode tidak ditemukan atau sesi kedaluwarsa.', 'danger');
            $this->redirect('index.php?page=ffr');
        }

        FfrPeriod::delete((int) $period['id']);
        FormHelper::setFlash('Periode ' . $this->service->periodLabelLong($period['period']) . ' dan seluruh isinya dihapus.', 'success');
        $this->redirect('index.php?page=ffr');
    }

    /**
     * Master: bucket + kategori.
     */
    public function setup(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleSetupSave();
            return;
        }

        $buckets = FfrBucket::all(false);
        $categories = FfrCategory::all(false);

        // Pemilih periode ikut tampil di tab ini, jadi periodenya perlu diisi.
        $period = $this->periodFromRequest($_GET);
        $periodLabel = $this->service->periodLabelLong($period['period']);

        $this->render('setup', compact('buckets', 'categories', 'period', 'periodLabel'));
    }

    private function handleSetupSave(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa.', 'danger');
            $this->redirect('index.php?page=ffr&action=setup');
        }

        $kind = $_POST['kind'] ?? '';

        if ($kind === 'bucket') {
            $data = [
                'code'        => $_POST['code'] ?? '',
                'name'        => $_POST['name'] ?? '',
                'type'        => $_POST['type'] ?? 'wajib',
                'description' => $_POST['description'] ?? '',
                'sort_order'  => $_POST['sort_order'] ?? 0,
            ];
            if (trim((string) $data['code']) === '' || trim((string) $data['name']) === '') {
                FormHelper::setFlash('Kode dan nama bucket wajib diisi.', 'danger');
            } elseif (FfrBucket::findByCode(strtoupper(trim($data['code'])))) {
                FormHelper::setFlash('Kode bucket sudah dipakai.', 'danger');
            } else {
                FfrBucket::create($data);
                FormHelper::setFlash('Bucket "' . $data['name'] . '" ditambahkan.', 'success');
            }
        }

        if ($kind === 'category') {
            $result = FfrCategory::create((string) ($_POST['name'] ?? ''));
            FormHelper::setFlash($result['success'] ? 'Kategori ditambahkan.' : $result['error'], $result['success'] ? 'success' : 'danger');
        }

        $this->redirect('index.php?page=ffr&action=setup');
    }

    public function updateBucket(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa.', 'danger');
            $this->redirect('index.php?page=ffr&action=setup');
        }

        $id = (int) ($_POST['id'] ?? 0);
        FfrBucket::update($id, [
            'name'        => $_POST['name'] ?? '',
            'type'        => $_POST['type'] ?? 'wajib',
            'description' => $_POST['description'] ?? '',
            'sort_order'  => $_POST['sort_order'] ?? 0,
            'is_active'   => !empty($_POST['is_active']),
        ]);

        FormHelper::setFlash('Bucket diperbarui.', 'success');
        $this->redirect('index.php?page=ffr&action=setup');
    }

    public function deleteBucket(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $result = FfrBucket::delete($id);
        FormHelper::setFlash($result['success'] ? 'Bucket dihapus.' : $result['error'], $result['success'] ? 'success' : 'danger');
        $this->redirect('index.php?page=ffr&action=setup');
    }

    public function updateCategory(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa.', 'danger');
            $this->redirect('index.php?page=ffr&action=setup');
        }

        $result = FfrCategory::rename((int) ($_POST['id'] ?? 0), (string) ($_POST['name'] ?? ''));
        FormHelper::setFlash($result['success'] ? 'Kategori diperbarui.' : $result['error'], $result['success'] ? 'success' : 'danger');
        $this->redirect('index.php?page=ffr&action=setup');
    }

    public function deleteCategory(): void
    {
        FfrCategory::delete((int) ($_GET['id'] ?? 0));
        FormHelper::setFlash('Kategori dihapus.', 'success');
        $this->redirect('index.php?page=ffr&action=setup');
    }

    /**
     * Terima angka dari input bertipe teks ("1.500.000" atau "1500000").
     * Pemisah ribuan dibuang, koma diperlakukan sebagai desimal.
     */
    private function parseAmount(mixed $value): float
    {
        $clean = $this->normalizeAmount($value);
        return $clean === null ? 0.0 : $clean;
    }

    /**
     * Angka desimal (mis. berat 0,5 gram). Titik tunggal dianggap desimal
     * kecuali tepat 3 digit di belakangnya (gaya ribuan "1.000").
     */
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
        if (preg_match('/^-?\d*\.(\d+)$/', $clean, $match) && strlen($match[1]) !== 3) {
            return (float) $clean;
        }
        return (float) str_replace('.', '', $clean);
    }

    /**
     * Angka persen (biaya/pajak/kupon). "0,15" dan "0.15" sama-sama 0,15.
     */
    private function parsePercent(mixed $value): float
    {
        $text = trim((string) $value);
        if ($text === '') {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', $text);
        if (strpos($clean, ',') === false
            && substr_count($clean, '.') === 1
            && preg_match('/^\d*\.\d{1,2}$/', $clean)) {
            return (float) $clean;
        }
        return $this->parseAmount($text);
    }

    /**
     * Harga per unit. Obligasi memakai persen nominal dan reksa dana memakai
     * NAB, keduanya boleh desimal (mis. 98,75 atau 1.234,56).
     */
    private function parsePrice(mixed $value, string $typeKey): float
    {
        return in_array($typeKey, ['obligasi', 'reksadana'], true)
            ? $this->parseDecimal($value)
            : $this->parseAmount($value);
    }

    /**
     * Sama seperti parseAmount(), tetapi string kosong menghasilkan null
     * (dipakai kolom "aktual" yang boleh dikosongkan).
     */
    private function parseOptionalAmount(mixed $value): ?float
    {
        return $this->normalizeAmount($value);
    }

    private function normalizeAmount(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $clean = preg_replace('/[^0-9,.\-]/', '', $value);
        $clean = str_replace('.', '', $clean);      // pemisah ribuan
        $clean = str_replace(',', '.', $clean);     // pemisah desimal

        return ($clean === '' || $clean === '-') ? null : (float) $clean;
    }

    // ---------------------------------------------------------------
    // Tabunganku — portofolio aset & investasi keluarga
    // ---------------------------------------------------------------

    /**
     * Laman Tabunganku: ringkasan portofolio + daftar aset investasi.
     *
     * Tidak memakai periode bulanan: nilai aset selalu "per hari ini".
     * Kategori yang dinonaktifkan lewat Master Kategori tidak ikut dihitung
     * maupun ditampilkan.
     */
    public function savings(): void
    {
        $types = FfrSavingsType::all();
        $listKeys = [];
        foreach ($types as $key => $row) {
            if (!empty($row['show_in_list'])) {
                $listKeys[] = $key;
            }
        }

        $savings = new SavingsService();
        $data = $savings->overview(true, $listKeys);
        $this->recordSavingsSnapshot($data, $listKeys);

        // Aset lama (sebelum buku transaksi ada) diberi saldo awal sekali.
        FfrSavingsTransaction::ensureOpeningBalances();

        $editId = (int) ($_GET['edit'] ?? 0);
        $editing = $editId > 0 ? FfrSavingsAsset::find($editId) : null;

        $ffrSubmenu = 'savings';
        $ffrActive = 'savings';
        $period = $this->service->getOrCreatePeriod($this->service->currentPeriod());
        $periodLabel = $this->service->periodLabelLong($period['period']);

        // Daftar aset + saldo untuk modal transaksi.
        $txAssets = [];
        foreach ($data['assets'] as $asset) {
            $txAssets[] = [
                'id'         => (int) $asset['id'],
                'name'       => $asset['name'],
                'type_key'   => $asset['type_key'],
                'type_label' => $asset['type_meta']['label'],
                'unit'       => FfrSavingsAsset::isCash($asset['type_key']) ? 'Rp' : $asset['unit_label'],
                'cash'       => FfrSavingsAsset::isCash($asset['type_key']),
                'quantity'   => (float) $asset['quantity'],
                'price'      => (float) $asset['current_price'],
                'sell_price' => (float) $asset['sell_price'],
                'avg_price'  => (float) $asset['avg_price'],
                'currency'   => $asset['currency'],
                'exchange_rate' => (float) $asset['exchange_rate'],
                'mode'       => FfrSavingsAsset::valuationMode($asset['type_key']),
            ];
        }

        $this->render('savings', compact(
            'data', 'editing', 'ffrSubmenu', 'ffrActive', 'period', 'periodLabel', 'types', 'txAssets'
        ));
    }

    // ---------------------------------------------------------------
    // Tabunganku — Buku transaksi (tarik/jual, transfer, koreksi)
    // ---------------------------------------------------------------

    /** Catat transaksi aset (tarik/jual, setor, transfer, koreksi). */
    public function saveSavingsTransaction(): void
    {
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect('index.php?page=ffr&action=savings');
        }

        $input = [
            'asset_id'        => $assetId,
            'tx_type'         => (string) ($_POST['tx_type'] ?? 'out'),
            'tx_date'         => (string) ($_POST['tx_date'] ?? ''),
            'quantity'        => (string) ($_POST['quantity'] ?? ''),
            'price'           => (string) ($_POST['price'] ?? ''),
            'fee'             => (string) ($_POST['fee'] ?? ''),
            'tax'             => (string) ($_POST['tax'] ?? ''),
            'category'        => (string) ($_POST['category'] ?? ''),
            'note'            => (string) ($_POST['note'] ?? ''),
            'target_asset_id' => (string) ($_POST['target_asset_id'] ?? ''),
            'target_price'    => (string) ($_POST['target_price'] ?? ''),
        ];

        $result = (new SavingsTransactionService())->record($input);
        FormHelper::setFlash($result['message'], $result['ok'] ? 'success' : 'danger');

        $this->redirect('index.php?page=ffr&action=savings');
    }

    /** Laman Mutasi: riwayat transaksi + ringkasan arus dana. */
    public function savingsMutations(): void
    {
        FfrSavingsTransaction::ensureOpeningBalances();

        $month = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $type = (string) ($_GET['type'] ?? '');
        if (!isset(FfrSavingsTransaction::TYPES[$type])) {
            $type = '';
        }

        $assetId = (int) ($_GET['asset_id'] ?? 0);

        $filters = ['from' => $from, 'to' => $to];
        if ($type !== '') {
            $filters['type'] = $type;
        }
        if ($assetId > 0) {
            $filters['asset_id'] = $assetId;
        }

        $transactions = FfrSavingsTransaction::all($filters);
        $summary = FfrSavingsTransaction::summary($filters);

        // Semua aset (termasuk yang diarsipkan) untuk pemilih filter.
        $allAssets = FfrSavingsAsset::all(false);

        $ffrSubmenu = 'savings';
        $ffrActive = 'savings_mutations';
        $period = $this->service->getOrCreatePeriod($this->service->currentPeriod());
        $periodLabel = $this->service->periodLabelLong($period['period']);

        $this->render('savings_mutations', compact(
            'transactions', 'summary', 'allAssets', 'month', 'type', 'assetId',
            'ffrSubmenu', 'ffrActive', 'period', 'periodLabel'
        ));
    }

    /** Ekspor mutasi bulan berjalan sebagai CSV. */
    public function exportSavingsMutations(): void
    {
        $month = (string) ($_GET['month'] ?? date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }

        $filters = ['from' => $month . '-01', 'to' => date('Y-m-t', strtotime($month . '-01'))];
        $type = (string) ($_GET['type'] ?? '');
        if (isset(FfrSavingsTransaction::TYPES[$type])) {
            $filters['type'] = $type;
        }
        $assetId = (int) ($_GET['asset_id'] ?? 0);
        if ($assetId > 0) {
            $filters['asset_id'] = $assetId;
        }

        $rows = FfrSavingsTransaction::all($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="mutasi-tabunganku-' . $month . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        // BOM agar Excel membaca UTF-8 dengan benar.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Tanggal', 'Aset', 'Jenis', 'Jumlah', 'Harga', 'Nilai Bruto',
            'Biaya', 'Pajak', 'Nilai Bersih', 'Realisasi', 'Saldo Akhir', 'Tujuan', 'Catatan',
        ], ';', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['tx_date'],
                $row['asset_name'] ?? '-',
                FfrSavingsTransaction::typeMeta($row['tx_type'])['label'],
                $row['quantity'],
                $row['price'],
                $row['gross_amount'],
                $row['fee'],
                $row['tax'],
                $row['amount'],
                $row['realized_gain'] ?? '',
                $row['balance_after'],
                $row['counterpart_name'] ?? '',
                $row['note'] ?? '',
            ], ';', '"', '');
        }

        fclose($out);
        exit;
    }


    // ---------------------------------------------------------------
    // Tabunganku — Dashboard (grafik & tren)
    // ---------------------------------------------------------------

    /** Pilihan rentang hari untuk grafik tren. */
    private const SAVINGS_RANGES = [30, 90, 180, 365, 0];

    /**
     * Dashboard Tabunganku: total aset, tren nilai, dan perbandingan aset.
     */
    public function savingsDashboard(): void
    {
        $types = FfrSavingsType::all();
        $listKeys = [];
        foreach ($types as $key => $row) {
            if (!empty($row['show_in_list'])) {
                $listKeys[] = $key;
            }
        }

        $range = (int) ($_GET['range'] ?? 90);
        if (!in_array($range, self::SAVINGS_RANGES, true)) {
            $range = 90;
        }

        $savings = new SavingsService();

        // Catat snapshot hari ini lebih dulu agar grafik langsung memuat titik
        // terbaru pada kunjungan pertama.
        $current = $savings->overview(true, $listKeys);
        $this->recordSavingsSnapshot($current, $listKeys);

        $data = $savings->dashboard($listKeys, $range);

        $ffrSubmenu = 'savings';
        $ffrActive = 'savings_dashboard';
        $period = $this->service->getOrCreatePeriod($this->service->currentPeriod());
        $periodLabel = $this->service->periodLabelLong($period['period']);

        $this->render('savings_dashboard', compact('data', 'ffrSubmenu', 'ffrActive', 'period', 'periodLabel', 'types', 'range'));
    }

    /** Simpan snapshot nilai portofolio hari ini (tombol manual). */
    public function saveSavingsSnapshot(): void
    {
        $back = 'index.php?page=ffr&action=savingsDashboard';

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }

        $types = FfrSavingsType::all();
        $listKeys = [];
        foreach ($types as $key => $row) {
            if (!empty($row['show_in_list'])) {
                $listKeys[] = $key;
            }
        }

        $savings = new SavingsService();
        $data = $savings->overview(true, $listKeys);
        $ok = $this->recordSavingsSnapshot($data, $listKeys, true);

        FormHelper::setFlash(
            $ok ? 'Snapshot nilai portofolio hari ini tersimpan.' : 'Gagal menyimpan snapshot.',
            $ok ? 'success' : 'danger'
        );
        $this->redirect($back);
    }

    /**
     * Catat snapshot harian (upsert per tanggal).
     *
     * @param array $data      Hasil overview().
     * @param array $listKeys  Kategori yang ditampilkan.
     * @param bool  $force     Tetap simpan walau nilai tidak berubah.
     */
    private function recordSavingsSnapshot(array $data, array $listKeys, bool $force = false): bool
    {
        $detail = [];
        foreach ($data['groups'] as $key => $group) {
            if (in_array($key, $listKeys, true)) {
                $detail[$key] = round((float) $group['value'], 2);
            }
        }

        $today = date('Y-m-d');
        $latest = FfrSavingsSnapshot::latest();

        // Lewati penulisan bila snapshot hari ini sudah sama persis.
        if (!$force && $latest && $latest['snapshot_date'] === $today) {
            $sameValue = abs($latest['total_value'] - (float) $data['total_value']) < 0.01;
            $sameCost  = abs($latest['total_cost'] - (float) $data['total_cost']) < 0.01;
            $sameDetail = $latest['detail'] == $detail;
            if ($sameValue && $sameCost && $sameDetail) {
                return true;
            }
        }

        return FfrSavingsSnapshot::upsert($today, [
            'total_value' => $data['total_value'],
            'total_cost'  => $data['total_cost'],
            'total_gross' => $data['total_gross'] ?? $data['total_value'],
            'asset_count' => $data['count'],
        ], $detail);
    }

    // ---------------------------------------------------------------
    // Tabunganku — Master Kategori
    // ---------------------------------------------------------------

    /**
     * Master Kategori: pilih kategori yang bisa diinput dan ditampilkan.
     */
    public function savingsMaster(): void
    {
        $types = FfrSavingsType::all();
        $counts = FfrSavingsAsset::countsByType();

        foreach ($types as &$type) {
            $type['count'] = $counts[$type['type_key']] ?? 0;
        }
        unset($type);

        $ffrSubmenu = 'savings';
        $ffrActive = 'savings_master';
        $period = $this->service->getOrCreatePeriod($this->service->currentPeriod());
        $periodLabel = $this->service->periodLabelLong($period['period']);

        $this->render('savings_master', compact('types', 'ffrSubmenu', 'ffrActive', 'period', 'periodLabel'));
    }

    /** Simpan pengaturan Master Kategori. */
    public function saveSavingsTypes(): void
    {
        $back = 'index.php?page=ffr&action=savingsMaster';

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }

        $posted = $_POST['types'] ?? [];
        $ok = FfrSavingsType::save(is_array($posted) ? $posted : []);
        FormHelper::setFlash(
            $ok ? 'Master kategori Tabunganku disimpan.' : 'Gagal menyimpan: tabel master belum siap.',
            $ok ? 'success' : 'danger'
        );
        $this->redirect($back);
    }

    /** Kembalikan semua kategori ke pengaturan bawaan. */
    public function resetSavingsTypes(): void
    {
        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect('index.php?page=ffr&action=savingsMaster');
        }

        $ok = FfrSavingsType::reset();
        FormHelper::setFlash(
            $ok ? 'Semua kategori diaktifkan kembali.' : 'Gagal mengubah pengaturan kategori.',
            $ok ? 'success' : 'danger'
        );
        $this->redirect('index.php?page=ffr&action=savingsMaster');
    }

    /**
     * Simpan aset investasi (tambah baru atau ubah yang sudah ada).
     */
    public function saveSavings(): void
    {
        $back = 'index.php?page=ffr&action=savings';

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            FormHelper::setFlash('Nama aset wajib diisi.', 'danger');
            $this->redirect($back);
        }

        $typeKey = (string) ($_POST['type_key'] ?? 'tabungan');
        if (!isset(FfrSavingsAsset::TYPES[$typeKey])) {
            $typeKey = 'lainnya';
        }
        $isCash = FfrSavingsAsset::isCash($typeKey);

        $currentPrice = $this->parsePrice($_POST['current_price'] ?? '0', $typeKey);
        $sellPrice    = $this->parsePrice($_POST['sell_price'] ?? '0', $typeKey);
        // Bila harga kini dikosongkan tetapi harga jual diisi, pakai harga jual
        // sebagai harga kini (mis. emas: buyback hari ini).
        if ($currentPrice <= 0 && $sellPrice > 0) {
            $currentPrice = $sellPrice;
        }

        $unitLabel = (string) ($_POST['unit_label'] ?? '');
        if ($unitLabel === '') {
            $unitLabel = FfrSavingsAsset::typeMeta($typeKey)['unit'] ?? 'unit';
        }

        $data = [
            'type_key'      => $typeKey,
            'name'          => $name,
            'ticker'        => (string) ($_POST['ticker'] ?? ''),
            'institution'   => (string) ($_POST['institution'] ?? ''),
            'account_no'    => (string) ($_POST['account_no'] ?? ''),
            'owner'         => (string) ($_POST['owner'] ?? 'Bersama'),
            'unit_label'    => $unitLabel,
            'quantity'      => $this->parseDecimal($_POST['quantity'] ?? '0'),
            'avg_price'     => $this->parsePrice($_POST['avg_price'] ?? '0', $typeKey),
            'current_price' => $currentPrice,
            'sell_price'    => $sellPrice,
            'fee_buy'       => $this->parsePercent($_POST['fee_buy'] ?? '0'),
            'fee_sell'      => $this->parsePercent($_POST['fee_sell'] ?? '0'),
            'tax_sell'      => $this->parsePercent($_POST['tax_sell'] ?? '0'),
            'coupon_rate'   => $this->parsePercent($_POST['coupon_rate'] ?? '0'),
            'maturity_date' => (string) ($_POST['maturity_date'] ?? ''),
            'currency'      => $isCash ? 'IDR' : (string) ($_POST['currency'] ?? 'IDR'),
            'exchange_rate' => $isCash ? 1 : $this->parseAmount($_POST['exchange_rate'] ?? '1'),
            'buy_date'      => $isCash ? '' : (string) ($_POST['buy_date'] ?? ''),
            'price_date'    => $isCash ? '' : (trim((string) ($_POST['price_date'] ?? '')) ?: date('Y-m-d')),
            'gold_brand'    => (string) ($_POST['gold_brand'] ?? ''),
            'gold_weight_gram' => $this->parseDecimal($_POST['gold_weight_gram'] ?? ''),
            'price_source'  => (string) ($_POST['price_source'] ?? ''),
            'price_fetched_at' => (string) ($_POST['price_fetched_at'] ?? ''),
            'notes'         => (string) ($_POST['notes'] ?? ''),
            'is_active'     => !empty($_POST['is_active']) ? 1 : 0,
            'sort_order'    => (int) ($_POST['sort_order'] ?? 0),
        ];

        $id = (int) ($_POST['asset_id'] ?? 0);
        if ($id > 0 && FfrSavingsAsset::find($id)) {
            FfrSavingsAsset::update($id, $data);
            FormHelper::setFlash('Aset "' . $name . '" diperbarui.', 'success');
        } else {
            FfrSavingsAsset::create($data);
            FormHelper::setFlash('Aset "' . $name . '" ditambahkan ke Tabunganku.', 'success');
        }

        $this->redirect($back);
    }

    /**
     * Perbarui harga sekarang satu aset (aksi cepat dari tabel).
     */
    public function updateSavingsPrice(): void
    {
        $back = 'index.php?page=ffr&action=savings';

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }

        $id = (int) ($_POST['asset_id'] ?? 0);
        $asset = FfrSavingsAsset::find($id);
        if (!$asset) {
            FormHelper::setFlash('Aset tidak ditemukan.', 'danger');
            $this->redirect($back);
        }

        $assetType = (string) ($asset['type_key'] ?? '');
        $currentPrice = $this->parsePrice($_POST['current_price'] ?? '0', $assetType);
        $hasSellPrice = array_key_exists('sell_price', $_POST);
        $sellPrice = $hasSellPrice ? $this->parsePrice($_POST['sell_price'] ?? '0', $assetType) : null;

        FfrSavingsAsset::updatePrices($id, $currentPrice, $sellPrice);
        FfrSavingsAsset::updatePriceDate($id, date('Y-m-d'));

        FormHelper::setFlash('Harga "' . $asset['name'] . '" diperbarui.', 'success');
        $this->redirect($back);
    }

    /**
     * Perbarui saldo aset cash (dana cash / deposito) langsung dari tabel.
     */
    public function updateSavingsQty(): void
    {
        $back = 'index.php?page=ffr&action=savings';

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }

        $id = (int) ($_POST['asset_id'] ?? 0);
        $asset = FfrSavingsAsset::find($id);
        if (!$asset) {
            FormHelper::setFlash('Aset tidak ditemukan.', 'danger');
            $this->redirect($back);
        }

        FfrSavingsAsset::updateQuantity($id, $this->parseDecimal($_POST['quantity'] ?? '0'));
        FormHelper::setFlash('Saldo "' . $asset['name'] . '" diperbarui.', 'success');
        $this->redirect($back);
    }

    public function deleteSavings(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $asset = FfrSavingsAsset::find($id);

        if ($asset) {
            // Aset yang sudah punya riwayat tidak dihapus permanen agar buku
            // transaksi tetap utuh — cukup diarsipkan.
            if (FfrSavingsTransaction::countForAsset($id) > 0) {
                FfrSavingsAsset::archive($id);
                FormHelper::setFlash(
                    'Aset "' . $asset['name'] . '" diarsipkan (riwayat transaksi dipertahankan).',
                    'info'
                );
            } else {
                FfrSavingsAsset::delete($id);
                FormHelper::setFlash('Aset "' . $asset['name'] . '" dihapus dari Tabunganku.', 'success');
            }
        }

        $this->redirect('index.php?page=ffr&action=savings');
    }

    /** Aktifkan kembali aset yang diarsipkan (saldo 0). */
    public function restoreSavings(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $asset = FfrSavingsAsset::find($id);

        if ($asset) {
            FfrSavingsAsset::unarchive($id);
            FormHelper::setFlash(
                'Aset "' . $asset['name'] . '" diaktifkan kembali. Isi saldo lewat tombol Setor.',
                'success'
            );
        }

        $this->redirect('index.php?page=ffr&action=savingsMutations');
    }

    /**
     * Laman "Harga Emas": harga harian per brand & pecahan, perbandingan,
     * histori, serta pembaruan massal aset emas.
     */
    public function goldPrices(): void
    {
        $service = new GoldPriceService();

        $weight = (float) ($_GET['weight'] ?? 1);
        if (!FfrGoldPrice::isSupportedWeight($weight)) {
            $weight = 1.0;
        }

        $back = 'index.php?page=ffr&action=goldPrices&weight=' . FfrGoldPrice::weightKey($weight);

        if (!empty($_GET['refresh'])) {
            try {
                $result = $service->ensureFresh(true);
            } catch (\Throwable $e) {
                error_log('[FFR gold] refresh: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
                $result = ['fetched' => false, 'reason' => 'provider', 'error' => $e->getMessage()];
            }

            if (!empty($result['fetched'])) {
                FormHelper::setFlash(
                    'Harga otomatis diperbarui dari ' . ($result['source'] ?? 'provider') .
                    ' (' . (int) ($result['stored'] ?? 0) . ' harga).',
                    'success'
                );
            } else {
                FormHelper::setFlash(
                    'Harga otomatis tidak tersedia. Memakai harga manual/terakhir.'
                        . (!empty($result['error']) ? ' (' . $result['error'] . ')' : ''),
                    $result['reason'] === 'cache' ? 'info' : 'warning'
                );
            }
            $this->redirect($back);
        }

        // Pertama kali dipakai: coba ambil sekali agar tabel tidak kosong.
        try {
            $comparison = $service->comparison($weight);
            if (empty($comparison['rows'])) {
                $service->ensureFresh();
                $comparison = $service->comparison($weight);
            }
            $bulk = $service->bulkPreview();
            $history = $service->history(14, $weight);
        } catch (\Throwable $e) {
            // Sumber harga eksternal tidak boleh menjatuhkan halaman.
            error_log('[FFR gold] goldPrices: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            FormHelper::setFlash(
                'Harga emas belum bisa dimuat otomatis: ' . $e->getMessage() .
                '. Anda masih bisa mengisi harga manual di bawah.',
                'warning'
            );
            $comparison = [
                'weight'      => $weight,
                'rows'        => [],
                'spot'        => null,
                'fetched_at'  => null,
                'date'        => null,
                'stale'       => false,
                'auto_brands' => FfrGoldPrice::AUTO_BRANDS,
                'manual_only' => array_keys(FfrGoldPrice::BRANDS),
            ];
            $bulk = [];
            $history = [];
        }

        $ffrSubmenu = 'savings';
        $ffrActive = 'gold_prices';
        $period = $this->service->getOrCreatePeriod($this->service->currentPeriod());
        $periodLabel = $this->service->periodLabelLong($period['period']);

        $this->render('gold_prices', compact(
            'comparison', 'bulk', 'history', 'weight', 'ffrSubmenu', 'ffrActive', 'period', 'periodLabel'
        ));
    }

    /** Simpan harga emas manual dari laman Harga Emas. */
    public function saveGoldPrices(): void
    {
        $weight = (float) ($_POST['weight'] ?? 1);
        $back = 'index.php?page=ffr&action=goldPrices&weight=' . FfrGoldPrice::weightKey($weight);

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }

        $posted = $_POST['prices'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        $saved = (new GoldPriceService())->saveManual($posted, $weight);
        FormHelper::setFlash(
            $saved > 0 ? $saved . ' harga emas disimpan.' : 'Tidak ada harga yang diisi.',
            $saved > 0 ? 'success' : 'info'
        );
        $this->redirect($back);
    }

    /**
     * JSON: harga satu brand + pecahan (tombol "Ambil Harga Hari Ini").
     */
    public function fetchGoldPrice(): void
    {
        $brand = (string) ($_GET['brand'] ?? '');
        $weight = (float) ($_GET['weight'] ?? 1);

        try {
            $row = (new GoldPriceService())->priceFor($brand, $weight);
        } catch (\Throwable $e) {
            error_log('[FFR gold] fetchGoldPrice: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'ok'      => false,
                'message' => 'Harga belum bisa diambil: ' . $e->getMessage(),
            ], 200);
            return;
        }

        if ($row === null) {
            $this->json([
                'ok'      => false,
                'message' => 'Harga untuk brand/pecahan ini belum tersedia. Isi manual atau coba lagi.',
            ]);
            return;
        }

        $this->json(['ok' => true] + $row);
    }

    /**
     * JSON: perbandingan semua brand untuk satu pecahan.
     */
    public function goldCompare(): void
    {
        $weight = (float) ($_GET['weight'] ?? 1);

        try {
            $service = new GoldPriceService();
            $comparison = $service->comparison($weight);
            if (empty($comparison['rows'])) {
                $service->ensureFresh();
                $comparison = $service->comparison($weight);
            }
        } catch (\Throwable $e) {
            error_log('[FFR gold] goldCompare: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->json([
                'ok'      => false,
                'message' => 'Perbandingan harga belum tersedia: ' . $e->getMessage(),
            ], 200);
            return;
        }

        $this->json(['ok' => true] + $comparison);
    }

    /** Terapkan pembaruan massal harga aset emas (setelah pratinjau). */
    public function goldBulkApply(): void
    {
        $back = 'index.php?page=ffr&action=goldPrices';

        if (!FormHelper::verifyCsrf()) {
            FormHelper::setFlash('Sesi form kedaluwarsa. Silakan coba lagi.', 'danger');
            $this->redirect($back);
        }

        $ids = $_POST['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $result = (new GoldPriceService())->applyBulk($ids);
        FormHelper::setFlash(
            $result['updated'] > 0
                ? $result['updated'] . ' aset emas diperbarui' .
                    ($result['skipped'] > 0 ? ', ' . $result['skipped'] . ' dilewati.' : '.')
                : 'Tidak ada aset yang diperbarui.',
            $result['updated'] > 0 ? 'success' : 'info'
        );
        $this->redirect($back);
    }

    /** Kirim JSON lalu hentikan request. */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}