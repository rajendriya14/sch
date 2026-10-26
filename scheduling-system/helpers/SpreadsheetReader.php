<?php

namespace Helpers;

/**
 * Pembaca berkas spreadsheet sederhana (tanpa library eksternal).
 *
 * Dipakai untuk membaca rekap absensi BAK. Mendukung:
 *   - .xlsx  : dibaca langsung dari arsip ZIP-nya (deflate via zlib),
 *              tidak memerlukan ekstensi ZipArchive.
 *   - .csv / .txt / paste dari Excel : dipisah tab, koma, atau titik koma.
 *
 * Keluaran selalu berupa matriks baris x kolom (indeks 0..n) berisi string,
 * sehingga pemanggil tidak perlu tahu format asalnya.
 */
final class SpreadsheetReader
{
    /** Batas baris agar satu berkas besar tidak menghabiskan memori. */
    private const MAX_ROWS = 60000;

    /** Kedalaman maksimum XML yang dipakai XMLReader. */
    private const MAX_DEPTH = 64;

    /**
     * Baca isi berkas menjadi matriks baris.
     *
     * @param string $bytes    Isi berkas (bukan path).
     * @param string $fileName Nama berkas, dipakai untuk menebak format.
     * @return array<int, array<int, string>>
     */
    public static function read(string $bytes, string $fileName = ''): array
    {
        $name = strtolower($fileName);

        // XLSX dikenali dari tanda tangan ZIP-nya, bukan dari ekstensi,
        // supaya berkas yang diganti namanya tetap terbaca.
        if (strncmp($bytes, "PK\x03\x04", 4) === 0) {
            return self::readXlsx($bytes);
        }

        if (str_ends_with($name, '.xlsx') || str_ends_with($name, '.xlsm')) {
            throw new \RuntimeException('Berkas .xlsx tidak valid (bukan arsip ZIP).');
        }

        // Format teks: tab (paste dari Excel) lebih diutamakan daripada koma,
        // karena isi sel bisa mengandung koma.
        return self::readDelimited($bytes);
    }

    /* ===================================================================== */
    /* XLSX                                                                  */
    /* ===================================================================== */

    /**
     * @return array<int, array<int, string>>
     */
    private static function readXlsx(string $bytes): array
    {
        $entries = self::zipEntries($bytes);

        $shared = [];
        if (isset($entries['xl/sharedStrings.xml'])) {
            $shared = self::parseSharedStrings(self::zipExtract($bytes, $entries['xl/sharedStrings.xml']));
        }

        $sheetPath = self::firstSheetPath($bytes, $entries);

        if (!isset($entries[$sheetPath])) {
            throw new \RuntimeException('Sheet pertama tidak ditemukan di dalam berkas .xlsx.');
        }

        return self::parseSheet(self::zipExtract($bytes, $entries[$sheetPath]), $shared);
    }

    /**
     * Cari path sheet pertama. workbook.xml memetakan rId ke berkas;
     * kalau pemetaan gagal, pakai xl/worksheets/sheet1.xml.
     */
    private static function firstSheetPath(string $bytes, array $entries): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';

        if (!isset($entries['xl/workbook.xml']) || !isset($entries['xl/_rels/workbook.xml.rels'])) {
            return $fallback;
        }

        try {
            $wb   = self::zipExtract($bytes, $entries['xl/workbook.xml']);
            $rels = self::zipExtract($bytes, $entries['xl/_rels/workbook.xml.rels']);
        } catch (\Throwable $e) {
            return $fallback;
        }

        if (!preg_match('#<sheet\b[^>]*r:id="([^"]+)"#i', $wb, $m)) {
            return $fallback;
        }
        $rid = $m[1];

        if (preg_match('#<Relationship\b[^>]*Id="' . preg_quote($rid, '#') . '"[^>]*Target="([^"]+)"#i', $rels, $r)) {
            $target = ltrim($r[1], '/');
            if (strpos($target, 'xl/') !== 0) {
                $target = 'xl/' . $target;
            }
            if (isset($entries[$target])) {
                return $target;
            }
        }

        return $fallback;
    }

    /**
     * Daftar entri ZIP: nama => informasi kompresi.
     *
     * Dibaca dari central directory supaya ukuran terkompresi akurat
     * (local header bisa menyimpan 0 bila memakai data descriptor).
     *
     * @return array<string, array{method:int, compSize:int, localOffset:int}>
     */
    private static function zipEntries(string $bytes): array
    {
        $eocd = strrpos($bytes, "PK\x05\x06");
        if ($eocd === false) {
            throw new \RuntimeException('Arsip ZIP rusak: penanda akhir tidak ditemukan.');
        }

        $end = unpack(
            'Vsig/vdisk/vcdDisk/ventriesDisk/ventries/Vsize/Voffset/vcommentLen',
            substr($bytes, $eocd, 22)
        );

        $entries = [];
        $offset  = (int) $end['offset'];
        $total   = (int) $end['entries'];
        $len     = strlen($bytes);

        for ($i = 0; $i < $total; $i++) {
            if ($offset + 46 > $len || substr($bytes, $offset, 4) !== "PK\x01\x02") {
                break;
            }

            $h = unpack(
                'Vsig/vmadeBy/vmadeVer/vflags/vmethod/vtime/vdate/Vcrc/VcompSize/VuncompSize/'
                . 'vnameLen/vextraLen/vcommentLen/vdisk/vinternal/Vexternal/VlocalOffset',
                substr($bytes, $offset, 46)
            );

            $name = substr($bytes, $offset + 46, (int) $h['nameLen']);
            $entries[$name] = [
                'method'      => (int) $h['method'],
                'compSize'    => (int) $h['compSize'],
                'localOffset' => (int) $h['localOffset'],
            ];

            $offset += 46 + (int) $h['nameLen'] + (int) $h['extraLen'] + (int) $h['commentLen'];
        }

        return $entries;
    }

    /**
     * Ambil isi satu entri ZIP (stored atau deflate).
     */
    private static function zipExtract(string $bytes, array $entry): string
    {
        $lo = $entry['localOffset'];
        if ($lo + 30 > strlen($bytes) || substr($bytes, $lo, 4) !== "PK\x03\x04") {
            throw new \RuntimeException('Arsip ZIP rusak: header lokal tidak valid.');
        }

        $h = unpack(
            'Vsig/vver/vflags/vmethod/vtime/vdate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen',
            substr($bytes, $lo, 30)
        );

        $start = $lo + 30 + (int) $h['nameLen'] + (int) $h['extraLen'];
        $raw   = substr($bytes, $start, $entry['compSize']);

        if ($entry['method'] === 0) {
            return $raw;
        }

        if ($entry['method'] === 8) {
            $out = @gzinflate($raw);
            if ($out === false) {
                throw new \RuntimeException('Gagal membuka data .xlsx (deflate).');
            }
            return $out;
        }

        throw new \RuntimeException('Metode kompresi .xlsx tidak didukung: ' . $entry['method']);
    }

    /**
     * sharedStrings.xml -> daftar teks.
     *
     * @return array<int, string>
     */
    private static function parseSharedStrings(string $xml): array
    {
        $out = [];
        $reader = new \XMLReader();
        if (!$reader->XML($xml, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return $out;
        }

        while (@$reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $inner = $reader->readOuterXml();
                $out[] = self::textOf($inner);
            }
        }
        $reader->close();

        return $out;
    }

    /** Ambil gabungan isi tag <t> dari potongan XML. */
    private static function textOf(string $xml): string
    {
        if (!preg_match_all('#<t\b[^>]*>(.*?)</t>#s', $xml, $m)) {
            return '';
        }
        return self::decode(implode('', $m[1]));
    }

    /** Decode entitas XML tanpa mengubah tag. */
    private static function decode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * sheet.xml -> matriks baris.
     *
     * @param array<int, string> $shared
     * @return array<int, array<int, string>>
     */
    private static function parseSheet(string $xml, array $shared): array
    {
        $rows   = [];
        $reader = new \XMLReader();
        if (!$reader->XML($xml, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new \RuntimeException('Sheet .xlsx tidak bisa dibaca.');
        }

        while (@$reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                break;
            }

            $rowXml = $reader->readOuterXml();
            $rows[] = self::parseRow($rowXml, $shared);
        }
        $reader->close();

        return $rows;
    }

    /**
     * @param array<int, string> $shared
     * @return array<int, string>
     */
    private static function parseRow(string $rowXml, array $shared): array
    {
        $cells = [];

        // Sel berisi nilai: <c r="D2" t="s"><v>12</v></c>
        // Atribut dibuat lazy supaya sel kosong berbentuk <c r="A2" s="5"/>
        // tidak "menelan" sel berikutnya.
        if (preg_match_all('#<c\b([^>]*?)(?:/>|>(.*?)</c>)#s', $rowXml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $attrs = $m[1];
                $inner = $m[2] ?? '';

                if (!preg_match('#\br="([A-Z]+)\d+"#i', $attrs, $ref)) {
                    continue;
                }
                $col = self::colIndex(strtoupper($ref[1]));

                $type  = preg_match('#\bt="([^"]+)"#i', $attrs, $t) ? strtolower($t[1]) : '';
                $value = '';

                if ($type === 's') {
                    if (preg_match('#<v>(.*?)</v>#s', $inner, $v)) {
                        $idx   = (int) self::decode($v[1]);
                        $value = $shared[$idx] ?? '';
                    }
                } elseif ($type === 'inlineStr') {
                    $value = self::textOf($inner);
                } elseif ($type === 'b') {
                    if (preg_match('#<v>(.*?)</v>#s', $inner, $v)) {
                        $value = $v[1] === '1' ? 'TRUE' : 'FALSE';
                    }
                } else {
                    if (preg_match('#<v>(.*?)</v>#s', $inner, $v)) {
                        $value = self::decode($v[1]);
                    }
                }

                $cells[$col] = trim($value);
            }
        }

        if (!$cells) {
            return [];
        }

        // Rapikan agar indeks kolom berurutan tanpa lubang.
        $max  = max(array_keys($cells));
        $line = [];
        for ($i = 0; $i <= $max; $i++) {
            $line[$i] = $cells[$i] ?? '';
        }
        return $line;
    }

    /** "A" => 0, "B" => 1, "AA" => 26. */
    private static function colIndex(string $letters): int
    {
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }

    /* ===================================================================== */
    /* CSV / TSV / paste                                                     */
    /* ===================================================================== */

    /**
     * @return array<int, array<int, string>>
     */
    private static function readDelimited(string $bytes): array
    {
        // Buang BOM UTF-8 bila ada.
        if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) {
            $bytes = substr($bytes, 3);
        }

        $lines = preg_split("/\r\n|\r|\n/", $bytes) ?: [];
        $out   = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (count($out) >= self::MAX_ROWS) {
                break;
            }
            if (strpos($line, "\t") !== false) {
                $out[] = array_map('trim', explode("\t", $line));
            } else {
                $out[] = array_map('trim', str_getcsv($line, ',', '"', ''));
            }
        }

        return $out;
    }
}
