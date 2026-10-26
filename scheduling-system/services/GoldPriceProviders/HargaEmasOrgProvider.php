<?php

namespace Services\GoldPriceProviders;

use Models\FfrGoldPrice;
use Services\GoldPriceProvider;

/**
 * Penyedia harga dari halaman publik harga-emas.org.
 *
 * Halaman menyajikan tabel HTML:
 *   - Tabel spot: Ounce (oz) & Gram (gr) dalam USD dan IDR.
 *   - Tabel Antam + Pegadaian: harga beli per pecahan; buyback Antam
 *     tercantum pada baris catatan ("Harga pembelian kembali: Rp…/gr").
 *   - Tabel UBS: kolom "Jual" (harga beli) dan "Beli" (buyback) per pecahan.
 *
 * Semua kelas CSS situs memakai hash, jadi parsing dilakukan berdasarkan
 * teks header & isi sel — bukan nama kelas. Bila struktur berubah, metode
 * fetch() mengembalikan null dan halaman memakai harga manual/terakhir.
 */
class HargaEmasOrgProvider implements GoldPriceProvider
{
    private const URL = 'https://harga-emas.org/';
    private const TIMEOUT_SECONDS = 10;

    public function key(): string
    {
        return 'harga-emas.org';
    }

    public function label(): string
    {
        return 'harga-emas.org';
    }

    public function fetch(): ?array
    {
        $html = $this->download(self::URL);
        if ($html === null || $html === '') {
            return null;
        }

        $tables = $this->tables($html);
        if (!$tables) {
            return null;
        }

        $spot = null;
        $brands = [];

        foreach ($tables as $table) {
            $rows = $this->rows($table);
            if (count($rows) < 2) {
                continue;
            }

            $header = array_map('mb_strtolower', $rows[0]);

            // --- Tabel spot (USD & IDR per ounce/gram) ---
            if ($spot === null && $this->hasAll($header, ['usd', 'idr'])) {
                $spot = $this->parseSpot($rows);
                continue;
            }

            // --- Tabel Antam + Pegadaian (satu tabel, dua kolom brand) ---
            if ($this->hasAll($header, ['antam', 'pegadaian'])) {
                $this->parseMultiBrand($rows, $brands);
                continue;
            }

            // --- Tabel UBS (Jual / Beli) ---
            if ($this->hasAll($header, ['jual', 'beli'])) {
                $this->parseUbs($rows, $brands);
            }
        }

        if (!$brands) {
            return null;
        }

        $fetchedAt = date('Y-m-d H:i:s');

        foreach ($brands as $brand => $payload) {
            $brands[$brand]['spot'] = $spot;
            $brands[$brand]['fetched_at'] = $fetchedAt;
            $brands[$brand]['source_url'] = self::URL;
        }

        return [
            'source'     => $this->key(),
            'source_url' => self::URL,
            'fetched_at' => $fetchedAt,
            'spot'       => $spot,
            'brands'     => $brands,
        ];
    }

    /** Unduh halaman; null bila gagal (tanpa mengganggu request lain). */
    private function download(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PradiyaPortal/1.0)',
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // curl_close() deprecated sejak PHP 8.5 (dan no-op sejak 8.0),
            // jadi handle dilepas otomatis — jangan dipanggil.

            if ($body !== false && $status >= 200 && $status < 300) {
                return (string) $body;
            }
        }

        // Fallback: sebagian hosting punya CA bundle cURL tidak lengkap
        // (mis. di balik proxy), sedangkan wrapper stream tetap bisa jalan.
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => self::TIMEOUT_SECONDS,
                'header'  => "User-Agent: Mozilla/5.0 (compatible; PradiyaPortal/1.0)\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        return $body === false ? null : (string) $body;
    }

    /** @return string[] daftar isi <table>…</table> */
    private function tables(string $html): array
    {
        preg_match_all('/<table\b[^>]*>(.*?)<\/table>/is', $html, $matches);
        return $matches[1] ?? [];
    }

    /**
     * @return array<int, array<int, string>> baris => sel (teks bersih)
     */
    private function rows(string $tableHtml): array
    {
        preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $tableHtml, $rows);

        $result = [];
        foreach ($rows[1] as $rowHtml) {
            preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $rowHtml, $cells);
            $texts = [];
            foreach ($cells[1] as $cell) {
                $texts[] = $this->text($cell);
            }
            $result[] = $texts;
        }
        return $result;
    }

    private function text(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function hasAll(array $cells, array $needles): bool
    {
        $joined = implode(' ', $cells);
        foreach ($needles as $needle) {
            if (mb_strpos($joined, $needle) === false) {
                return false;
            }
        }
        return true;
    }

    /** "2.424.368,92(+6.595,49)" → 2424368.92 ; "4.179,64" → 4179.64 */
    private function number(string $value): ?float
    {
        // Buang keterangan dalam tanda kurung (mis. selisih harian "(+6.595,49)")
        // supaya tidak ikut tergabung ke angka utama.
        $value = preg_replace('/\([^)]*\)/u', '', $value);
        $clean = preg_replace('/[^0-9,.\-]/', '', $value);
        if ($clean === '' || $clean === '-') {
            return null;
        }
        if (strpos($clean, ',') !== false) {
            return (float) str_replace(',', '.', str_replace('.', '', $clean));
        }
        return (float) str_replace('.', '', $clean);
    }

    /** Berat pada tabel: "0.5" → 0.5, "5" → 5, "1.000" → 1000. */
    private function weightValue(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d+\.\d{1,3}$/', $value)) {
            return (float) $value;
        }
        return $this->number($value);
    }

    /** Spot dari tabel "Satuan | USD | IDR". */
    private function parseSpot(array $rows): ?array
    {
        $usdOz = null;
        $idrGram = null;
        $usdGram = null;

        foreach ($rows as $row) {
            if (count($row) < 3) {
                continue;
            }
            $label = mb_strtolower($row[0]);
            $usd = $this->number($row[1]);
            $idr = $this->number($row[2]);

            if (mb_strpos($label, 'kilogram') !== false) {
                continue;
            }
            if (mb_strpos($label, 'ounce') !== false) {
                $usdOz = $usd;
            } elseif (mb_strpos($label, 'gram') !== false) {
                $usdGram = $usd;
                $idrGram = $idr;
            }
        }

        if ($usdOz === null && $usdGram !== null) {
            $usdOz = $usdGram * 31.1034768;
        }

        return [
            'idr_gram' => $idrGram,
            'usd_oz'   => $usdOz,
            'usd_idr'  => ($usdOz !== null && $idrGram !== null && $usdOz > 0)
                ? round($idrGram / ($usdOz / 31.1034768), 2)
                : null,
        ];
    }

    /**
     * Tabel "Satuan | Antam | Pegadaian": kolom 1 Antam, kolom 2 Pegadaian,
     * semuanya harga beli. Buyback Antam diambil dari baris catatan.
     */
    private function parseMultiBrand(array $rows, array &$brands): void
    {
        $header = array_map('mb_strtolower', $rows[0]);
        $columns = [];
        foreach ($header as $index => $label) {
            if (mb_strpos($label, 'antam') !== false) {
                $columns['antam'] = $index;
            } elseif (mb_strpos($label, 'pegadaian') !== false) {
                $columns['pegadaian'] = $index;
            }
        }
        if (!$columns) {
            return;
        }

        $buybackPerGram = null;

        // Pass 1: catatan buyback ada di baris paling bawah, jadi harus
        // dibaca sebelum baris harga dihitung.
        foreach ($rows as $row) {
            $joined = implode(' ', $row);
            if ($buybackPerGram === null
                && preg_match('/pembelian kembali[^0-9]*([0-9][0-9.,]*)/i', $joined, $match)) {
                $buybackPerGram = $this->number($match[1]);
            }
        }

        // Pass 2: harga beli per pecahan.
        foreach ($rows as $row) {
            if (count($row) < 2) {
                continue;
            }

            $weight = $this->weightValue($row[0]);
            if ($weight === null || !FfrGoldPrice::isSupportedWeight($weight)) {
                continue;
            }

            foreach ($columns as $brand => $index) {
                if (!isset($row[$index])) {
                    continue;
                }
                $buy = $this->number($row[$index]);
                if ($buy === null || $buy <= 0) {
                    continue;
                }

                $buyback = null;
                if ($brand === 'antam' && $buybackPerGram !== null) {
                    $buyback = round($buybackPerGram * $weight, 2);
                }

                $brands[$brand]['weights'][FfrGoldPrice::weightKey($weight)] = [
                    'buy'     => $buy,
                    'buyback' => $buyback,
                ];
            }
        }
    }

    /** Tabel UBS: kolom "Jual" = harga beli, "Beli" = buyback. */
    private function parseUbs(array $rows, array &$brands): void
    {
        $header = array_map('mb_strtolower', $rows[0]);
        $buyIndex = null;
        $buybackIndex = null;

        foreach ($header as $index => $label) {
            if (mb_strpos($label, 'jual') !== false) {
                $buyIndex = $index;
            } elseif (mb_strpos($label, 'beli') !== false) {
                $buybackIndex = $index;
            }
        }

        if ($buyIndex === null || $buybackIndex === null) {
            return;
        }

        foreach ($rows as $row) {
            if (count($row) <= max($buyIndex, $buybackIndex)) {
                continue;
            }
            $weight = $this->weightValue($row[0]);
            if ($weight === null || !FfrGoldPrice::isSupportedWeight($weight)) {
                continue;
            }

            $buy = $this->number($row[$buyIndex]);
            if ($buy === null || $buy <= 0) {
                continue;
            }

            $brands['ubs']['weights'][FfrGoldPrice::weightKey($weight)] = [
                'buy'     => $buy,
                'buyback' => $this->number($row[$buybackIndex]),
            ];
        }
    }
}
