<?php

namespace Models;

use Config\Database;

/**
 * Absensi BAK per periode (dari berkas konfirmasi attendance WFM).
 *
 * Alur:
 *   parse()          berkas -> baris mentah (posisi, kode absensi, nama, shift, tanggal)
 *   save()           agregasi + simpan per periode (upload ulang menimpa periode itu)
 *   summary()        rekap per posisi: hari H/SC/Sick/CT/Off + quantity prorata
 *                    + rekap per orang (NIK, hari masuk, rate, unit & counting price)
 *   savePrices()     harga per posisi per periode
 *   syncToMaxPotential()  quantity & price/unit mengalir ke rate card BAK
 *
 * Rumus quantity (keputusan pemilik sistem):
 *   quantity = jumlah ORANG UNIK, diprorata:
 *       rasio orang = min(1, hari masuk orang itu / hari kerja standar)
 *       hari masuk = hari terjadwal (H/CT/SC/Sick); Off tidak dihitung
 *       hari kerja standar = hari terjadwal terbanyak di berkas (= sebulan penuh)
 *   Rate dibulatkan ROUND 2 desimal; Sum Rate = penjumlahan rate yang sudah
 *   dibulatkan itu, dan angka hasil pembulatan dipakai untuk menghitung uang.
 *   CT dihitung masuk (bagian dari hak cuti tahunan).
 *
 * Hanya baris ber-Remarks "Non Billable" / "Buffer" yang TIDAK dihitung ke
 * harga & ringkasan, Max Potential, Accrual, dan Actual. Remarks lain —
 * termasuk "Billable: Resign per 15 Oct 2026" maupun "Resign per 09 Oct 2026" —
 * tetap dihitung, dengan rate mengikuti hari aktif (resign diprorata).
 * Baris yang tidak dihitung tetap tampil di rekap per orang sebagai informasi.
 */
class BakAttendance
{
    public const CODE_H    = 'H';
    public const CODE_SC   = 'SC';
    public const CODE_SICK = 'Sick';
    public const CODE_CT   = 'CT';
    public const CODE_OFF  = 'Off';

    /** Kata kunci Remarks yang membuat baris TIDAK dihitung. */
    public const REMARK_EXCLUDE = ['non billable', 'nonbillable', 'buffer'];

    /** Jumlah desimal Rate (ROUND 2) — dipakai juga saat menghitung uang. */
    public const RATE_DECIMALS = 2;

    /** Kolom bawaan berkas WFM: D = Position, Q = Actual Attendance Code. */
    private const DEFAULT_POS_COL  = 3;
    private const DEFAULT_CODE_COL = 16;

    /**
     * Apakah orang ini boleh dihitung?
     *
     * Aturan (keputusan pemilik sistem):
     *   - Dihitung   : "Billable", "Billable: Resign per 15 Oct 2026",
     *                  "Resign per 09 Oct 2026" (resign + tanggal saja).
     *   - Tidak      : mengandung "Non Billable" (atau "Nonbillable") atau
     *                  "Buffer" — salah satu atau keduanya.
     *
     * Jadi pengecualian hanya untuk Non Billable / Buffer; Remarks lain tetap
     * dihitung (rate mengikuti hari aktif, sehingga resign tetap diprorata).
     *
     * Berkas lama tanpa kolom Remarks: semua orang dianggap dihitung supaya
     * perhitungan tidak menjadi nol.
     */
    public static function isBillable(?string $remarks, bool $hasRemarks = true): bool
    {
        if (!$hasRemarks) {
            return true;
        }

        $value = strtolower(trim((string) $remarks));
        if ($value === '') {
            return true;
        }

        foreach (self::REMARK_EXCLUDE as $needle) {
            if (strpos($value, $needle) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ambil tanggal resign dari teks Remarks.
     *
     * Contoh yang didukung:
     *   "Non Billable (Buffer): Resign per 28 Sep 2026"
     *   "Resign per 28 September 2026"
     *   "Resign 28/09/2026"  |  "Resign 2026-09-28"
     *
     * @return ?string tanggal dalam format Y-m-d, atau null bila tidak ada.
     */
    public static function resignDate(?string $remarks): ?string
    {
        $text = trim((string) $remarks);
        if ($text === '' || stripos($text, 'resign') === false) {
            return null;
        }

        // Nama bulan: Inggris (Jan..Dec) + Indonesia (Jan..Des, Mei, Agu, Okt).
        $months = [
            'jan' => 1,  'januari' => 1,  'january' => 1,
            'feb' => 2,  'peb' => 2,      'februari' => 2, 'february' => 2,
            'mar' => 3,  'maret' => 3,    'march' => 3,
            'apr' => 4,  'april' => 4,
            'may' => 5,  'mei' => 5,
            'jun' => 6,  'juni' => 6,     'june' => 6,
            'jul' => 7,  'juli' => 7,     'july' => 7,
            'aug' => 8,  'agu' => 8,      'ags' => 8, 'agustus' => 8, 'august' => 8,
            'sep' => 9,  'sept' => 9,     'september' => 9,
            'oct' => 10, 'okt' => 10,     'oktober' => 10, 'october' => 10,
            'nov' => 11, 'november' => 11,
            'dec' => 12, 'des' => 12,     'desember' => 12, 'december' => 12,
        ];

        // 28 Sep 2026 / 28 September 2026 / 28-Sep-2026
        if (preg_match('/(\d{1,2})[\s\-\/\.]+([A-Za-z]{3,9})[\s\-\/\.]+(\d{4})/', $text, $m)) {
            $key = strtolower($m[2]);
            if (isset($months[$key])) {
                return sprintf('%04d-%02d-%02d', (int) $m[3], $months[$key], (int) $m[1]);
            }
        }

        // 2026-09-28
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 28/09/2026 atau 28-09-2026
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $text, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    /** Jumlah hari dalam bulan periode (YYYY-MM). */
    public static function monthLength(string $period): int
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return 30;
        }

        return (int) (new \DateTimeImmutable($period . '-01'))->format('t');
    }

    /* ===================================================================== */
    /* Parsing                                                               */
    /* ===================================================================== */

    /**
     * Normalisasi matriks spreadsheet menjadi baris absensi.
     *
     * @param array<int, array<int, string>> $matrix
     * @return array{
     *     rows: array<int, array{name:string, employee_id:string, position:string, code:string, shift:string, date:string, remarks:string}>,
     *     other_codes: array<int, string>,
     *     period: ?string,
     *     skipped: int
     * }
     */
    public static function parse(array $matrix): array
    {
        // Kolom bawaan berkas WFM (A = Employee ID, D = Position, Q = Code, T = Remarks).
        $posCol  = self::DEFAULT_POS_COL;
        $codeCol = self::DEFAULT_CODE_COL;
        $nameCol = 1;   // Fullname
        $idCol   = 0;   // Employee ID / NIK
        $shiftCol = 5;  // Working Shift
        $dateCol = 6;   // Date
        $remarkCol = 19; // T = Remarks
        $mapDone = false;

        $rows       = [];
        $otherCodes = [];
        $skipped    = 0;
        $period     = null;

        foreach ($matrix as $line) {
            if (!is_array($line) || $line === []) {
                continue;
            }

            $cells = array_values($line);
            $probe = strtoupper(implode(' ', array_map('strval', $cells)));

            // Baris kepala tabel: petakan kolom berdasarkan namanya, supaya
            // tahan terhadap pergeseran kolom antar versi ekspor.
            if (!$mapDone && strpos($probe, 'POSITION') !== false && strpos($probe, 'ATTENDANCE') !== false) {
                foreach ($cells as $i => $c) {
                    $u = strtoupper(trim((string) $c));
                    $u = preg_replace('/[^A-Z ]+/', ' ', $u) ?? $u;

                    if ($u === 'POSITION') {
                        $posCol = $i;
                    } elseif (strpos($u, 'ATTENDANCE') !== false) {
                        $codeCol = $i;
                    } elseif (strpos($u, 'EMPLOYEE ID') !== false || $u === 'NIK' || $u === 'N I K') {
                        $idCol = $i;
                    } elseif (strpos($u, 'FULLNAME') !== false || strpos($u, 'FULL NAME') !== false || $u === 'NAME') {
                        $nameCol = $i;
                    } elseif (strpos($u, 'WORKING SHIFT') !== false || $u === 'SHIFT') {
                        $shiftCol = $i;
                    } elseif (strpos($u, 'REMARK') !== false) {
                        $remarkCol = $i;
                    } elseif (trim($u) === 'DATE' && $dateCol === 6) {
                        $dateCol = $i;
                    }
                }
                $mapDone = true;
                continue;
            }

            // Baris huruf kolom (A B C ... S) yang ikut ter-paste dari Excel.
            // Semua selnya hanya 1-2 huruf kapital, dan jumlahnya banyak.
            if (self::looksLikeColumnLetters($cells)) {
                continue;
            }

            $position = trim((string) ($cells[$posCol] ?? ''));
            if ($position === '') {
                $skipped++;
                continue;
            }

            $code  = self::normalizeCode($cells[$codeCol] ?? '');
            $name  = trim((string) ($cells[$nameCol] ?? ''));
            $shift = trim((string) ($cells[$shiftCol] ?? ''));
            $date  = self::excelDate($cells[$dateCol] ?? '');

            if ($date !== null && $period === null) {
                $period = substr($date, 0, 7);
            }

            if (strpos($code, 'LAIN:') === 0) {
                $raw = substr($code, 5);
                if (!in_array($raw, $otherCodes, true)) {
                    $otherCodes[] = $raw;
                }
            }

            $rows[] = [
                'name'        => $name,
                'employee_id' => trim((string) ($cells[$idCol] ?? '')),
                'position'    => $position,
                'code'        => $code,
                'shift'       => $shift,
                'date'        => $date ?? '',
                'remarks'     => trim((string) ($cells[$remarkCol] ?? '')),
            ];
        }

        return [
            'rows'        => $rows,
            'other_codes' => $otherCodes,
            'period'      => $period,
            'skipped'     => $skipped,
        ];
    }

    /**
     * Apakah baris ini hanya berisi huruf kolom (A, B, C, ... S)?
     *
     * Baris seperti ini sering ikut ter-paste dari Excel dan tidak boleh
     * dianggap sebagai data absensi.
     *
     * @param array<int, string> $cells
     */
    private static function looksLikeColumnLetters(array $cells): bool
    {
        $seen = 0;
        foreach ($cells as $c) {
            $v = strtoupper(trim((string) $c));
            if ($v === '') {
                continue;
            }
            if (!preg_match('/^[A-Z]{1,2}$/', $v)) {
                return false;
            }
            $seen++;
        }

        return $seen >= 5;
    }

    /**
     * Kode absensi diseragamkan.
     *
     * '' berarti tidak ada kode (biasanya Day Off). Kode tak dikenal diberi
     * awalan "LAIN:" agar bisa dilaporkan tanpa mengganggu hitungan.
     */
    public static function normalizeCode($value): string
    {
        $s = strtoupper(preg_replace('/[\s\-_]+/', '', trim((string) $value)) ?? '');
        if ($s === '') {
            return '';
        }

        $map = [
            'H' => self::CODE_H, 'HADIR' => self::CODE_H, 'PRESENT' => self::CODE_H,
            'SC' => self::CODE_SC, 'SHIFTCHANGE' => self::CODE_SC,
            'SICK' => self::CODE_SICK, 'SAKIT' => self::CODE_SICK,
            'CT' => self::CODE_CT, 'CUTI' => self::CODE_CT, 'CUTITAHUNAN' => self::CODE_CT,
            'OFF' => self::CODE_OFF, 'DAYOFF' => self::CODE_OFF, 'DO' => self::CODE_OFF, 'LIBUR' => self::CODE_OFF,
        ];

        if (isset($map[$s])) {
            return $map[$s];
        }

        return 'LAIN:' . $s;
    }

    /**
     * Serial tanggal Excel -> Y-m-d. Bukan serial tanggal => null.
     */
    public static function excelDate($value): ?string
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }
        if (!is_numeric($value)) {
            return null;
        }

        $n = (float) $value;
        if ($n < 20000 || $n > 80000) {
            return null;
        }

        return gmdate('Y-m-d', (int) round(($n - 25569) * 86400));
    }

    /**
     * Hari dianggap terjadwal bila bukan Day Off.
     */
    private static function isScheduled(string $code, string $shift): bool
    {
        if ($code === self::CODE_OFF) {
            return false;
        }

        $sh = strtolower(preg_replace('/[\s\-_]+/', '', $shift) ?? '');
        if (in_array($sh, ['dayoff', 'off', 'libur', 'do'], true)) {
            return false;
        }

        if ($code !== '') {
            return true;
        }

        return $sh !== '';
    }

    /* ===================================================================== */
    /* Penyimpanan                                                           */
    /* ===================================================================== */

    /**
     * Simpan hasil parse untuk satu periode (menimpa periode yang sama).
     *
     * @param array<int, array{name:string, position:string, employee_id:string, code:string, shift:string, date:string, remarks:string}> $rows
     * @return array<string, mixed> ringkasan periode
     */
    public static function save(string $period, array $rows, string $fileName = '', ?string $user = null): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Periode harus berformat YYYY-MM.');
        }
        if ($rows === []) {
            throw new \InvalidArgumentException('Tidak ada baris absensi yang bisa disimpan.');
        }

        $db = Database::getInstance();

        // --- agregasi di memori ---
        $dayMap    = [];   // "position|person|code" => jumlah baris (Billable saja)
        $peopleMap = [];   // "position|name" => hari terjadwal
        $names     = [];   // name => true (orang unik seluruh berkas)

        // Berkas lama tanpa kolom Remarks: semua orang dianggap Billable.
        $hasRemarks = false;
        foreach ($rows as $r) {
            if (trim((string) ($r['remarks'] ?? '')) !== '') {
                $hasRemarks = true;
                break;
            }
        }

        foreach ($rows as $r) {
            $pos      = $r['position'];
            $code     = $r['code'] === '' ? '' : $r['code'];
            $billable = self::isBillable($r['remarks'] ?? '', $hasRemarks);

            // Hanya Billable yang dihitung ke harga, Max Potential, Accrual, Actual.
            if ($billable) {
                $person = $r['name'] !== '' ? $r['name'] : '(tanpa nama)';
                $key    = $pos . '|' . $person . '|' . $code;
                if (!isset($dayMap[$key])) {
                    $dayMap[$key] = [
                        'position' => $pos,
                        'person'   => $person,
                        'code'     => $code,
                        'day_rows' => 0,
                        'people'   => [],
                    ];
                }
                $dayMap[$key]['day_rows']++;
                $dayMap[$key]['people'][$person] = true;
            }

            $name = $r['name'] !== '' ? $r['name'] : '(tanpa nama)';
            $names[$name] = true;

            $pk = $pos . '|' . $name;
            if (!isset($peopleMap[$pk])) {
                $peopleMap[$pk] = [
                    'position'    => $pos,
                    'name'        => $name,
                    'employee_id' => (string) ($r['employee_id'] ?? ''),
                    'remarks'     => (string) ($r['remarks'] ?? ''),
                    'scheduled'   => 0,
                ];
            } else {
                if ($peopleMap[$pk]['employee_id'] === '' && ($r['employee_id'] ?? '') !== '') {
                    // NIK kadang hanya terisi di sebagian baris.
                    $peopleMap[$pk]['employee_id'] = (string) $r['employee_id'];
                }
                if ($peopleMap[$pk]['remarks'] === '' && ($r['remarks'] ?? '') !== '') {
                    // Remarks juga kadang hanya terisi di sebagian baris.
                    $peopleMap[$pk]['remarks'] = (string) $r['remarks'];
                }
            }
            if (self::isScheduled($code, $r['shift'] ?? '')) {
                $peopleMap[$pk]['scheduled']++;
            }
        }

        $standard = 0;
        foreach ($peopleMap as $p) {
            if ($p['scheduled'] > $standard) {
                $standard = $p['scheduled'];
            }
        }

        // --- tulis ---
        $db->beginTransaction();
        try {
            $sel = $db->prepare('SELECT id FROM bak_attendance_uploads WHERE period = ?');
            $sel->execute([$period]);
            $uploadId = $sel->fetchColumn();

            if ($uploadId === false || $uploadId === null) {
                $ins = $db->prepare(
                    'INSERT INTO bak_attendance_uploads
                        (period, file_name, total_rows, total_people, standard_days, uploaded_by)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([$period, $fileName, count($rows), count($names), $standard, $user]);
                $uploadId = (int) $db->lastInsertId();
            } else {
                $uploadId = (int) $uploadId;
                $upd = $db->prepare(
                    'UPDATE bak_attendance_uploads
                        SET file_name = ?, total_rows = ?, total_people = ?, standard_days = ?, uploaded_by = ?
                      WHERE id = ?'
                );
                $upd->execute([$fileName, count($rows), count($names), $standard, $user, $uploadId]);

                $db->prepare('DELETE FROM bak_attendance_days WHERE upload_id = ?')->execute([$uploadId]);
                $db->prepare('DELETE FROM bak_attendance_people WHERE upload_id = ?')->execute([$uploadId]);
            }

            $insDay = $db->prepare(
                'INSERT INTO bak_attendance_days (upload_id, position, person_name, code, day_rows, people)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($dayMap as $d) {
                $insDay->execute([
                    $uploadId,
                    $d['position'],
                    $d['person'],
                    $d['code'],
                    $d['day_rows'],
                    count($d['people']),
                ]);
            }

            $insPerson = $db->prepare(
                'INSERT INTO bak_attendance_people (upload_id, position, employee_id, person_name, remarks, scheduled_days)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($peopleMap as $p) {
                $insPerson->execute([
                    $uploadId,
                    $p['position'],
                    $p['employee_id'] !== '' ? $p['employee_id'] : null,
                    $p['name'],
                    $p['remarks'] !== '' ? $p['remarks'] : null,
                    $p['scheduled'],
                ]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Rate card Max Potential otomatis mengikuti posisi yang ada di berkas.
        self::syncQuietly($period);

        return self::summary($period);
    }

    /**
     * Hapus data absensi satu periode.
     */
    public static function remove(string $period): void
    {
        $db  = Database::getInstance();
        $stmt = $db->prepare('SELECT id FROM bak_attendance_uploads WHERE period = ?');
        $stmt->execute([$period]);
        $id = $stmt->fetchColumn();

        if ($id !== false && $id !== null) {
            $db->prepare('DELETE FROM bak_attendance_uploads WHERE id = ?')->execute([(int) $id]);
        }
    }

    /**
     * Daftar periode yang pernah diunggah (terbaru lebih dulu).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function periods(int $limit = 12): array
    {
        $stmt = Database::getInstance()->query(
            'SELECT period, file_name, total_rows, total_people, standard_days, created_at
               FROM bak_attendance_uploads
           ORDER BY period DESC
              LIMIT ' . max(1, min(60, $limit))
        );

        return array_map(static function (array $row): array {
            return [
                'period'        => (string) $row['period'],
                'file_name'     => (string) ($row['file_name'] ?? ''),
                'total_rows'    => (int) $row['total_rows'],
                'total_people'  => (int) $row['total_people'],
                'standard_days' => (int) $row['standard_days'],
                'uploaded_at'   => (string) ($row['created_at'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

    /**
     * Baris upload satu periode (null bila belum ada).
     */
    public static function find(string $period): ?array
    {
        $stmt = Database::getInstance()->prepare(
            'SELECT id, period, file_name, total_rows, total_people, standard_days, uploaded_by, created_at
               FROM bak_attendance_uploads WHERE period = ?'
        );
        $stmt->execute([$period]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /* ===================================================================== */
    /* Rekap                                                                 */
    /* ===================================================================== */

    /**
     * Rekap per posisi untuk satu periode.
     *
     * @return array<string, mixed>
     */
    public static function summary(string $period): array
    {
        $upload = self::find($period);

        if ($upload === null) {
            return [
                'period'          => $period,
                'uploaded'        => false,
                'file_name'       => '',
                'total_rows'      => 0,
                'total_people'    => 0,
                'standard_days'   => 0,
                'days_in_month'   => self::monthLength($period),
                'uploaded_at'     => '',
                'positions'       => [],
                'employees'       => [],
                'other_codes'     => [],
                'missing_in_rate' => [],
                'absent_in_data'  => [],
                'totals'          => [
                    'people' => 0, 'quantity' => 0,
                    'h' => 0, 'sc' => 0, 'sick' => 0, 'ct' => 0, 'off' => 0,
                    'subtotal' => 0, 'counted' => 0, 'missing_days' => 0,
                    'billable' => 0, 'excluded' => 0,
                ],
            ];
        }

        $db       = Database::getInstance();
        $uploadId = (int) $upload['id'];
        $standard = (int) $upload['standard_days'];

        // Orang + hari masuk sekaligus bahan rekap per orang
        $stmt = $db->prepare(
            'SELECT position, employee_id, person_name, remarks, scheduled_days, days_override, active_override
               FROM bak_attendance_people
              WHERE upload_id = ?
              ORDER BY id'
        );
        $stmt->execute([$uploadId]);

        $people    = [];
        $employees = [];
        // Nama orang per posisi yang hari masuknya kurang dari standar,
        // dipakai untuk menjelaskan variance di Accrual Input.
        $shortByPosition = [];

        $personRows = $stmt->fetchAll();

        // Berkas lama tanpa kolom Remarks: semua orang dianggap Billable.
        $hasRemarks = false;
        foreach ($personRows as $row) {
            if (trim((string) ($row['remarks'] ?? '')) !== '') {
                $hasRemarks = true;
                break;
            }
        }

        $daysInMonth = self::monthLength($period);

        $excluded = 0;
        foreach ($personRows as $row) {
            $billable = self::isBillable($row['remarks'] ?? '', $hasRemarks);
            if (!$billable) {
                $excluded++;
            }
            $p = (string) $row['position'];
            $auto   = (int) $row['scheduled_days'];
            $manual = $row['days_override'] !== null && $row['days_override'] !== '';
            // Hari masuk efektif: koreksi manual menang atas hasil Excel.
            $days   = $manual ? max(0.0, (float) $row['days_override']) : (float) $auto;

            // --- Rate = porsi bulan aktif (acuan kalender, bukan roster) ---
            // Sebulan penuh selalu 1,00 walau Off-nya 6/9/10 hari. Yang bisa
            // memotong hanya tanggal resign yang tertulis di Remarks.
            $resign    = self::resignDate($row['remarks'] ?? '');
            $activeAuto = $daysInMonth;
            if ($resign !== null) {
                $resignPeriod = substr($resign, 0, 7);
                if ($resignPeriod === $period) {
                    // Resign di bulan berjalan: aktif s/d tanggal resign (inklusif).
                    $activeAuto = min($daysInMonth, max(0, (int) substr($resign, 8, 2)));
                } elseif ($resign < $period . '-01') {
                    // Sudah resign sebelum periode ini dimulai.
                    $activeAuto = 0;
                }
            }
            $activeManual = $row['active_override'] !== null && $row['active_override'] !== '';
            $active = $activeManual
                ? max(0.0, min((float) $daysInMonth, (float) $row['active_override']))
                : (float) $activeAuto;

            // Rate dibulatkan ROUND 2 desimal; angka hasil pembulatan itu
            // yang dipakai menghitung uang.
            $ratio = $daysInMonth > 0
                ? round(min(1.0, $active / $daysInMonth), self::RATE_DECIMALS)
                : 1.0;

            // Hanya Billable yang masuk perhitungan posisi & tahap berikutnya.
            if ($billable) {
                if (!isset($people[$p])) {
                    $people[$p] = ['head' => 0, 'qty' => 0.0, 'partial' => 0];
                }
                $people[$p]['head']++;
                // Sum Rate = penjumlahan rate per orang yang sudah dibulatkan.
                $people[$p]['qty'] += $ratio;
                if ($ratio < 1.0) {
                    $people[$p]['partial']++;
                }

                // Hari kerja kurang dari standar hanya jadi CATATAN variance
                // (tidak memotong rate), supaya finance bisa menegur manual.
                if ($standard > 0 && $days < $standard) {
                    $shortByPosition[$p][] = [
                        'name'    => (string) $row['person_name'],
                        'days'    => round($days, 2),
                        'missing' => round($standard - $days, 2),
                        'manual'  => $manual,
                    ];
                }
            }

            $employees[] = [
                'position'      => $p,
                'employee_id'   => (string) ($row['employee_id'] ?? ''),
                'name'          => (string) $row['person_name'],
                'remarks'       => (string) ($row['remarks'] ?? ''),
                'billable'      => $billable,
                'days'          => $days,
                'days_auto'     => $auto,
                'manual'        => $manual,
                'active_days'   => $active,
                'active_auto'   => (float) $activeAuto,
                'active_manual' => $activeManual,
                'resign_date'   => $resign ?? '',
                'rate'          => $ratio,
            ];
        }

        // Hari per posisi & kode — hanya baris milik orang Billable.
        $remarksOf = [];
        foreach ($personRows as $row) {
            $remarksOf[(string) $row['position'] . '|' . (string) $row['person_name']]
                = (string) ($row['remarks'] ?? '');
        }

        $stmt = $db->prepare(
            'SELECT position, person_name, code, day_rows FROM bak_attendance_days WHERE upload_id = ?'
        );
        $stmt->execute([$uploadId]);

        $pos = [];
        foreach ($stmt->fetchAll() as $row) {
            $p      = (string) $row['position'];
            $person = trim((string) ($row['person_name'] ?? ''));

            // Baris lama (sebelum ada person_name) tetap dihitung.
            if ($person !== '') {
                $remarks = $remarksOf[$p . '|' . $person] ?? '';
                if (!self::isBillable($remarks, $hasRemarks)) {
                    continue;
                }
            }

            if (!isset($pos[$p])) {
                $pos[$p] = ['position' => $p, 'h' => 0, 'sc' => 0, 'sick' => 0, 'ct' => 0, 'off' => 0, 'other' => 0];
            }
            $code = (string) $row['code'];
            $days = (int) $row['day_rows'];

            if ($code === '') {
                $pos[$p]['off'] += $days;
            } elseif ($code === self::CODE_H) {
                $pos[$p]['h'] += $days;
            } elseif ($code === self::CODE_SC) {
                $pos[$p]['sc'] += $days;
            } elseif ($code === self::CODE_SICK) {
                $pos[$p]['sick'] += $days;
            } elseif ($code === self::CODE_CT) {
                $pos[$p]['ct'] += $days;
            } elseif ($code === self::CODE_OFF) {
                $pos[$p]['off'] += $days;
            } else {
                $pos[$p]['other'] += $days;
            }
        }

        // Posisi yang punya orang Billable selalu muncul walau baris harinya kosong.
        foreach (array_keys($people) as $name) {
            if (!isset($pos[$name])) {
                $pos[$name] = [
                    'position' => $name, 'h' => 0, 'sc' => 0, 'sick' => 0, 'ct' => 0, 'off' => 0, 'other' => 0,
                ];
            }
        }

        // Harga: bak_position_prices menimpa rate card
        $rate = [];
        foreach ($db->query('SELECT role, category, price_per_unit FROM max_potential_earnings')->fetchAll() as $r) {
            $rate[(string) $r['role']] = [
                'category' => (string) ($r['category'] ?? ''),
                'price'    => (float) $r['price_per_unit'],
                'source'   => 'rate',
            ];
        }

        $stmt = $db->prepare('SELECT position, category, price, is_custom FROM bak_position_prices WHERE period = ?');
        $stmt->execute([$period]);
        foreach ($stmt->fetchAll() as $r) {
            $rate[(string) $r['position']] = [
                'category' => (string) ($r['category'] ?? ''),
                'price'    => (float) $r['price'],
                'source'   => ((int) $r['is_custom'] === 1) ? 'custom' : 'period',
            ];
        }

        $positions = [];
        $totals    = [
            'people' => 0, 'quantity' => 0.0,
            'h' => 0, 'sc' => 0, 'sick' => 0, 'ct' => 0, 'off' => 0,
            'subtotal' => 0.0, 'counted' => 0.0, 'missing_days' => 0.0,
            'billable' => 0, 'excluded' => $excluded,
        ];
        $missing   = [];

        foreach (array_keys($pos) as $name) {
            $p   = $pos[$name];
            $pp  = $people[$name] ?? ['head' => 0, 'qty' => 0.0, 'partial' => 0];
            $r   = $rate[$name] ?? null;

            // Sum Rate: total rate per orang yang sudah dibulatkan (ROUND 2).
            $qty   = round($pp['qty'], self::RATE_DECIMALS);
            $price = $r['price'] ?? 0.0;
            $sub   = round($price * $qty, 2);

            if ($r === null) {
                $missing[] = $name;
            }

            // Orang di posisi ini yang hari kerjanya kurang dari standar.
            $short = $shortByPosition[$name] ?? [];
            usort($short, static function (array $a, array $b): int {
                if ($a['missing'] !== $b['missing']) {
                    return $b['missing'] <=> $a['missing'];
                }
                return strcasecmp($a['name'], $b['name']);
            });
            $missingDays = 0.0;
            foreach ($short as $item) {
                $missingDays += (float) $item['missing'];
            }

            $positions[] = [
                'position'     => $name,
                'category'     => $r['category'] ?? '',
                'known'        => $r !== null,
                'price'        => $price,
                'head'         => $pp['head'],
                'quantity'     => $qty,
                'partial'      => $pp['partial'],
                'h'            => $p['h'],
                'sc'           => $p['sc'],
                'sick'         => $p['sick'],
                'ct'           => $p['ct'],
                'off'          => $p['off'],
                'other'        => $p['other'],
                'subtotal'     => $sub,
                'shortfalls'   => $short,
                'missing_days' => round($missingDays, 2),
            ];

            $totals['people']   += $pp['head'];
            $totals['quantity'] += $qty;
            $totals['h']        += $p['h'];
            $totals['sc']       += $p['sc'];
            $totals['sick']     += $p['sick'];
            $totals['ct']       += $p['ct'];
            $totals['off']      += $p['off'];
            $totals['subtotal'] += $sub;
            $totals['missing_days'] += $missingDays;
        }

        // Posisi dikenali lebih dulu, lalu urut nama
        usort($positions, static function (array $a, array $b): int {
            if ($a['known'] !== $b['known']) {
                return $a['known'] ? -1 : 1;
            }
            return strcasecmp($a['position'], $b['position']);
        });

        // Rekap per orang: Unit Price mengikuti posisi, Counting Price = rate x harga.
        $rank = [];
        foreach ($positions as $i => $p) {
            $rank[$p['position']] = $i;
        }
        $priceOf = array_column($positions, 'price', 'position');

        $countedTotal = 0.0;
        $billableCount = 0;
        foreach ($employees as $i => $e) {
            $price    = (float) ($priceOf[$e['position']] ?? 0.0);
            $empRate  = round($e['rate'], self::RATE_DECIMALS);
            // Yang bukan Billable tetap tampil di rekap, tapi tidak dihitung.
            $counted = $e['billable'] ? round($price * $empRate, 2) : 0.0;
            if ($e['billable']) {
                $billableCount++;
            }

            $employees[$i]['rate']    = $empRate;
            $employees[$i]['price']   = $price;
            $employees[$i]['counted'] = $counted;
            $countedTotal            += $counted;
        }

        usort($employees, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[$a['position']] ?? PHP_INT_MAX;
            $rb = $rank[$b['position']] ?? PHP_INT_MAX;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        $totals['quantity'] = round($totals['quantity'], 2);
        $totals['subtotal'] = round($totals['subtotal'], 2);
        $totals['counted']  = round($countedTotal, 2);
        $totals['missing_days'] = round($totals['missing_days'], 2);
        $totals['billable'] = $billableCount;

        // Posisi di rate card yang tidak ada di absensi periode ini
        $present = array_column($positions, 'position');
        $absent  = [];
        foreach (array_keys($rate) as $role) {
            if (!in_array($role, $present, true)) {
                $absent[] = $role;
            }
        }
        sort($absent);

        return [
            'period'          => $period,
            'uploaded'        => true,
            'file_name'       => (string) ($upload['file_name'] ?? ''),
            'total_rows'      => (int) $upload['total_rows'],
            'total_people'    => (int) $upload['total_people'],
            'standard_days'   => $standard,
            'days_in_month'   => $daysInMonth,
            'uploaded_at'     => (string) ($upload['created_at'] ?? ''),
            'positions'       => $positions,
            'employees'       => $employees,
            'other_codes'     => [],
            'missing_in_rate' => $missing,
            'absent_in_data'  => $absent,
            'totals'          => $totals,
        ];
    }

    /* ===================================================================== */
    /* Harga per posisi                                                      */
    /* ===================================================================== */

    /**
     * Kategori bawaan untuk posisi yang tidak ada di rate card.
     */
    public const DEFAULT_CATEGORY = 'Posisi baru';

    /**
     * Simpan harga per posisi untuk satu periode.
     *
     * @param array<string, float|int|string> $prices position => harga
     */
    public static function savePrices(string $period, array $prices): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Periode harus berformat YYYY-MM.');
        }

        $db = Database::getInstance();

        $db->beginTransaction();
        try {
            $sel = $db->prepare('SELECT id, category, is_custom FROM bak_position_prices WHERE period = ? AND position = ?');
            $upd = $db->prepare('UPDATE bak_position_prices SET price = ?, category = ? WHERE id = ?');
            $ins = $db->prepare(
                'INSERT INTO bak_position_prices (period, position, category, price, is_custom)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $catStmt = $db->prepare('SELECT category FROM max_potential_earnings WHERE role = ?');

            foreach ($prices as $position => $price) {
                $position = trim((string) $position);
                if ($position === '') {
                    continue;
                }
                if (!is_numeric($price)) {
                    throw new \InvalidArgumentException("Harga untuk {$position} harus berupa angka.");
                }

                $value = round(max(0.0, (float) $price), 2);

                // Kategori: rate card -> kategori lama -> "Posisi baru".
                $catStmt->execute([$position]);
                $rateCat = $catStmt->fetchColumn();
                $fallback = ($rateCat === false || trim((string) $rateCat) === '')
                    ? self::DEFAULT_CATEGORY
                    : (string) $rateCat;

                $sel->execute([$period, $position]);
                $existing = $sel->fetch();

                if ($existing) {
                    $oldCat = trim((string) ($existing['category'] ?? ''));
                    $upd->execute([$value, $oldCat === '' ? $fallback : $oldCat, (int) $existing['id']]);
                } else {
                    $ins->execute([$period, $position, $fallback, $value, 0]);
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // Harga yang baru diubah langsung mengalir ke Max Potential Earnings.
        self::syncQuietly($period);
    }

    /**
     * Koreksi manual hari masuk & hari aktif per orang (per periode).
     *
     * Nilai default tetap dari Excel (Working days) dan dari tanggal resign di
     * Remarks (Hari aktif). Kolom override hanya menampung koreksi bila data
     * berkas salah. Kirim nilai `null` untuk mengembalikan ke nilai otomatis.
     *
     * @param array<int, array{position?:string, name?:string, days?:int|float|string|null, active?:int|float|string|null}> $entries
     * @return array<string, mixed> ringkasan terbaru
     */
    public static function saveEmployeeDays(string $period, array $entries): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Periode harus berformat YYYY-MM.');
        }

        $upload = self::find($period);
        if ($upload === null) {
            throw new \RuntimeException('Belum ada data absensi untuk periode ini.');
        }

        $db          = Database::getInstance();
        $uploadId    = (int) $upload['id'];
        $daysInMonth = self::monthLength($period);
        $updDays     = $db->prepare(
            'UPDATE bak_attendance_people
                SET days_override = ?
              WHERE upload_id = ? AND position = ? AND person_name = ?'
        );
        $updActive   = $db->prepare(
            'UPDATE bak_attendance_people
                SET active_override = ?
              WHERE upload_id = ? AND position = ? AND person_name = ?'
        );

        /** Baca nilai angka dari payload; null/kosong berarti kembali ke otomatis. */
        $toNumber = static function ($raw, string $label, string $name): ?float {
            if ($raw === null || $raw === '') {
                return null;
            }
            if (!is_numeric($raw)) {
                throw new \InvalidArgumentException("{$label} untuk {$name} harus berupa angka.");
            }

            return round(max(0.0, (float) $raw), 2);
        };

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name     = trim((string) ($entry['name'] ?? ''));
            $position = trim((string) ($entry['position'] ?? ''));
            if ($name === '' || $position === '') {
                continue;
            }

            if (array_key_exists('days', $entry)) {
                $days = $toNumber($entry['days'], 'Working days', $name);
                $updDays->execute([$days, $uploadId, $position, $name]);
            }

            if (array_key_exists('active', $entry)) {
                $active = $toNumber($entry['active'], 'Hari aktif', $name);
                if ($active !== null) {
                    // Hari aktif tidak boleh melebihi jumlah hari bulan itu.
                    $active = min((float) $daysInMonth, $active);
                }
                $updActive->execute([$active, $uploadId, $position, $name]);
            }
        }

        // Rate mengikuti hari aktif, jadi rate card langsung ikut menyesuaikan.
        self::syncQuietly($period);

        return self::summary($period);
    }

    /**
     * Tambahkan posisi baru (di luar rate card) ke periode ini.
     *
     * @param array<int, string> $positions
     */
    public static function addPositions(string $period, array $positions, ?string $category = null): void
    {
        $db   = Database::getInstance();
        $sel  = $db->prepare('SELECT id FROM bak_position_prices WHERE period = ? AND position = ?');
        $ins  = $db->prepare(
            'INSERT INTO bak_position_prices (period, position, category, price, is_custom)
             VALUES (?, ?, ?, 0, 1)'
        );

        $cat = ($category === null || trim($category) === '') ? self::DEFAULT_CATEGORY : $category;

        foreach ($positions as $position) {
            $position = trim((string) $position);
            if ($position === '') {
                continue;
            }
            $sel->execute([$period, $position]);
            if ($sel->fetchColumn() === false) {
                $ins->execute([$period, $position, $cat]);
            }
        }

        // Posisi baru langsung masuk ke rate card Max Potential Earnings.
        self::syncQuietly($period);
    }

    /**
     * Jalankan sinkron tanpa menggagalkan proses pemanggil.
     *
     * Dipakai setelah upload/harga berubah: rate card Max Potential mengikuti
     * berkas secara otomatis, tetapi kegagalan sinkron tidak boleh membatalkan
     * data absensi yang sudah tersimpan.
     */
    private static function syncQuietly(string $period): void
    {
        try {
            self::syncToMaxPotential($period);
        } catch (\Throwable $e) {
            error_log('BakAttendance syncQuietly(' . $period . '): ' . $e->getMessage());
        }
    }

    /* ===================================================================== */
    /* Sinkron ke Max Potential Earnings                                     */
    /* ===================================================================== */

    /**
     * Terapkan hasil rekap ke rate card BAK.
     *
     * Aturan:
     *   - Posisi yang ada di absensi  -> price_per_unit = harga, quantity = prorata
     *   - Posisi di rate card tapi tidak ada di absensi -> quantity = 0
     *   - Posisi baru -> dibuatkan baris rate card
     *
     * @return array<string, mixed> isi max potential terbaru
     */
    public static function syncToMaxPotential(string $period): array
    {
        $summary = self::summary($period);
        if (!$summary['uploaded']) {
            throw new \RuntimeException('Belum ada data absensi untuk periode ini.');
        }

        $db        = Database::getInstance();
        $positions = $summary['positions'];

        $db->beginTransaction();
        try {
            $find = $db->prepare('SELECT id, price_per_unit, price_manual FROM max_potential_earnings WHERE role = ?');
            $ins  = $db->prepare(
                'INSERT INTO max_potential_earnings
                    (role, category, billing_type, price_per_unit, quantity, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $upd  = $db->prepare(
                'UPDATE max_potential_earnings
                    SET category = COALESCE(?, category),
                        billing_type = ?,
                        price_per_unit = ?,
                        quantity = ?,
                        subtotal = ?
                  WHERE id = ?'
            );

            $seen = [];
            foreach ($positions as $p) {
                $role  = (string) $p['position'];
                $seen[] = $role;
                $price = round((float) $p['price'], 2);
                $qty   = round((float) $p['quantity'], 2);
                $cat   = $p['category'] !== '' ? $p['category'] : null;

                $find->execute([$role]);
                $existing = $find->fetch();

                if ($existing === false || $existing === null) {
                    $ins->execute([$role, $cat, 'Headcount', $price, $qty, round($price * $qty, 2)]);
                    continue;
                }

                // Harga yang sudah diubah manual di Max Potential tidak ditimpa
                // hasil hitung WFM; rate tetap diperbarui.
                if ((int) ($existing['price_manual'] ?? 0) === 1) {
                    $price = round((float) $existing['price_per_unit'], 2);
                }
                $upd->execute([$cat, 'Headcount', $price, $qty, round($price * $qty, 2), (int) $existing['id']]);
            }

            // Rate card yang tidak ada di absensi periode ini: rate (quantity)
            // dikosongkan supaya tampil 0, tetapi harga manual, nilai Max
            // Potential manual, dan role tambahan manual tetap dipertahankan.
            foreach ($db->query('SELECT id, role, price_per_unit FROM max_potential_earnings')->fetchAll() as $row) {
                if (in_array((string) $row['role'], $seen, true)) {
                    continue;
                }
                $db->prepare(
                    'UPDATE max_potential_earnings SET quantity = 0, subtotal = 0 WHERE id = ?'
                )->execute([(int) $row['id']]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $sum = MaxPotentialEarning::summary();

        return [
            'items'       => MaxPotentialEarning::all(),
            'categories'  => $sum['categories'],
            'grand_total' => $sum['grand_total'],
        ];
    }
}
