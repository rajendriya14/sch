<?php
/**
 * FFR — Rencana Bulan: pemasukan + alokasi dana ke tiap bucket.
 *
 * Alokasi bisa diisi dalam rupiah atau persen; keduanya saling mengisi
 * (dihitung ulang di server saat disimpan agar tidak ada selisih pembulatan).
 *
 * Variabel: $period, $periodLabel, $periodOptions, $incomes, $summaries,
 *           $total, $allocated, $prevLabel, $allocation, $recalcNeeded,
 *           $recalcInfo
 */
use Helpers\FormHelper;
use Helpers\MoneyHelper;

$pageName = $pageName ?? 'ffr';
$ffrActive = 'plan';
require __DIR__ . '/_nav.php';

$allocation   = $allocation ?? [];
$recalcNeeded = $recalcNeeded ?? false;
$recalcInfo   = $recalcInfo ?? [];

$allocByBucket = [];
foreach ($summaries as $s) {
    $allocByBucket[(int) $s['bucket']['id']] = $s['allocated'];
}
$sisa = $total - $allocated;
$owners = ['Ivan', 'Ricka', 'Bersama'];

// Porsi tiap kelompok terhadap total pemasukan (= nilai kolom persen).
$rowPct = function (int $bucketId, float $amount) use ($allocation, $total): float {
    $stored = $allocation[$bucketId] ?? null;
    if ($stored !== null && $stored['source'] === 'percent' && $stored['percent'] !== null) {
        return (float) $stored['percent'];
    }
    return $total > 0 ? round($amount / $total * 100, 2) : 0.0;
};
$totalPct = 0.0;
foreach ($summaries as $s) {
    $totalPct += $rowPct((int) $s['bucket']['id'], (float) $s['allocated']);
}
$sisaPct = $total > 0 ? ($sisa / $total * 100) : 0.0;
$isClosed = ($period['status'] ?? 'open') === 'closed';

// Kartu pemasukan punya dua mode: form input dan ringkasan terkunci.
// Setelah pemasukan tersimpan, kartu menampilkan ringkasan + tombol Edit.
$incomeLocked = !empty($incomes) && !isset($_GET['editIncome']) && !$isClosed;
$incomeCount  = count($incomes);
?>  

<!-- Toolbar: refresh & hapus isi (isian belum tersimpan bisa dipulihkan) -->
<div class="d-flex flex-wrap align-items-center gap-2 mb-2" id="ffrDraftBar"
     data-draft-key="plan:<?= htmlspecialchars($period['period']) ?>"
     data-draft-scope="ffrPlanForm">
    <button type="button" class="btn btn-sm btn-outline-dark" id="ffrRefreshBtn"
            title="Muat ulang data terbaru; isian yang belum disimpan tetap dipulihkan">
        <i class="bi bi-arrow-clockwise"></i> Refresh
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger" id="ffrClearBtn"
            title="Kosongkan isian di layar; data yang sudah tersimpan tidak berubah">
        <i class="bi bi-eraser"></i> Hapus Isi
    </button>
    <span class="small text-muted d-none" id="ffrDraftState">
        <i class="bi bi-record-circle text-warning"></i> ada isian belum tersimpan
    </span>
    <span class="small text-muted d-none" id="ffrDraftRestored">
        <i class="bi bi-arrow-counterclockwise text-primary"></i> Draft dipulihkan
        — <a href="#" id="ffrDraftDiscard">buang draft</a>
    </span>
</div>

<form method="post" action="index.php?page=ffr&action=plan" id="ffrPlanForm"
      data-income="<?= (float) $total ?>"
      data-closed="<?= $isClosed ? '1' : '0' ?>"
      data-draft-key="plan:<?= htmlspecialchars($period['period']) ?>">
    <?= FormHelper::csrfField() ?>
    <input type="hidden" name="period" value="<?= htmlspecialchars($period['period']) ?>">

    <div class="row g-3">
        <!-- PEMASUKAN -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center gap-2">
                    <span><i class="bi bi-arrow-down-circle"></i> Pemasukan</span>
                    <span class="badge bg-light text-dark" id="ffrIncomeBadge"><?= MoneyHelper::rupiah($total) ?></span>
                </div>

                <?php if ($incomeLocked): ?>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-2" id="ffrIncomeSummary">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sumber</th>
                                        <th>Pemilik</th>
                                        <th class="text-end">Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incomes as $income): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($income['source']) ?></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($income['owner']) ?></small></td>
                                            <td class="text-end"><?= MoneyHelper::rupiah($income['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light fw-semibold">
                                    <tr>
                                        <td colspan="2">Total pemasukan</td>
                                        <td class="text-end"><?= MoneyHelper::rupiah($total) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="alert alert-success py-2 small mb-2">
                            <i class="bi bi-check-circle"></i>
                            Pemasukan sudah tersimpan (<?= $incomeCount ?> baris). Angka ini dipakai
                            sebagai dasar perhitungan persen alokasi.
                        </div>

                        <a href="index.php?page=ffr&action=plan&period=<?= urlencode($period['period']) ?>&editIncome=1"
                           class="btn btn-sm btn-outline-dark">
                            <i class="bi bi-pencil"></i> Edit Pemasukan
                        </a>

                        <?php // Nilai tetap dikirim saat Simpan Rencana agar tidak terhapus. ?>
                        <?php foreach ($incomes as $income): ?>
                            <input type="hidden" name="income_source[]" value="<?= htmlspecialchars($income['source']) ?>">
                            <input type="hidden" name="income_amount[]" value="<?= (int) $income['amount'] ?>">
                            <input type="hidden" name="income_owner[]" value="<?= htmlspecialchars($income['owner']) ?>">
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-2" id="ffrIncomeTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width: 140px;">Sumber</th>
                                        <th style="min-width: 130px;">Jumlah</th>
                                        <th style="min-width: 100px;">Pemilik</th>
                                        <th style="width: 42px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($incomes as $income): ?>
                                        <tr>
                                            <td><input type="text" class="form-control form-control-sm" name="income_source[]"
                                                       value="<?= htmlspecialchars($income['source']) ?>" placeholder="Gaji Ivan"></td>
                                            <td><input type="text" inputmode="numeric" class="form-control form-control-sm ffr-amount"
                                                       name="income_amount[]" value="<?= (int) $income['amount'] ?>"></td>
                                            <td>
                                                <select class="form-select form-select-sm" name="income_owner[]">
                                                    <?php foreach ($owners as $owner): ?>
                                                        <option value="<?= $owner ?>" <?= $income['owner'] === $owner ? 'selected' : '' ?>><?= $owner ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-sm btn-outline-danger ffr-remove-row" title="Hapus baris">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php if (empty($incomes)): ?>
                                        <?php for ($i = 0; $i < 3; $i++): ?>
                                            <tr>
                                                <td><input type="text" class="form-control form-control-sm" name="income_source[]"
                                                           value="" placeholder="<?= $i === 0 ? 'Gaji Ivan' : ($i === 1 ? 'Gaji Ricka' : 'Freelance / Bonus') ?>"></td>
                                                <td><input type="text" inputmode="numeric" class="form-control form-control-sm ffr-amount"
                                                           name="income_amount[]" value=""></td>
                                                <td>
                                                    <select class="form-select form-select-sm" name="income_owner[]">
                                                        <?php foreach ($owners as $owner): ?>
                                                            <option value="<?= $owner ?>" <?= ($i === 0 ? 'Ivan' : ($i === 1 ? 'Ricka' : 'Bersama')) === $owner ? 'selected' : '' ?>><?= $owner ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-sm btn-outline-danger ffr-remove-row" title="Hapus baris">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endfor; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-dark" id="ffrAddIncome">
                                <i class="bi bi-plus-circle"></i> Tambah baris
                            </button>

                            <?php if (!$isClosed): ?>
                                <button type="submit" class="btn btn-sm btn-success"
                                        formaction="index.php?page=ffr&action=saveIncome"
                                        formnovalidate>
                                    <i class="bi bi-check-circle"></i> Simpan Pemasukan
                                </button>
                            <?php endif; ?>

                            <?php if (!empty($incomes)): ?>
                                <a href="index.php?page=ffr&action=plan&period=<?= urlencode($period['period']) ?>"
                                   class="btn btn-sm btn-outline-secondary">
                                    Batal Edit
                                </a>
                            <?php endif; ?>
                        </div>

                        <p class="text-muted small mb-0 mt-2">
                            <i class="bi bi-info-circle"></i>
                            Jumlah langsung masuk ke total. Baris tanpa nama otomatis disimpan
                            sebagai "Pemasukan"; baris dengan jumlah 0 diabaikan.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ALOKASI -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-dark text-white">
                    <i class="bi bi-wallet2"></i> Alokasi Dana per Kelompok
                </div>
                <div class="card-body">
                    <?php if ($recalcNeeded): ?>
                        <div class="alert alert-warning py-2 small mb-3" id="ffrRecalcBanner">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <i class="bi bi-arrow-repeat"></i>
                                    <strong>Pemasukan berubah.</strong>
                                    Alokasi tersimpan dibuat dari persen, jadi nominalnya belum
                                    mencerminkan pemasukan <?= MoneyHelper::rupiah($total) ?>.
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <form method="post" action="index.php?page=ffr&action=recalcPlan" class="d-inline">
                                        <?= FormHelper::csrfField() ?>
                                        <input type="hidden" name="period" value="<?= htmlspecialchars($period['period']) ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i class="bi bi-calculator"></i> Hitung Ulang
                                        </button>
                                    </form>
                                    <form method="post" action="index.php?page=ffr&action=lockPlan" class="d-inline"
                                          data-confirm="Kunci alokasi sebagai nominal rupiah? Persen menjadi angka turunan dan tidak berubah otomatis saat pemasukan berubah.">
                                        <?= FormHelper::csrfField() ?>
                                        <input type="hidden" name="period" value="<?= htmlspecialchars($period['period']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            Biarkan (kunci nominal)
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="ffr-alloc-list">
                    <?php foreach ($summaries as $s): ?>
                        <?php
                            $bucketId = (int) $s['bucket']['id'];
                            $plan = $s['plan_total'];
                            $alloc = $s['allocated'];
                            $pct = $alloc > 0 ? min(100, MoneyHelper::percent($plan, $alloc, 1)) : ($plan > 0 ? 100 : 0);
                            $barClass = match ($s['status']['key']) {
                                'aman' => 'bg-success',
                                'ketat' => 'bg-warning',
                                'lewat', 'tanpa_dana' => 'bg-danger',
                                default => 'bg-secondary',
                            };
                            $typeLabel = match ($s['bucket']['type']) {
                                'wajib' => 'Wajib',
                                'opsional' => 'Opsional',
                                'simpan' => 'Simpanan',
                                default => '',
                            };
                            $ownPct = $rowPct($bucketId, (float) $alloc);
                            $src = $allocation[$bucketId]['source'] ?? 'rp';
                        ?>
                        <div class="ffr-alloc-row" data-bucket="<?= $bucketId ?>">
                            <div class="ffr-alloc-head">
                                <div class="ffr-alloc-label">
                                    <strong><?= htmlspecialchars($s['bucket']['name']) ?></strong>
                                    <span class="badge bg-light text-dark border ms-1"><?= $typeLabel ?></span>
                                    <span class="badge <?= $s['status']['badge'] ?> ms-1"><?= $s['status']['label'] ?></span>
                                </div>
                                <div class="ffr-alloc-input-pair">
                                    <div class="input-group input-group-sm ffr-pct-box">
                                        <input type="text" inputmode="decimal" class="form-control text-end ffr-pct"
                                               name="allocation_pct[<?= $bucketId ?>]"
                                               value="<?= $ownPct > 0 || $alloc > 0 ? MoneyHelper::percentText($ownPct, 2) : '' ?>"
                                               data-bucket="<?= $bucketId ?>"
                                               aria-label="Persen dari pemasukan"
                                               placeholder="0"
                                               <?= $isClosed || $total <= 0 ? 'disabled' : '' ?>>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <i class="bi bi-arrow-left-right ffr-arrow"></i>
                                    <div class="input-group input-group-sm ffr-rp-box">
                                        <span class="input-group-text">Rp</span>
                                        <input type="text" inputmode="numeric" class="form-control text-end ffr-amount ffr-alloc-rp"
                                               name="allocation[<?= $bucketId ?>]"
                                               value="<?= $alloc > 0 ? (int) $alloc : '' ?>"
                                               data-bucket="<?= $bucketId ?>"
                                               aria-label="Nominal rupiah"
                                               <?= $isClosed ? 'disabled' : '' ?>>
                                    </div>
                                    <input type="hidden" name="allocation_src[<?= $bucketId ?>]"
                                           id="ffrSrc<?= $bucketId ?>" value="<?= $src ?>">
                                </div>
                            </div>
                            <div class="progress ffr-progress mt-1">
                                <div class="progress-bar <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Rencana item: <?= MoneyHelper::rupiah($plan) ?> (<?= (int) $s['item_count'] ?> item)</span>
                                <span><?= MoneyHelper::percentText($pct) ?>% dari dana</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>

                    <div class="border-top pt-3 mt-1">
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total pemasukan</span>
                            <strong id="ffrTotalIncome"><?= MoneyHelper::rupiah($total) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total dialokasikan</span>
                            <strong id="ffrTotalAlloc"><?= MoneyHelper::rupiah($allocated) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Total porsi pemasukan</span>
                            <strong id="ffrTotalPct" class="<?= $totalPct > 100 ? 'text-danger' : '' ?>">
                                <?= MoneyHelper::percentText($totalPct, 2) ?>%
                            </strong>
                        </div>
                        <div class="d-flex justify-content-between fs-5">
                            <span>Belum dialokasikan</span>
                            <strong id="ffrUnallocated" class="<?= $sisa < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= MoneyHelper::rupiah($sisa) ?>
                            </strong>
                        </div>
                        <div class="small text-end mt-1 text-muted" id="ffrUnallocPct">
                            = <?= ($sisa < 0 ? '−' : '') . MoneyHelper::percentText(abs($sisaPct), 2) ?>% dari pemasukan
                        </div>
                        <div class="alert alert-danger py-2 small mt-2 mb-0 <?= $totalPct > 100 ? '' : 'd-none' ?>" id="ffrOverWarn">
                            <i class="bi bi-exclamation-triangle"></i>
                            Total persen melebihi 100%. Masih boleh disimpan, tapi dana yang
                            dialokasikan lebih besar dari pemasukan.
                        </div>
                        <div class="progress ffr-progress mt-2 d-none" id="ffrUnallocBar">
                            <div class="progress-bar bg-dark" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Simpan Rencana
                    </button>

                    <?php if ($isClosed): ?>
                        <span class="text-muted small align-self-center">
                            Bulan ditutup — buka dulu untuk mengubah.
                        </span>
                    <?php else: ?>
                        <a href="index.php?page=ffr&action=items&period=<?= urlencode($period['period']) ?>" class="btn btn-outline-dark">
                            <i class="bi bi-list-check"></i> Isi Daftar Belanja
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Aksi periode (form terpisah karena mengubah data lain) -->
<div class="card mt-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <form method="post" action="index.php?page=ffr&action=copyPrevious" class="d-inline"
              data-confirm="Salin semua alokasi + item dari bulan sebelumnya (<?= htmlspecialchars($prevLabel) ?>)? Realisasi dikosongkan.">
            <?= FormHelper::csrfField() ?>
            <input type="hidden" name="period" value="<?= htmlspecialchars($period['period']) ?>">
            <button type="submit" class="btn btn-outline-primary" <?= $isClosed ? 'disabled' : '' ?>>
                <i class="bi bi-clipboard-plus"></i> Salin dari bulan lalu
            </button>
        </form>

        <form method="post" action="index.php?page=ffr&action=toggleStatus" class="d-inline"
              data-confirm="<?= $isClosed ? 'Buka kembali buku bulan ini?' : 'Tutup buku bulan ini? Setelah ditutup, rencana & item dikunci.' ?>">
            <?= FormHelper::csrfField() ?>
            <input type="hidden" name="period" value="<?= htmlspecialchars($period['period']) ?>">
            <button type="submit" class="btn <?= $isClosed ? 'btn-outline-success' : 'btn-outline-dark' ?>">
                <i class="bi <?= $isClosed ? 'bi-unlock' : 'bi-lock' ?>"></i>
                <?= $isClosed ? 'Buka Buku' : 'Tutup Buku' ?>
            </button>
        </form>

        <form method="post" action="index.php?page=ffr&action=deletePeriod" class="d-inline"
              data-confirm="Hapus periode <?= htmlspecialchars($periodLabel) ?> beserta SELURUH pemasukan, alokasi, dan item belanjanya? Tindakan ini tidak bisa dibatalkan.">
            <?= FormHelper::csrfField() ?>
            <input type="hidden" name="period" value="<?= htmlspecialchars($period['period']) ?>">
            <button type="submit" class="btn btn-outline-danger">
                <i class="bi bi-trash"></i> Hapus Periode
            </button>
        </form>

        <span class="text-muted small ms-auto">
            Butuh kelompok dana baru (mis. Hiburan)?
            <a href="index.php?page=ffr&action=setup">Atur di Master</a>
        </span>
    </div>
</div>
<?php require __DIR__ . '/_nav_end.php'; ?>
