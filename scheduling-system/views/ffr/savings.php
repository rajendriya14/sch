<?php
/**
 * FFR — Tabunganku: portofolio aset & investasi keluarga.
 *
 * Satu aset = satu kepemilikan. Form tambah/ubah menyesuaikan jenis aset:
 *   - Dana Cash / Deposito : bank, nomor rekening, saldo, bunga deposito
 *   - Emas / Logam         : berat gram, harga beli, harga buyback hari ini
 *   - Mata Uang Asing      : jumlah valas, kurs beli, kurs jual hari ini
 *   - Saham / Reksa Dana   : kode/ticker, jumlah lembar/unit, harga beli & kini
 *   - Obligasi / SBN       : nominal, harga %, kupon, jatuh tempo
 *   - Kripto / Properti    : jumlah unit, harga beli & kini
 *
 * Variabel: $data, $editing, $period, $periodLabel
 */
use Helpers\FormHelper;
use Helpers\MoneyHelper;
use Models\FfrGoldPrice;
use Models\FfrSavingsAsset;
use Models\FfrSavingsType;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'savings';
$ffrSubmenu = 'savings';
require __DIR__ . '/_nav.php';

$assets = $data['assets'];
$groups = $data['groups'];
$owners = ['Ivan', 'Ricka', 'Bersama'];

$edit = array_merge([
    'id' => 0,
    'type_key' => 'tabungan',
    'name' => '',
    'ticker' => '',
    'institution' => '',
    'account_no' => '',
    'owner' => 'Bersama',
    'unit_label' => 'Rp',
    'quantity' => '',
    'avg_price' => '',
    'current_price' => '',
    'sell_price' => '',
    'fee_buy' => '',
    'fee_sell' => '',
    'tax_sell' => '',
    'coupon_rate' => '',
    'maturity_date' => '',
    'currency' => 'IDR',
    'exchange_rate' => 1,
    'buy_date' => '',
    'price_date' => date('Y-m-d'),
    'gold_brand' => '',
    'gold_weight_gram' => '',
    'price_source' => '',
    'price_fetched_at' => '',
    'notes' => '',
    'is_active' => 1,
    'sort_order' => 0,
], $editing ?: []);
$isEdit = (int) ($edit['id'] ?? 0) > 0;
$editType = $edit['type_key'] ?? 'tabungan';
if (!isset(FfrSavingsAsset::TYPES[$editType])) {
    $editType = 'lainnya';
}
$typeMeta = FfrSavingsAsset::typeMeta($editType);

/**
 * Format jumlah unit: 0,5 gram tetap "0,5"; 5 tetap "5".
 */
$formatQty = function ($value): string {
    $number = (float) $value;
    $text = number_format($number, 4, ',', '.');
    $text = rtrim(rtrim($text, '0'), ',');
    return $text === '' ? '0' : $text;
};

$gainClass = fn(?float $gain): string => $gain === null || abs($gain) < 1 ? 'text-muted' : ($gain > 0 ? 'text-success' : 'text-danger');
$dateValue = fn($value): string => !empty($value) ? htmlspecialchars((string) $value) : '';

/**
 * Harga per satuan: ribuan memakai titik, desimal memakai koma.
 * 16200 → "16.200"; 98.75 → "98,75"; 1234.56 → "1.234,56".
 */
$formatPrice = function ($value): string {
    $number = (float) $value;
    if ($number == 0.0) {
        return '';
    }
    $text = number_format($number, 2, ',', '.');
    $text = rtrim(rtrim($text, '0'), ',');
    return $text;
};

/** Saldo dana selalu dua desimal: 5000000.5 → "5.000.000,50". */
$formatSaldo = fn($value): string => number_format((float) $value, 2, ',', '.');

// Label & perilaku tiap jenis aset untuk form dinamis.
$formConfig = [
    'tabungan' => [
        'nameLabel' => 'Nama Dana',
        'namePlaceholder' => 'mis. Dana Darurat Keluarga',
        'institutionLabel' => 'Nama Bank / Tempat Simpan',
        'institutionPlaceholder' => 'mis. BCA, Mandiri, Tunai',
        'qtyLabel' => 'Saldo Dana',
        'qtyHelp' => 'Jumlah uang yang tersimpan saat ini (boleh desimal).',
        'qtyPlaceholder' => 'mis. 1.500.000,50',
    ],
    'deposito' => [
        'nameLabel' => 'Nama Deposito',
        'namePlaceholder' => 'mis. Deposito BCA 3 Bulan',
        'institutionLabel' => 'Nama Bank Penerbit',
        'institutionPlaceholder' => 'mis. BCA',
        'qtyLabel' => 'Nilai Deposito',
        'qtyHelp' => 'Pokok deposito dalam Rupiah (boleh desimal).',
        'qtyPlaceholder' => 'mis. 10.000.000,00',
    ],
    'emas' => [
        'nameLabel' => 'Nama / Bentuk Emas',
        'namePlaceholder' => 'mis. Emas Antam 5 gram',
        'institutionLabel' => 'Penerbit / Butik',
        'institutionPlaceholder' => 'mis. Antam, UBS, Galeri24',
        'qtyLabel' => 'Berat (gram)',
        'qtyHelp' => 'Berat emas dalam gram.',
    ],
    'valas' => [
        'nameLabel' => 'Nama Simpanan Valas',
        'namePlaceholder' => 'mis. Tabungan USD',
        'institutionLabel' => 'Bank / Money Changer',
        'institutionPlaceholder' => 'mis. Bank Mandiri, Money Changer',
        'qtyLabel' => 'Jumlah Valas',
        'qtyHelp' => 'Jumlah mata uang asing yang dimiliki.',
    ],
    'saham' => [
        'nameLabel' => 'Nama Saham',
        'namePlaceholder' => 'mis. Bank Central Asia',
        'institutionLabel' => 'Sekuritas',
        'institutionPlaceholder' => 'mis. Mirae Asset, Stockbit',
        'qtyLabel' => 'Jumlah Lembar',
        'qtyHelp' => '1 lot = 100 lembar saham.',
    ],
    'obligasi' => [
        'nameLabel' => 'Nama / Seri Obligasi',
        'namePlaceholder' => 'mis. SBN Retail ORI025',
        'institutionLabel' => 'Penerbit / Platform',
        'institutionPlaceholder' => 'mis. Kemenkeu, Bibit',
        'qtyLabel' => 'Nominal (Rp)',
        'qtyHelp' => 'Nominal pokok obligasi dalam Rupiah.',
    ],
    'reksadana' => [
        'nameLabel' => 'Nama Reksa Dana',
        'namePlaceholder' => 'mis. Reksa Dana Pasar Uang',
        'institutionLabel' => 'Platform / Manajer Investasi',
        'institutionPlaceholder' => 'mis. Bibit, Bareksa',
        'qtyLabel' => 'Jumlah Unit',
        'qtyHelp' => 'Jumlah unit penyertaan yang dimiliki.',
    ],
    'kripto' => [
        'nameLabel' => 'Nama Aset Kripto',
        'namePlaceholder' => 'mis. Bitcoin',
        'institutionLabel' => 'Exchange / Wallet',
        'institutionPlaceholder' => 'mis. Indodax, Tokocrypto',
        'qtyLabel' => 'Jumlah Koin',
        'qtyHelp' => 'Jumlah koin/token yang dimiliki.',
    ],
    'properti' => [
        'nameLabel' => 'Nama Properti',
        'namePlaceholder' => 'mis. Rumah Bekasi',
        'institutionLabel' => 'Lokasi / Developer',
        'institutionPlaceholder' => 'mis. Bekasi, Developer',
        'qtyLabel' => 'Jumlah Unit',
        'qtyHelp' => 'Jumlah unit properti.',
    ],
    'lainnya' => [
        'nameLabel' => 'Nama Aset',
        'namePlaceholder' => 'mis. Barang koleksi',
        'institutionLabel' => 'Institusi / Platform',
        'institutionPlaceholder' => 'mis. Toko, platform, atau penyimpan',
        'qtyLabel' => 'Jumlah',
        'qtyHelp' => 'Jumlah unit aset.',
    ],
];
$config = $formConfig[$editType] ?? $formConfig['lainnya'];
// Obligasi (harga %) dan reksa dana (NAB) boleh memakai angka desimal.
$decimalPrices = in_array($editType, ['obligasi', 'reksadana'], true);

// Satuan awal (dipakai bila JS belum jalan): jenis cash selalu Rupiah.
$unitDefault = ($isEdit && !empty($edit['unit_label']))
    ? (string) $edit['unit_label']
    : ($editType === 'valas'
        ? (string) ($edit['currency'] ?: 'USD')
        : (FfrSavingsAsset::typeMeta($editType)['unit'] ?? 'unit'));

// Label harga yang berubah mengikuti jenis aset (dipakai juga oleh JS).
$priceLabels = [
    'tabungan'  => ['avgLabel' => 'Harga Beli', 'nowLabel' => 'Harga Kini', 'sellLabel' => 'Harga Jual'],
    'deposito'  => ['avgLabel' => 'Harga Beli', 'nowLabel' => 'Harga Kini', 'sellLabel' => 'Harga Jual'],
    'emas'      => ['avgLabel' => 'Harga Beli / gram', 'nowLabel' => 'Harga Beli Hari Ini / gram', 'sellLabel' => 'Harga Buyback Hari Ini / gram'],
    'valas'     => ['avgLabel' => 'Kurs Beli Saat Beli', 'nowLabel' => 'Kurs Jual Hari Ini', 'sellLabel' => 'Kurs Beli Hari Ini (Buyback)'],
    'saham'     => ['avgLabel' => 'Harga Beli / Lembar', 'nowLabel' => 'Harga Pasar Hari Ini', 'sellLabel' => 'Harga Jual Hari Ini'],
    'obligasi'  => ['avgLabel' => 'Harga Beli (% Nominal)', 'nowLabel' => 'Harga Kini (%)', 'sellLabel' => 'Harga Jual (%)'],
    'reksadana' => ['avgLabel' => 'NAB Saat Beli', 'nowLabel' => 'NAB Hari Ini', 'sellLabel' => 'NAB Jual Hari Ini'],
    'kripto'    => ['avgLabel' => 'Harga Beli / Koin', 'nowLabel' => 'Harga Kini / Koin', 'sellLabel' => 'Harga Jual / Koin'],
    'properti'  => ['avgLabel' => 'Harga Beli', 'nowLabel' => 'Nilai Pasar Kini', 'sellLabel' => 'Perkiraan Harga Jual'],
    'lainnya'   => ['avgLabel' => 'Harga Beli / Satuan', 'nowLabel' => 'Harga Kini / Satuan', 'sellLabel' => 'Harga Jual / Satuan'],
];
foreach ($formConfig as $typeKey => $typeConfig) {
    $formConfig[$typeKey] = array_merge(
        ['qtyPlaceholder' => 'mis. 5 atau 0,5'],
        $typeConfig,
        [
            'label' => FfrSavingsAsset::TYPES[$typeKey]['label'] ?? $typeKey,
            'unit'  => FfrSavingsAsset::TYPES[$typeKey]['unit'] ?? 'unit',
        ],
        $priceLabels[$typeKey] ?? []
    );
}

// Master kategori: hanya kategori yang diaktifkan untuk input yang muncul
// di pemilih. Kategori aset yang sedang diubah tetap ditampilkan agar
// pengguna tidak kehilangan jenis aslinya.
$types = $types ?? FfrSavingsType::all();

// Aset baru dimulai dari kategori pertama yang aktif untuk input, supaya
// kategori yang dinonaktifkan tidak muncul kembali sebagai pilihan awal.
if (!$isEdit && empty($types[$editType]['show_in_form'])) {
    foreach ($types as $key => $typeRow) {
        if (!empty($typeRow['show_in_form'])) {
            $editType = $key;
            break;
        }
    }
    $typeMeta = FfrSavingsAsset::typeMeta($editType);
    $config = $formConfig[$editType] ?? $formConfig['lainnya'];
    $decimalPrices = in_array($editType, ['obligasi', 'reksadana'], true);
    $unitDefault = $editType === 'valas'
        ? (string) ($edit['currency'] ?: 'USD')
        : (FfrSavingsAsset::typeMeta($editType)['unit'] ?? 'unit');
}

$formTypes = [];
$hiddenTypeCount = 0;
foreach ($types as $key => $typeRow) {
    if (!empty($typeRow['show_in_form']) || $key === $editType) {
        $formTypes[$key] = $typeRow;
    } else {
        $hiddenTypeCount++;
    }
}
if (count($formTypes) === 0) {
    // Jangan biarkan form tanpa pilihan sama sekali.
    $formTypes = $types;
}
?>

<!-- Toolbar: judul + pintasan ke Dashboard & Master Kategori -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span class="small text-muted">
        <i class="bi bi-wallet2"></i>
        Kategori aktif: <?= count(array_filter($types, fn($t) => !empty($t['show_in_form']))) ?> untuk input,
        <?= count(array_filter($types, fn($t) => !empty($t['show_in_list']))) ?> untuk tampil
    </span>
    <div class="d-flex gap-2">
        <a href="index.php?page=ffr&action=savingsMutations" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-journal-arrow-down"></i> Mutasi
        </a>
        <a href="index.php?page=ffr&action=savingsDashboard" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-graph-up"></i> Dashboard
        </a>
        <a href="index.php?page=ffr&action=savingsMaster" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-sliders"></i> Master Kategori
        </a>
    </div>
</div>


<!-- Ringkasan portofolio -->
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Total Nilai Sekarang</div>
                <div class="fs-4 fw-bold"><?= MoneyHelper::rupiah($data['total_value']) ?></div>
                <div class="small text-muted">
                    <?= (int) $data['count'] ?> aset tercatat ·
                    perkiraan bila dijual hari ini
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Total Modal</div>
                <div class="fs-4 fw-bold"><?= MoneyHelper::rupiah($data['total_cost']) ?></div>
                <div class="small text-muted">harga beli + biaya beli</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Potensi Untung / Rugi</div>
                <div class="fs-4 fw-bold <?= $gainClass($data['total_gain']) ?>">
                    <?= $data['total_gain'] < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($data['total_gain'])) ?>
                </div>
                <div class="small <?= $gainClass($data['total_gain']) ?>">
                    <?= $data['total_gain_percent'] === null
                        ? 'belum ada modal'
                        : ($data['total_gain_percent'] > 0 ? '+' : '') . MoneyHelper::percentText($data['total_gain_percent'], 2) . '% dari modal' ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Jenis Investasi</div>
                <div class="fs-4 fw-bold"><?= count($groups) ?> jenis</div>
                <div class="small text-muted">
                    <?= $data['best']
                        ? 'Terbaik: ' . htmlspecialchars($data['best']['name'])
                        : 'belum ada pergerakan' ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Alokasi per jenis -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <i class="bi bi-pie-chart"></i> Alokasi per Jenis
            </div>
            <div class="card-body">
                <?php if (empty($groups)): ?>
                    <p class="text-muted mb-0">Belum ada aset. Tambahkan lewat form di samping.</p>
                <?php else: ?>
                    <?php foreach ($groups as $group): ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="bi <?= htmlspecialchars($group['icon']) ?>"></i>
                                    <strong><?= htmlspecialchars($group['label']) ?></strong>
                                    <span class="badge bg-light text-dark border ms-1"><?= (int) $group['count'] ?></span>
                                </span>
                                <span class="text-end">
                                    <strong><?= MoneyHelper::rupiah($group['value']) ?></strong>
                                    <small class="text-muted d-block"><?= MoneyHelper::percentText($group['percent']) ?>%</small>
                                </span>
                            </div>
                            <div class="progress ffr-progress mt-1">
                                <div class="progress-bar bg-<?= htmlspecialchars($group['class'] === 'purple' || $group['class'] === 'teal' ? 'dark' : $group['class']) ?>"
                                     style="width: <?= (float) $group['percent'] ?>%"></div>
                            </div>
                            <div class="small <?= $gainClass($group['gain']) ?>">
                                <?= $group['gain'] < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($group['gain'])) ?>
                                <?php if ($group['gain_percent'] !== null): ?>
                                    (<?= $group['gain_percent'] > 0 ? '+' : '' ?><?= MoneyHelper::percentText($group['gain_percent'], 2) ?>%)
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Form tambah / ubah aset -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi <?= $isEdit ? 'bi-pencil-square' : 'bi-plus-circle' ?>"></i>
                    <?= $isEdit ? 'Ubah Aset' : 'Tambah Aset Investasi' ?>
                </span>
                <?php if ($isEdit): ?>
                    <a href="index.php?page=ffr&action=savings" class="btn btn-sm btn-light">
                        <i class="bi bi-x-lg"></i> Batal
                    </a>
                <?php endif; ?>
            </div>
            <form method="post" action="index.php?page=ffr&action=saveSavings" id="svAssetForm">
                <?= FormHelper::csrfField() ?>
                <input type="hidden" name="asset_id" value="<?= (int) $edit['id'] ?>">
                <input type="hidden" name="unit_label" id="svUnit" value="<?= htmlspecialchars($unitDefault) ?>">
                <input type="hidden" name="sort_order" value="<?= (int) $edit['sort_order'] ?>">
                <div class="card-body">

                    <!-- 1. Pilih jenis aset -->
                    <div class="sv-type-grid mb-3">
                        <?php foreach ($formTypes as $key => $typeRow): ?>
                            <?php
                            $meta = FfrSavingsAsset::typeMeta($key);
                            $inactive = empty($typeRow['show_in_form']);
                            ?>
                            <input type="radio" class="btn-check" name="type_key" id="svType_<?= $key ?>"
                                   value="<?= htmlspecialchars($key) ?>"
                                   data-sv-type
                                   <?= $editType === $key ? 'checked' : '' ?>>
                            <label class="sv-type-card" for="svType_<?= $key ?>">
                                <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                <span><?= htmlspecialchars($meta['label']) ?></span>
                                <?php if ($inactive): ?>
                                    <small class="sv-type-note">nonaktif</small>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($hiddenTypeCount > 0): ?>
                        <div class="alert alert-light border py-2 small mb-3">
                            <i class="bi bi-eye-slash"></i>
                            <?= (int) $hiddenTypeCount ?> kategori lain disembunyikan dari form.
                            Atur di <a href="index.php?page=ffr&action=savingsMaster">Master Kategori</a>.
                        </div>
                    <?php endif; ?>

                    <!-- 2. Identitas aset -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="svName" id="svNameLabel"><?= htmlspecialchars($config['nameLabel']) ?></label>
                            <input type="text" class="form-control" id="svName" name="name" maxlength="100" required
                                   placeholder="<?= htmlspecialchars($config['namePlaceholder']) ?>"
                                   value="<?= htmlspecialchars((string) $edit['name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="svInstitution" id="svInstitutionLabel"><?= htmlspecialchars($config['institutionLabel']) ?></label>
                            <input type="text" class="form-control" id="svInstitution" name="institution" maxlength="100"
                                   <?= FfrSavingsAsset::isCash($editType) ? '' : 'list="svInstitutionList"' ?>
                                   placeholder="<?= htmlspecialchars($config['institutionPlaceholder']) ?>"
                                   value="<?= htmlspecialchars((string) $edit['institution']) ?>">
                            <datalist id="svInstitutionList"></datalist>
                        </div>

                        <div class="col-md-6" data-sv-show="emas">
                            <label class="form-label" for="svGoldBrand">Brand Emas</label>
                            <select class="form-select" id="svGoldBrand" name="gold_brand">
                                <?php foreach (FfrGoldPrice::BRANDS as $brandKey => $brandMeta): ?>
                                    <option value="<?= htmlspecialchars($brandKey) ?>"
                                        <?= ($edit['gold_brand'] ?? '') === $brandKey ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brandMeta['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" data-sv-show="emas">
                            <label class="form-label" for="svGoldWeight">Pecahan</label>
                            <?php
                            // Default 1 gram; nilai dari DB bisa "1.000" sehingga
                            // dinormalkan agar cocok dengan value opsi.
                            $selectedGoldWeight = (string) ($edit['gold_weight_gram'] ?? '');
                            $selectedGoldWeight = $selectedGoldWeight === ''
                                ? '1'
                                : FfrGoldPrice::weightKey((float) $selectedGoldWeight);
                            ?>
                            <select class="form-select" id="svGoldWeight" name="gold_weight_gram">
                                <?php foreach (FfrGoldPrice::WEIGHTS as $w): ?>
                                    <?php $weightVal = FfrGoldPrice::weightKey((float) $w); ?>
                                    <option value="<?= $weightVal ?>"
                                        <?= $selectedGoldWeight === $weightVal ? 'selected' : '' ?>>
                                        <?= $weightVal ?> gram
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Dipakai untuk mengambil harga per pecahan.</div>
                        </div>

                        <div class="col-md-6" data-sv-show="saham reksadana kripto obligasi">
                            <label class="form-label" for="svTicker">Kode / Ticker</label>
                            <input type="text" class="form-control" id="svTicker" name="ticker" maxlength="20"
                                   placeholder="mis. BBCA / BTC"
                                   value="<?= htmlspecialchars((string) $edit['ticker']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="svOwner">Pemilik</label>
                            <select class="form-select" id="svOwner" name="owner">
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?= $owner ?>" <?= $edit['owner'] === $owner ? 'selected' : '' ?>><?= $owner ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <hr class="my-3">

                    <!-- 3. Jumlah & harga -->
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="svQty" id="svQtyLabel"><?= htmlspecialchars($config['qtyLabel']) ?></label>
                            <input type="text" inputmode="decimal" class="form-control" id="svQty" name="quantity"
                                   placeholder="mis. 5 atau 0,5"
                                   value="<?= $edit['quantity'] === ''
                                        ? ''
                                        : htmlspecialchars(FfrSavingsAsset::isCash($editType) ? $formatSaldo($edit['quantity']) : $formatQty($edit['quantity'])) ?>">
                            <div class="form-text" id="svQtyHelp"><?= htmlspecialchars($config['qtyHelp']) ?></div>
                        </div>

                        <div class="col-md-6 col-lg-4" data-sv-hide="tabungan deposito">
                            <label class="form-label" for="svAvg" id="svAvgLabel">Harga Beli / Satuan</label>
                            <input type="text" inputmode="numeric" class="form-control ffr-amount" id="svAvg" name="avg_price"
                                   <?= $decimalPrices ? 'data-ffr-decimal="1"' : '' ?>
                                   value="<?= $formatPrice($edit['avg_price']) ?>">
                        </div>
                        <div class="col-md-6 col-lg-4" data-sv-hide="tabungan deposito">
                            <label class="form-label" for="svNow" id="svNowLabel">Harga Kini / Satuan</label>
                            <input type="text" inputmode="numeric" class="form-control ffr-amount" id="svNow" name="current_price"
                                   <?= $decimalPrices ? 'data-ffr-decimal="1"' : '' ?>
                                   value="<?= $formatPrice($edit['current_price']) ?>">
                        </div>
                        <div class="col-md-6 col-lg-4" data-sv-hide="tabungan deposito">
                            <label class="form-label" for="svSell" id="svSellLabel">Harga Jual / Buyback</label>
                            <input type="text" inputmode="numeric" class="form-control ffr-amount" id="svSell" name="sell_price"
                                   <?= $decimalPrices ? 'data-ffr-decimal="1"' : '' ?>
                                   value="<?= $formatPrice($edit['sell_price']) ?>">
                            <div class="form-text">Harga yang diterima saat menjual hari ini.</div>
                        </div>

                        <div class="col-md-6 col-lg-3" data-sv-show="valas">
                            <label class="form-label" for="svCurrency">Mata Uang</label>
                            <select class="form-select" id="svCurrency" name="currency">
                                <?php foreach (FfrSavingsAsset::CURRENCIES as $cur): ?>
                                    <option value="<?= $cur ?>" <?= strtoupper((string) $edit['currency']) === $cur ? 'selected' : '' ?>>
                                        <?= $cur ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-3" data-sv-show="valas">
                            <label class="form-label" for="svRate">Kurs ke Rupiah</label>
                            <input type="text" inputmode="numeric" class="form-control ffr-amount" id="svRate" name="exchange_rate"
                                   value="<?= (float) $edit['exchange_rate'] > 0 ? MoneyHelper::rupiah($edit['exchange_rate'], false) : '1' ?>">
                            <div class="form-text">Isi 1 bila harga di atas sudah dalam Rupiah.</div>
                        </div>

                        <div class="col-md-6 col-lg-3" data-sv-hide="tabungan deposito">
                            <label class="form-label" for="svBuyDate">Tanggal Beli</label>
                            <input type="date" class="form-control" id="svBuyDate" name="buy_date"
                                   value="<?= $dateValue($edit['buy_date']) ?>">
                        </div>
                        <div class="col-md-6 col-lg-3" data-sv-hide="tabungan deposito">
                            <label class="form-label" for="svPriceDate">Tanggal Harga</label>
                            <input type="date" class="form-control" id="svPriceDate" name="price_date"
                                   value="<?= $dateValue($edit['price_date']) ?>">
                            <div class="form-text">Tanggal harga kini/jual di atas.</div>
                        </div>

                        <div class="col-12" data-sv-show="emas">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-dark" id="svGoldFetch">
                                    <i class="bi bi-cloud-arrow-down"></i> Ambil Harga Hari Ini
                                </button>
                                <a href="index.php?page=ffr&action=goldPrices" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-gem"></i> Harga Emas
                                </a>
                                <span class="small text-muted" id="svGoldFetchInfo">
                                    Pilih brand &amp; pecahan, lalu ambil harga.
                                </span>
                            </div>
                            <input type="hidden" name="price_source" id="svPriceSource"
                                   value="<?= htmlspecialchars((string) $edit['price_source']) ?>">
                            <input type="hidden" name="price_fetched_at" id="svPriceFetchedAt"
                                   value="<?= htmlspecialchars((string) $edit['price_fetched_at']) ?>">
                            <?php if (!empty($edit['price_source']) && !empty($edit['price_fetched_at'])): ?>
                                <div class="small text-muted mt-1">
                                    Harga terakhir: <?= htmlspecialchars((string) $edit['price_source']) ?>
                                    · <?= htmlspecialchars((string) $edit['price_fetched_at']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 4. Khusus deposito & obligasi -->
                    <div class="row g-3 mt-1" data-sv-show="deposito obligasi">
                        <div class="col-md-6">
                            <label class="form-label" for="svCoupon">Bunga / Kupon per Tahun (%)</label>
                            <input type="text" inputmode="decimal" class="form-control" id="svCoupon" name="coupon_rate"
                                   placeholder="mis. 5,5"
                                   value="<?= (float) $edit['coupon_rate'] > 0 ? MoneyHelper::percentText($edit['coupon_rate'], 2) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="svMaturity">Jatuh Tempo</label>
                            <input type="date" class="form-control" id="svMaturity" name="maturity_date"
                                   value="<?= $dateValue($edit['maturity_date']) ?>">
                        </div>
                    </div>

                    <!-- 5. Biaya & pajak (opsional) -->
                    <div class="mt-3" data-sv-hide="tabungan deposito">
                        <a class="small text-decoration-none" data-bs-toggle="collapse" href="#svFees" role="button"
                           aria-expanded="false" aria-controls="svFees">
                            <i class="bi bi-sliders"></i> Biaya &amp; pajak jual (opsional)
                        </a>
                        <div class="collapse mt-2" id="svFees">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="svFeeBuy">Biaya Beli (%)</label>
                                    <input type="text" inputmode="decimal" class="form-control" id="svFeeBuy" name="fee_buy"
                                           placeholder="0"
                                           value="<?= (float) $edit['fee_buy'] > 0 ? MoneyHelper::percentText($edit['fee_buy'], 2) : '' ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="svFeeSell">Biaya Jual (%)</label>
                                    <input type="text" inputmode="decimal" class="form-control" id="svFeeSell" name="fee_sell"
                                           placeholder="0"
                                           value="<?= (float) $edit['fee_sell'] > 0 ? MoneyHelper::percentText($edit['fee_sell'], 2) : '' ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="svTaxSell">Pajak Jual (%)</label>
                                    <input type="text" inputmode="decimal" class="form-control" id="svTaxSell" name="tax_sell"
                                           placeholder="0"
                                           value="<?= (float) $edit['tax_sell'] > 0 ? MoneyHelper::percentText($edit['tax_sell'], 2) : '' ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Prediksi otomatis -->
                    <div class="sv-prediction mt-3" id="svPrediction">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><i class="bi bi-calculator"></i> Prediksi</strong>
                            <span class="badge bg-light text-dark border" id="svPredType"><?= htmlspecialchars($typeMeta['label']) ?></span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="sv-pred-label">Modal</div>
                                <div class="sv-pred-value" id="svPredCost">–</div>
                            </div>
                            <div class="col-6">
                                <div class="sv-pred-label" id="svPredValueLabel">Nilai sekarang</div>
                                <div class="sv-pred-value" id="svPredValue">–</div>
                            </div>
                            <div class="col-6">
                                <div class="sv-pred-label" id="svPredSellLabel">Perkiraan hasil jual</div>
                                <div class="sv-pred-value" id="svPredSell">–</div>
                            </div>
                            <div class="col-6">
                                <div class="sv-pred-label">Untung / Rugi</div>
                                <div class="sv-pred-value" id="svPredGain">–</div>
                            </div>
                        </div>
                        <div class="small mt-2" id="svPredExtra"></div>
                        <div class="small text-muted mt-1" id="svPredNote"></div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label" for="svNotes">Catatan</label>
                            <input type="text" class="form-control" id="svNotes" name="notes" maxlength="255"
                                   placeholder="mis. target jual saat naik 20%"
                                   value="<?= htmlspecialchars((string) $edit['notes']) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="svActive" name="is_active"
                                       <?= !empty($edit['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="svActive">Tampilkan di portofolio</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?= $isEdit ? 'Simpan Perubahan' : 'Tambah Aset' ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Perbandingan harga emas: tampil hanya saat kategori Emas dipilih -->
        <div class="card mt-3 d-none" id="svGoldCompareCard">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-gem"></i> Perbandingan Harga Emas</span>
                <a href="index.php?page=ffr&action=goldPrices" class="btn btn-sm btn-light">
                    <i class="bi bi-sliders"></i> Kelola Harga
                </a>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <span class="small text-muted" id="svGoldCompareMeta">Memuat harga…</span>
                    <select class="form-select form-select-sm" id="svGoldCompareWeight"
                            style="max-width: 150px;" aria-label="Pecahan perbandingan">
                        <?php foreach (FfrGoldPrice::WEIGHTS as $w): ?>
                            <?php $weightVal = FfrGoldPrice::weightKey((float) $w); ?>
                            <option value="<?= $weightVal ?>"><?= $weightVal ?> gram</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 150px;">Brand</th>
                                <th class="text-end">Beli / gram</th>
                                <th class="text-end">Buyback / gram</th>
                                <th class="text-end" title="Selisih buyback terhadap harga beli; makin kecil makin baik">Spread</th>
                                <th class="text-end">Premium vs Spot</th>
                                <th class="text-end" style="min-width: 110px;">Sumber</th>
                            </tr>
                        </thead>
                        <tbody id="svGoldCompareBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Memuat…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mb-0 mt-2">
                    <i class="bi bi-info-circle"></i>
                    Penanda “termurah” dan “spread tersempit” bersifat informatif, bukan saran investasi.
                    Harga Semar/Galeri24/Lotus diisi manual lewat halaman Harga Emas.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Daftar aset -->
<div class="card mt-3">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-wallet2"></i> Daftar Investasi &amp; Tabungan — <?= (int) $data['count'] ?> aset</span>
        <span class="small">Total <?= MoneyHelper::rupiah($data['total_value']) ?></span>
    </div>
    <?php if (($data['hidden_count'] ?? 0) > 0): ?>
        <div class="alert alert-light border-0 border-bottom rounded-0 py-2 small mb-0">
            <i class="bi bi-eye-slash"></i>
            <?= (int) $data['hidden_count'] ?> aset dari kategori yang disembunyikan tidak ikut dihitung.
            <a href="index.php?page=ffr&action=savingsMaster">Ubah di Master Kategori</a>.
        </div>
    <?php endif; ?>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 44px;">No</th>
                        <th style="min-width: 200px;">Aset</th>
                        <th style="min-width: 130px;">Jenis</th>
                        <th class="text-end" style="min-width: 110px;">Jumlah</th>
                        <th class="text-end" style="min-width: 140px;">Harga</th>
                        <th class="text-end" style="min-width: 120px;">Modal</th>
                        <th class="text-end" style="min-width: 140px;">Nilai Kini</th>
                        <th class="text-end" style="min-width: 150px;" title="Setelah biaya jual & pajak">Untung / Rugi</th>
                        <th class="text-end" style="min-width: 96px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assets)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                Belum ada aset investasi. Tambahkan dana cash, valas, emas,
                                saham, atau obligasi lewat form di atas.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($assets as $i => $asset): ?>
                            <?php
                            $meta = $asset['type_meta'];
                            $type = $asset['type_key'];
                            $cash = FfrSavingsAsset::isCash($type);
                            $hasSellPrice = !$cash && ($asset['sell_price'] ?? 0) > 0;
                            $sellDiffers = !$cash && abs((float) $asset['value'] - (float) $asset['gross_value']) >= 1;
                            ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($asset['name']) ?></strong>
                                    <?php if (!empty($asset['ticker'])): ?>
                                        <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($asset['ticker']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($asset['gold_brand'])): ?>
                                        <span class="badge bg-warning-subtle text-dark border border-warning ms-1">
                                            <i class="bi bi-gem"></i>
                                            <?= htmlspecialchars(\Models\FfrGoldPrice::brandMeta($asset['gold_brand'])['label']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($asset['owner']) ?></span>
                                    <?php if (!empty($asset['institution'])): ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars((string) $asset['institution']) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($asset['notes'])): ?>
                                        <small class="text-muted d-block fst-italic"><?= htmlspecialchars($asset['notes']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-dark-subtle text-dark border border-dark">
                                        <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i>
                                        <?= htmlspecialchars($meta['label']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($cash): ?>
                                        <form method="post" action="index.php?page=ffr&action=updateSavingsQty" class="d-inline">
                                            <?= FormHelper::csrfField() ?>
                                            <input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>">
                                            <input type="text" inputmode="decimal"
                                                   class="form-control form-control-sm text-end ffr-amount sv-qty"
                                                   name="quantity" data-ffr-money2="1" style="min-width: 130px;"
                                                   value="<?= $formatSaldo($asset['quantity']) ?>"
                                                   title="Ubah saldo lalu tekan Enter untuk memperbarui">
                                        </form>
                                        <small class="text-muted d-block"><?= htmlspecialchars($asset['unit_label']) ?></small>
                                    <?php else: ?>
                                        <?= htmlspecialchars($formatQty($asset['quantity'])) ?>
                                        <small class="text-muted"><?= htmlspecialchars($asset['unit_label']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($cash): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <form method="post" action="index.php?page=ffr&action=updateSavingsPrice" class="d-inline">
                                            <?= FormHelper::csrfField() ?>
                                            <input type="hidden" name="asset_id" value="<?= (int) $asset['id'] ?>">
                                            <input type="text" inputmode="numeric"
                                                   class="form-control form-control-sm text-end ffr-amount sv-price mb-1"
                                                   name="current_price" style="min-width: 110px;"
                                                   <?= in_array($type, ['obligasi', 'reksadana'], true) ? 'data-ffr-decimal="1"' : '' ?>
                                                   value="<?= $formatPrice($asset['current_price']) ?>"
                                                   title="Harga kini — ubah lalu tekan Enter">
                                            <?php if (in_array($type, ['emas', 'valas'], true)): ?>
                                                <input type="text" inputmode="numeric"
                                                       class="form-control form-control-sm text-end ffr-amount sv-price"
                                                       name="sell_price" style="min-width: 110px;"
                                                       value="<?= $formatPrice($asset['sell_price']) ?>"
                                                       placeholder="harga jual"
                                                       title="Harga jual/buyback — ubah lalu tekan Enter">
                                            <?php endif; ?>
                                        </form>
                                        <small class="text-muted d-block">
                                            beli <?= $formatPrice($asset['avg_price']) ?><?= $type === 'obligasi' ? '%' : '' ?>
                                            <?php if (strtoupper((string) $asset['currency']) !== 'IDR'): ?>
                                                · <?= htmlspecialchars($asset['currency']) ?>
                                            <?php endif; ?>
                                        </small>
                                        <?php if (!empty($asset['price_date'])): ?>
                                            <small class="text-muted d-block">per <?= htmlspecialchars(date('d/m/Y', strtotime($asset['price_date']))) ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($asset['price_source'])): ?>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-cloud-check"></i> <?= htmlspecialchars($asset['price_source']) ?>
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($hasSellPrice): ?>
                                            <small class="text-muted d-block">jual <?= $formatPrice($asset['sell_price']) ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <?= $cash ? 'Rp ' . $formatSaldo($asset['cost']) : MoneyHelper::rupiah($asset['cost']) ?>
                                </td>
                                <td class="text-end">
                                    <strong><?= $cash ? 'Rp ' . $formatSaldo($asset['value']) : MoneyHelper::rupiah($asset['value']) ?></strong>
                                    <?php if ($sellDiffers): ?>
                                        <small class="text-muted d-block">bruto <?= MoneyHelper::rupiah($asset['gross_value']) ?></small>
                                    <?php endif; ?>
                                    <?php if ((float) $asset['coupon_annual'] > 0): ?>
                                        <small class="text-muted d-block">kupon <?= MoneyHelper::rupiah($asset['coupon_annual']) ?>/th</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end <?= $gainClass($asset['gain']) ?>">
                                    <?php if ($cash): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?= $asset['gain'] < 0 ? '−' : '+' ?><?= MoneyHelper::rupiah(abs($asset['gain'])) ?>
                                        <?php if ($asset['gain_percent'] !== null): ?>
                                            <small class="d-block">
                                                <?= $asset['gain_percent'] > 0 ? '+' : '' ?><?= MoneyHelper::percentText($asset['gain_percent'], 2) ?>%
                                            </small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-success sv-tx-open"
                                            title="Setor / tambah"
                                            data-asset-id="<?= (int) $asset['id'] ?>"
                                            data-tx-mode="in">
                                        <i class="bi bi-arrow-down-circle"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger sv-tx-open"
                                            title="Tarik / jual / pindah"
                                            data-asset-id="<?= (int) $asset['id'] ?>"
                                            data-tx-mode="out">
                                        <i class="bi bi-arrow-up-circle"></i>
                                    </button>
                                    <a href="index.php?page=ffr&action=savings&edit=<?= (int) $asset['id'] ?>"
                                       class="btn btn-sm btn-outline-primary" title="Ubah aset">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="index.php?page=ffr&action=deleteSavings&id=<?= (int) $asset['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       data-confirm="Hapus aset &quot;<?= htmlspecialchars($asset['name']) ?>&quot; dari Tabunganku? Riwayat transaksinya tetap tersimpan.">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-3">
    <i class="bi bi-lightbulb"></i>
    Ubah <strong>harga</strong> atau <strong>saldo</strong> langsung di tabel lalu tekan Enter untuk memperbarui nilainya.
    Nilai kini dihitung dari harga jual/buyback hari ini setelah biaya jual &amp; pajak, jadi angka untung/rugi
    mendekati uang yang benar-benar diterima bila aset dijual.
</p>

<?php require __DIR__ . '/_nav_end.php'; ?>

<!-- Modal transaksi aset: setor, tarik/jual, transfer, koreksi -->
<div class="modal fade" id="svTxModal" tabindex="-1" aria-labelledby="svTxTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="index.php?page=ffr&action=saveSavingsTransaction" id="svTxForm">
                <?= FormHelper::csrfField() ?>
                <input type="hidden" name="asset_id" id="svTxAssetId" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="svTxTitle"><i class="bi bi-journal-arrow-down"></i> Transaksi Aset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border py-2 small" id="svTxAssetInfo">Pilih aset.</div>

                    <div class="btn-group btn-group-sm w-100 mb-3" role="group" aria-label="Jenis transaksi">
                        <input type="radio" class="btn-check" name="tx_type" id="svTxTypeOut" value="out" checked>
                        <label class="btn btn-outline-danger" for="svTxTypeOut">
                            <i class="bi bi-arrow-up-circle"></i> Tarik / Jual
                        </label>

                        <input type="radio" class="btn-check" name="tx_type" id="svTxTypeIn" value="in">
                        <label class="btn btn-outline-success" for="svTxTypeIn">
                            <i class="bi bi-arrow-down-circle"></i> Setor / Tambah
                        </label>

                        <input type="radio" class="btn-check" name="tx_type" id="svTxTypeTransfer" value="transfer">
                        <label class="btn btn-outline-primary" for="svTxTypeTransfer">
                            <i class="bi bi-arrow-left-right"></i> Pindah Aset
                        </label>

                        <input type="radio" class="btn-check" name="tx_type" id="svTxTypeAdjust" value="adjust">
                        <label class="btn btn-outline-warning" for="svTxTypeAdjust">
                            <i class="bi bi-wrench-adjustable"></i> Koreksi
                        </label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="svTxDate">Tanggal</label>
                            <input type="date" class="form-control" id="svTxDate" name="tx_date"
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="svTxQty" id="svTxQtyLabel">Jumlah</label>
                            <input type="text" inputmode="decimal" class="form-control" id="svTxQty" name="quantity"
                                   value="" required>
                            <div class="form-text" id="svTxQtyHelp">Jumlah unit aset.</div>
                        </div>

                        <div class="col-md-4" id="svTxPriceWrap">
                            <label class="form-label" for="svTxPrice" id="svTxPriceLabel">Harga</label>
                            <input type="text" inputmode="numeric" class="form-control ffr-amount" id="svTxPrice"
                                   name="price" value="">
                            <div class="form-text" id="svTxPriceHelp">Harga per unit.</div>
                        </div>

                        <div class="col-md-4 sv-tx-cost">
                            <label class="form-label" for="svTxFee">Biaya (Rp)</label>
                            <input type="text" inputmode="numeric" class="form-control ffr-amount" id="svTxFee"
                                   name="fee" value="" placeholder="0">
                        </div>
                        <div class="col-md-4 sv-tx-cost">
                            <label class="form-label" for="svTxTax">Pajak (Rp)</label>
                            <input type="text" inputmode="numeric" class="form-control ffr-amount" id="svTxTax"
                                   name="tax" value="" placeholder="0">
                        </div>

                        <div class="col-md-4" id="svTxCategoryWrap">
                            <label class="form-label" for="svTxCategory">Tujuan / Alasan</label>
                            <select class="form-select" id="svTxCategory" name="category">
                                <?php foreach (\Models\FfrSavingsTransaction::CATEGORIES as $catKey => $catLabel): ?>
                                    <option value="<?= htmlspecialchars($catKey) ?>"><?= htmlspecialchars($catLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 d-none" id="svTxTargetWrap">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="svTxTarget">Pindah ke Aset</label>
                                    <select class="form-select" id="svTxTarget" name="target_asset_id">
                                        <option value="">— pilih aset tujuan —</option>
                                        <?php foreach ($txAssets as $txAsset): ?>
                                            <option value="<?= (int) $txAsset['id'] ?>">
                                                <?= htmlspecialchars($txAsset['name']) ?>
                                                (<?= htmlspecialchars($txAsset['type_label']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="svTxTargetPrice">Harga di Aset Tujuan</label>
                                    <input type="text" inputmode="numeric" class="form-control ffr-amount"
                                           id="svTxTargetPrice" name="target_price" value="">
                                    <div class="form-text" id="svTxTargetHelp">
                                        Harga beli per unit di aset tujuan (kosong = pakai harga kini).
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="svTxNote">Catatan</label>
                            <input type="text" class="form-control" id="svTxNote" name="note" maxlength="255"
                                   placeholder="mis. bayar uang sekolah anak">
                        </div>
                    </div>

                    <div class="sv-prediction mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><i class="bi bi-calculator"></i> Perhitungan</strong>
                            <span class="badge bg-light text-dark border" id="svTxBadge">Tarik / Jual</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <div class="sv-pred-label">Nilai Bruto</div>
                                <div class="sv-pred-value" id="svTxGross">–</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="sv-pred-label">Nilai Bersih</div>
                                <div class="sv-pred-value" id="svTxNet">–</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="sv-pred-label">Realized</div>
                                <div class="sv-pred-value" id="svTxRealized">–</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="sv-pred-label">Saldo Setelah</div>
                                <div class="sv-pred-value" id="svTxBalance">–</div>
                            </div>
                        </div>
                        <div class="small text-muted mt-2" id="svTxNoteInfo"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="svTxSubmit">
                        <i class="bi bi-check2"></i> Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.SV_TX_ASSETS = <?= json_encode($txAssets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script>
    // Konfigurasi form per jenis aset + saran institusi untuk datalist.
    window.SV_FORM_CONFIG = <?= json_encode($formConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    window.SV_INSTITUTIONS = <?= json_encode(FfrSavingsAsset::INSTITUTIONS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>

