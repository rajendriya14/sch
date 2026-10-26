<?php

namespace Services;

/**
 * Kontrak penyedia harga emas otomatis.
 *
 * Implementasi wajib "gagal dengan tenang": kembalikan null bila sumber
 * tidak bisa dibaca, jangan melempar exception ke halaman.
 */
interface GoldPriceProvider
{
    /** Kunci singkat penyedia, mis. "harga-emas.org". */
    public function key(): string;

    /** Nama tampilan penyedia. */
    public function label(): string;

    /**
     * Ambil harga terbaru.
     *
     * @return array|null Format:
     *   [
     *     'source' => string,
     *     'source_url' => string,
     *     'fetched_at' => 'Y-m-d H:i:s',
     *     'spot' => ['idr_gram' => ?float, 'usd_oz' => ?float, 'usd_idr' => ?float],
     *     'brands' => [
     *        'antam' => ['weights' => [1.0 => ['buy' => float, 'buyback' => ?float]]],
     *     ],
     *   ]
     */
    public function fetch(): ?array;
}
