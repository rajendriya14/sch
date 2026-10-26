/**
 * =============================================================================
 * BAKStepper.tsx
 * -----------------------------------------------------------------------------
 * 3-step wizard untuk BAK (Berita Acara Kerja).
 *
 *   Step 1 - Max Potential Earnings  (read-only)
 *            GET  /api/bak/max-potential-earnings
 *   Step 2 - Accrual Input           (editable + debounce autosave 2s)
 *            GET  /api/bak/seller-input
 *            POST /api/bak/seller-input
 *   Step 3 - Actual Input            (finance verification + approve/lock)
 *            GET  /api/bak/actual-input
 *            POST /api/bak/actual-input
 *            POST /api/bak/actual-input/{id}/approve
 *
 * Sifat komponen:
 *   - Self-contained: hanya butuh `react` (tanpa axios / UI lib / Tailwind).
 *   - Styling memakai blok CSS ber-namespace `.bak-*` di dalam file ini supaya
 *     responsive (media query) tanpa perlu konfigurasi build tambahan.
 *   - Semua data dinormalisasi di `normalize*()` sehingga toleran terhadap variasi
 *     bentuk response API. Sesuaikan hanya jika struktur API sangat berbeda.
 *
 * Cara pakai:
 *   import BAKStepper from './BAKStepper';
 *   <BAKStepper apiBaseUrl="/api/bak" />
 * =============================================================================
 */

import React, {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from 'react';
import type { ChangeEvent, ReactNode } from 'react';

/* ========================================================================== */
/* Konfigurasi                                                                 */
/* ========================================================================== */

const DEFAULT_API_BASE = '/api/bak';

/** Rate pengali amount (spec BAK memakai Rate = 1). */
const DEFAULT_RATE = 1;
/** Debounce auto-save (ms) sesuai requirement. */
const AUTOSAVE_DEBOUNCE_MS = 2000;
/** Durasi toast tampil (ms). */
const TOAST_TTL_MS = 4000;
/** Step 2: variance di luar +/-10% di-highlight merah. */
const SELLER_WARN_PCT = 10;
/** Step 3: <= 5% hijau, 5-15% kuning, > 15% merah. */
const FINANCE_GREEN_PCT = 5;
const FINANCE_YELLOW_PCT = 15;
/** Step 3: variance > 15% wajib melampirkan supporting doc. */
const DOC_REQUIRED_PCT = 15;

/* ========================================================================== */
/* Types                                                                       */
/* ========================================================================== */

export type BAKStatus = 'DRAFT' | 'SUBMITTED' | 'APPROVED' | 'LOCKED';

export const SELLER_VARIANCE_REASONS = [
  'Client reduction',
  'Market rate',
  'Contract revision',
  'HC optimization',
  'Other',
] as const;
/** Jenis billing rate card — menentukan satuan quantity pada analisa variance. */
export const BILLING_TYPES = ['Hours', 'Headcount', 'Interaction'] as const;
export type BillingType = (typeof BILLING_TYPES)[number];

/**
 * Kode alasan otomatis — dihitung sistem saat angka antar step berbeda
 * (mis. jumlah quantity berkurang). Ditampilkan di kolom terpisah
 * ("Variance Reason (Auto)") dan tidak disimpan sebagai alasan manual.
 */
export const AUTO_VARIANCE_REASONS = [
  'Quantity shortage',
  'Quantity excess',
  'Price variance',
  'Value variance',
] as const;
export type AutoVarianceReason = (typeof AUTO_VARIANCE_REASONS)[number];

/** Kode auto versi lama yang mungkin masih tersimpan di database. */
const LEGACY_AUTO_VARIANCE_REASONS = ['HC shortage', 'HC excess'];

export type SellerVarianceReason = (typeof SELLER_VARIANCE_REASONS)[number];

export const FINANCE_VARIANCE_REASONS = [
  'Deduction',
  'SLA miss',
  'Discount',
  'Reward',
  'Doc variance',
  'Other',
] as const;
export type FinanceVarianceReason = (typeof FINANCE_VARIANCE_REASONS)[number];

/** Baris tabel Max Potential Earnings. */
export interface MaxPotentialRow {
  id: string | number;
  role: string;
  category?: string;
  billing_type?: BillingType;
  /** Harga satuan manual. */
  price_per_unit: number;
  /** true bila harga sudah diubah manual (tidak ditimpa hitung WFM). */
  price_manual?: boolean | number;
  /** Rate (sum rate) hasil hitung WFM. */
  quantity: number;
  /** Hasil hitung WFM = rate × price/unit. */
  subtotal: number;
  /** Max Potential yang diisi manual (null bila belum diisi). */
  manual_amount?: number | null;
  /** Rate yang dipakai untuk Max Potential manual. */
  manual_rate?: number | null;
  /** Nilai acuan: manual bila diisi, kalau tidak hasil WFM. */
  base_amount?: number | null;
  /** true bila role ditambahkan manual (tidak ada di berkas WFM). */
  is_custom?: boolean | number;
}

export interface CategoryBreakdown {
  category: string;
  subtotal: number;
}

/** Payload penyimpanan Max Potential (subtotal dihitung server dari price × rate). */
export interface MaxPotentialSaveItem {
  /** null untuk role baru yang ditambahkan manual. */
  id: string | number | null;
  role?: string;
  billing_type: BillingType;
  price_per_unit: number | null;
  /** Max Potential manual; null = kosongkan (kembali ke hasil WFM). */
  manual_amount?: number | null;
  /** Max Potential Rate (pengali nominal manual). */
  manual_rate?: number | null;
  /** true = hapus role ini dari rate card. */
  delete?: boolean;
}

export interface MaxPotentialEarnings {
  items: MaxPotentialRow[];
  categories: CategoryBreakdown[];
  grand_total: number;
  /** Total hasil hitung WFM. */
  wfm_total?: number;
  /** Total Max Potential manual. */
  manual_total?: number;
}

/* ---------------------------- Absensi (Step 1A–1C) ----------------------- */

/** Satu orang yang hari kerjanya kurang dari hari kerja standar. */
export interface AttendanceShortfall {
  name: string;
  /** Hari masuk efektif orang ini. */
  days: number;
  /** Kekurangan hari terhadap hari kerja standar. */
  missing: number;
  /** true bila hari masuknya hasil koreksi manual. */
  manual: boolean;
}

/** Satu posisi hasil rekap absensi. */
export interface AttendancePosition {
  position: string;
  category: string;
  /** true bila posisi sudah punya harga (rate card atau harga periode ini). */
  known: boolean;
  price: number;
  /** Jumlah orang unik. */
  head: number;
  /** Sum rate (jumlah rate per orang) — pengganti quantity headcount. */
  quantity: number;
  /** Berapa orang yang dihitung prorata. */
  partial: number;
  h: number;
  sc: number;
  sick: number;
  ct: number;
  off: number;
  other: number;
  subtotal: number;
  /** Orang di posisi ini yang hari masuknya kurang dari standar. */
  shortfalls: AttendanceShortfall[];
  /** Total kekurangan hari pada posisi ini. */
  missing_days: number;
}

export interface AttendanceTotals {
  people: number;
  quantity: number;
  h: number;
  sc: number;
  sick: number;
  ct: number;
  off: number;
  subtotal: number;
  /** Total Counting Price hasil rekap per orang. */
  counted: number;
  /** Total kekurangan hari seluruh posisi. */
  missing_days: number;
  /** Jumlah orang yang dihitung (Remarks bukan Non Billable / Buffer). */
  billable: number;
  /** Jumlah orang yang tidak dihitung (Remarks Non Billable / Buffer). */
  excluded: number;
}

/** Satu orang pada rekap/review awal absensi. */
export interface AttendanceEmployee {
  position: string;
  /** NIK / Employee ID dari kolom A berkas (boleh kosong). */
  employee_id: string;
  name: string;
  /** Remarks dari kolom T berkas (boleh kosong). */
  remarks: string;
  /** false hanya bila Remarks mengandung Non Billable / Buffer. */
  billable: boolean;
  /** Hari masuk efektif (H/CT/SC/Sick) — Off tidak dihitung. */
  days: number;
  /** Hari masuk otomatis dari Excel (sebelum koreksi manual). */
  days_auto: number;
  /** true bila Working days sudah dikoreksi manual. */
  manual: boolean;
  /** Hari aktif kalender (pembilang rate) — 31 untuk sebulan penuh. */
  active_days: number;
  /** Hari aktif otomatis (dari tanggal resign di Remarks). */
  active_auto: number;
  /** true bila Hari aktif sudah dikoreksi manual. */
  active_manual: boolean;
  /** Tanggal resign hasil baca Remarks (YYYY-MM-DD), kosong bila tidak ada. */
  resign_date: string;
  /** Rate = hari aktif / jumlah hari bulan (full = 1). */
  rate: number;
  /** Harga satuan posisi. */
  price: number;
  /** Counting Price = rate x Unit Price (0 bila Remarks Non Billable / Buffer). */
  counted: number;
}

export interface AttendanceSummary {
  period: string;
  uploaded: boolean;
  file_name: string;
  total_rows: number;
  total_people: number;
  /** Hari kerja terbanyak di berkas (= sebulan penuh), dasar prorata. */
  standard_days: number;
  /** Jumlah hari dalam bulan periode (dasar rate kalender). */
  days_in_month: number;
  uploaded_at: string;
  positions: AttendancePosition[];
  /** Rekap per orang: satu baris per karyawan. */
  employees: AttendanceEmployee[];
  other_codes: string[];
  /** Posisi di berkas yang belum punya harga. */
  missing_in_rate: string[];
  /** Posisi di rate card yang tidak muncul di berkas periode ini. */
  absent_in_data: string[];
  totals: AttendanceTotals;
}

export interface AttendancePeriodInfo {
  period: string;
  file_name: string;
  total_rows: number;
  total_people: number;
  standard_days: number;
  uploaded_at: string;
}

export interface AttendancePayload {
  summary: AttendanceSummary;
  periods: AttendancePeriodInfo[];
}

export interface AttendanceSyncResult {
  items: unknown;
  categories: unknown;
  grand_total: number;
  summary: AttendanceSummary;
}

/** Baris tabel Step 2. */
export interface SellerInputRow {
  /** Identitas baris yang stabil untuk React & pencocokan (dari max_potential). */
  key: string;
  /** id baris seller_input di server; null bila belum pernah disimpan. */
  id: string | number | null;
  max_potential_id?: string | number;
  role: string;
  category?: string;
  /** Jenis billing dari rate card. */
  billing_type?: BillingType;
  price_per_unit?: number;
  /** Snapshot quantity otomatis dari Max Potential (untuk deteksi edit manual). */
  auto_quantity?: number | null;
  /** Snapshot price/unit otomatis dari Max Potential. */
  auto_price_per_unit?: number | null;
  /** Harga/unit yang diisi manual seller (default dari rate card). */
  sales_price_per_unit?: number;
  quantity?: number;
  /** Quantity rate card (acuan tetap untuk menghitung selisih HC). */
  max_potential_quantity?: number;
  max_potential: number;
  seller_input: number | null;
  variance_reason: SellerVarianceReason | '';
  notes: string;
  /** true bila actual_input baris ini sudah LOCKED — baris jadi read-only. */
  locked: boolean;
}

/** Baris tabel Step 3. */
export interface ActualInputRow {
  /** Identitas baris yang stabil untuk React & pencocokan. */
  key: string;
  /** id baris actual_input di server; null bila belum pernah disimpan. */
  id: string | number | null;
  seller_input_id?: string | number | null;
  /** Cadangan bila seller_input_id belum tersedia (mis. seller belum disimpan). */
  max_potential_id?: string | number | null;
  role: string;
  category?: string;
  /** Jenis billing dari rate card. */
  billing_type?: BillingType;
  /** Harga satuan dari rate card — acuan analisa rate & default price/unit. */
  price_per_unit?: number;
  /** Harga/unit accrual (acuan tetap untuk analisa rate). */
  seller_price_per_unit?: number;
  /** Harga/unit actual (bisa diedit) — Actual Input = Price/Unit × Quantity. */
  actual_price_per_unit?: number;
  /** Quantity actual input (bisa diedit; default dari rate card). */
  quantity?: number;
  /** Quantity rate card (acuan tetap, sync dengan Max Potential). */
  max_potential_quantity?: number;
  /** Quantity baris accrual (acuan tetap, sync dengan Accrual Input). */
  seller_quantity?: number;
  max_potential: number;
  seller_input: number | null;
  actual_input: number | null;
  variance_reason_finance: FinanceVarianceReason | '';
  notes: string;
  supporting_docs: string;
  status: BAKStatus;
}

export interface ValidationIssue {
  id: string;
  field: string;
  message: string;
}

export interface BAKStepperProps {
  /** Base URL API BAK. Default: `/api/bak`. */
  apiBaseUrl?: string;
  /** Step pembuka (1-4). Default: 1 (WFM Calculation). */
  initialStep?: 1 | 2 | 3 | 4;
  /** Dipanggil setelah semua row berhasil di-approve & lock. */
  onLocked?: (rows: ActualInputRow[]) => void;
  /**
   * Uploader opsional untuk supporting document.
   * Kembalikan URL / reference yang akan disimpan di `supporting_docs`.
   * Jika tidak diberikan, user hanya bisa mengisi reference manual.
   */
  uploadSupportingDoc?: (file: File) => Promise<string>;
  /** Hook error global untuk kebutuhan logging di host app. */
  onError?: (error: unknown) => void;
}

/* ========================================================================== */
/* Utilities                                                                   */
/* ========================================================================== */

/** Format angka ke mata uang IDR tanpa simbol: 1500000 -> "1.500.000". */
export function formatCurrency(value: number | null | undefined): string {
  if (value == null || !Number.isFinite(value)) return '';
  const negative = value < 0;
  const digits = Math.round(Math.abs(value)).toString();
  const grouped = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `${negative ? '-' : ''}${grouped}`;
}

/** Format quantity absensi: 60 -> "60", 60.48 -> "60,48". */
export function formatQuantity(value: number | null | undefined): string {
  if (value == null || !Number.isFinite(value)) return '0';
  const rounded = Math.round(value * 100) / 100;
  const whole = Math.trunc(rounded);
  const fraction = Math.round((rounded - whole) * 100);
  const grouped = Math.abs(whole).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  if (fraction === 0) return grouped;
  return `${grouped},${String(fraction).padStart(2, '0').replace(/0$/, '')}`;
}

/** Format rate absensi dengan 2 desimal: 1 -> "1,00", 0.92 -> "0,92". */
export function formatRate(value: number | null | undefined): string {
  if (value == null || !Number.isFinite(value)) return '0,00';
  const rounded = Math.round(value * 100) / 100;
  const whole = Math.trunc(rounded);
  const fraction = Math.round(Math.abs(rounded - whole) * 100);
  return `${whole},${String(fraction).padStart(2, '0')}`;
}

/** Format nilai signed untuk kolom variance: -1500000 -> "-1.500.000". */
export function formatSignedCurrency(value: number | null | undefined): string {
  if (value == null || !Number.isFinite(value)) return '-';
  const formatted = formatCurrency(Math.abs(value));
  if (value > 0) return `+${formatted}`;
  if (value < 0) return `-${formatted}`;
  return formatted;
}

/** Rupiah bertanda untuk narasi: -1500000 -> "-Rp 1.500.000". */
export function formatSignedRupiah(value: number | null | undefined): string {
  const sign = value != null && value < 0 ? '-' : '+';
  const abs = Math.abs(value ?? 0);
  if (abs < 0.005) return 'Rp 0';
  return `${sign}Rp ${formatCurrency(abs)}`;
}

/** Nilai variance dengan panah arah: +1.132 -> "↑ +1.132", -806 -> "↓ -806". */
export function formatVariance(value: number | null | undefined): string {
  if (value == null || !Number.isFinite(value)) return '-';
  const arrow = value > 0 ? '↑ ' : value < 0 ? '↓ ' : '';
  return `${arrow}${formatSignedCurrency(value)}`;
}

export function formatPercent(value: number | null | undefined): string {
  if (value == null || !Number.isFinite(value)) return '-';
  const sign = value > 0 ? '+' : value < 0 ? '-' : '';
  // Desimal koma mengikuti format angka lain di BAK (Rp 1.234,56).
  return `${sign}${Math.abs(value).toFixed(1).replace('.', ',')}%`;
}

/** Bersihkan teks user menjadi angka: "1.500.000" / "1500000" -> 1500000. */
export function parseCurrency(text: string): number | null {
  const digits = text.replace(/[^\d]/g, '');
  if (digits === '') return null;
  const parsed = Number(digits);
  return Number.isFinite(parsed) ? parsed : null;
}

/**
 * Parse quantity / sum rate: mendukung desimal koma.
 * "59,48" -> 59.48 | "1.234,5" -> 1234.5 | "60" -> 60
 */
export function parseQuantity(text: string): number | null {
  const cleaned = text.replace(/[^\d.,-]/g, '').trim();
  if (cleaned === '' || cleaned === '-') return null;
  const normalized =
    cleaned.lastIndexOf(',') > cleaned.lastIndexOf('.')
      ? cleaned.replace(/\./g, '').replace(',', '.')
      : cleaned.replace(/,/g, '');
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? parsed : null;
}

/** Konversi nilai apa pun dari API menjadi number. */
function toNumber(value: unknown, fallback = 0): number {
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value === 'string') {
    const cleaned = value.replace(/[^\d.,-]/g, '');
    if (cleaned === '') return fallback;
    // "1.500.000,50" -> 1500000.50 | "1,500,000.50" -> 1500000.50
    const normalized =
      cleaned.lastIndexOf(',') > cleaned.lastIndexOf('.')
        ? cleaned.replace(/\./g, '').replace(',', '.')
        : cleaned.replace(/,/g, '');
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : fallback;
  }
  return fallback;
}

const round2 = (n: number) => Math.round(n * 100) / 100;

/**
 * Teks jumlah untuk narasi variance.
 *
 * Headcount tidak mungkin pecahan, jadi dibulatkan **ke atas** (1,7 -> 2).
 * Billing type lain (Hours/Units) tetap memakai desimal.
 */
export function quantityText(type: BillingType | null | undefined, value: number): string {
  if (toBillingType(type) === 'Headcount') {
    return String(Math.max(0, Math.ceil(Math.abs(value) - 1e-9)));
  }
  return formatQuantity(Math.abs(value));
}

/**
 * Tone warna variance.
 *
 * Kekurangan (di bawah acuan) **selalu merah** — sekecil apa pun — karena
 * artinya tagihan/accrual lebih rendah dari yang seharusnya. Kelebihan baru
 * diwarnai kuning/merah bila melewati ambang.
 */
export function varianceTone(
  variance: number,
  variancePct: number,
  greenPct: number,
  yellowPct: number,
): 'ok' | 'warn' | 'danger' {
  if (variance < -0.005) return 'danger';
  const absPct = Math.abs(variancePct);
  if (absPct <= greenPct) return 'ok';
  return absPct <= yellowPct ? 'warn' : 'danger';
}

/** variance = compare - base, variancePct = variance / base * 100. */
export function computeVariance(
  base: number | null | undefined,
  compare: number | null | undefined,
): { variance: number; variancePct: number } {
  if (base == null || compare == null || !Number.isFinite(base) || !Number.isFinite(compare)) {
    return { variance: 0, variancePct: 0 };
  }
  const variance = compare - base;
  const variancePct = base === 0 ? 0 : (variance / base) * 100;
  return { variance, variancePct: round2(variancePct) };
}

export interface AutoReason {
  code: AutoVarianceReason;
  label: string;
}

/** Bentuk tunggal & jamak satuan quantity menurut jenis billing. */
const BILLING_UNIT_FORMS: Record<BillingType, [string, string]> = {
  Hours: ['hour', 'hours'],
  Headcount: ['headcount', 'headcount'],
  Interaction: ['interaction', 'interactions'],
};

function billingUnit(type: BillingType | null | undefined, count: number): string {
  const [singular, plural] = BILLING_UNIT_FORMS[type ?? 'Hours'] ?? ['unit', 'units'];
  return Math.abs(count) === 1 ? singular : plural;
}

/**
 * Analisa variance otomatis dari selisih angka antar step (bahasa Inggris yang wajar).
 *
 * Contoh (Headcount, 22.000.000 lebih rendah dari plan):
 * `{ code: 'Quantity shortage',
 *    label: '1 headcount below plan — Rp 22.000.000 (-50.0%)' }`.
 * `baseline` dipakai untuk menamai acuan pembanding ("plan" / "accrual").
 * Mengembalikan null bila amount (pada presisi tampilan) sama.
 */
export function buildAutoReason(
  billingType: BillingType | null | undefined,
  baseQuantity: number | null | undefined,
  quantity: number | null | undefined,
  baseAmount: number | null | undefined,
  amount: number | null | undefined,
  basePrice: number | null | undefined,
  price: number | null | undefined,
  baseline = 'plan',
): AutoReason | null {
  if (baseAmount == null || amount == null) return null;
  // Bandingkan pada presisi yang ditampilkan (rupiah bulat). Selisih di bawah
  // 1 rupiah membuat kedua angka tampil sama, jadi jangan dianggap variance.
  if (Math.round(baseAmount) === Math.round(amount)) return null;

  const diff = round2(amount - baseAmount);
  const totalPct = baseAmount === 0 ? 0 : (diff / baseAmount) * 100;

  const qtyDelta =
    baseQuantity != null && quantity != null ? round2(quantity - baseQuantity) : 0;
  const fromPrice = basePrice ?? (baseQuantity ? baseAmount / baseQuantity : null);
  const toPrice = price ?? (quantity ? amount / quantity : null);
  const pricePct =
    fromPrice != null && toPrice != null && fromPrice !== 0
      ? ((toPrice - fromPrice) / fromPrice) * 100
      : null;

  const clauses: string[] = [];
  let code: AutoVarianceReason = 'Value variance';

  // Selisih jumlah hanya disebut bila memang menjelaskan selisih nominal.
  // Contoh: -0,3 headcount x Rp 6.659.045 = -Rp 1.997.714 (cocok).
  // Kalau nominal turun lebih jauh (input manual), lebih tepat menyebut nominal.
  const unitPrice = fromPrice ?? toPrice ?? 0;
  const qtyImplied = qtyDelta * unitPrice;
  const qtyExplains =
    qtyDelta !== 0 &&
    Math.abs(qtyImplied - diff) <= Math.max(1, Math.abs(diff) * 0.01);

  if (qtyExplains) {
    // Headcount dibulatkan ke atas supaya tidak muncul "1,7 headcount".
    clauses.push(
      `${quantityText(billingType, qtyDelta)} ${billingUnit(billingType, qtyDelta)} ` +
        `${qtyDelta < 0 ? 'below' : 'above'} ${baseline}`,
    );
    code = qtyDelta < 0 ? 'Quantity shortage' : 'Quantity excess';
  }

  if (pricePct != null && Math.abs(pricePct) >= 0.05) {
    clauses.push(
      `Rate ${pricePct < 0 ? 'down' : 'up'} ${Math.abs(pricePct).toFixed(1).replace('.', ',')}%`,
    );
    if (code === 'Value variance') code = 'Price variance';
  }

  let sentence: string;
  if (clauses.length === 2) {
    sentence = `${clauses[0]} and ${clauses[1]}`;
  } else if (clauses.length === 1) {
    sentence = clauses[0];
  } else {
    sentence = `Total amount ${diff < 0 ? 'below' : 'above'} ${baseline}`;
  }
  sentence = sentence.charAt(0).toUpperCase() + sentence.slice(1);

  // Persentase sangat kecil ditampilkan "<0,1%" agar tidak terbaca "0,0%".
  const pctText =
    Math.abs(totalPct) >= 0.05 ? formatPercent(totalPct) : `${totalPct < 0 ? '-' : '+'}<0,1%`;
  // Nominal selalu bertanda: deficit = minus, surplus = plus.
  const amountText = formatSignedRupiah(diff);
  return {
    code,
    label: `${sentence} — ${amountText} (${pctText})`,
  };
}

function isAutoReason(reason: string): boolean {
  return (
    (AUTO_VARIANCE_REASONS as readonly string[]).includes(reason) ||
    LEGACY_AUTO_VARIANCE_REASONS.includes(reason)
  );
}

/** Satu baris narasi variance (format laporan deficit/surplus). */
export interface VarianceLine {
  role: string;
  direction: 'Decrease' | 'Increase';
  text: string;
  amount: number;
}

/**
 * Narasi variance WFM Calculation terhadap Max Potential (batas atas).
 *
 * Arah selisih: **WFM − Max Potential**. Jadi bila hasil hitung WFM lebih
 * kecil dari Max Potential (mis. 53,70 vs 54 karena ada yang tidak masuk
 * penuh), nilainya **minus / Decrease (deficit)**.
 *
 * Format mengikuti laporan finance:
 *   Deficit amount due to:
 *   - Decrease 0,3 headcount equal -Rp 1.997.714 for Agent position.
 *     From 54 headcount (Rp 359.588.430) to 53,7 headcount (Rp 357.590.717)
 *     due to WFM calculation below the Max Potential rate.
 */
export function buildVarianceLines(
  rows: Array<{
    role: string;
    billing_type?: string | null;
    quantity: number;
    manual_rate?: number | null;
    price_per_unit: number;
    subtotal: number;
    manual_amount?: number | null;
  }>,
): { heading: string; lines: VarianceLine[]; total: number } | null {
  const lines: VarianceLine[] = [];
  let total = 0;

  rows.forEach((row) => {
    const manual = row.manual_amount;
    if (manual == null) return;
    // Negatif = hasil WFM di bawah Max Potential (kekurangan).
    const diff = round2(row.subtotal - manual);
    if (Math.abs(diff) < 0.005) return;

    const unit = billingUnit(toBillingType(row.billing_type), diff);
    const wfmQty = round2(row.quantity);
    const capQty = row.manual_rate == null ? wfmQty : round2(row.manual_rate);
    const direction: 'Decrease' | 'Increase' = diff < 0 ? 'Decrease' : 'Increase';

    const qtyDelta = round2(wfmQty - capQty);
    const priceCap = capQty !== 0 ? round2(manual / capQty) : round2(row.price_per_unit);
    const priceWfm = wfmQty !== 0 ? round2(row.subtotal / wfmQty) : priceCap;
    // Abaikan beda harga yang hanya efek pembulatan rate (mis. 45,05 x harga).
    const priceChanged =
      priceCap > 0 && Math.abs(priceWfm - priceCap) / priceCap > 0.005;

    const causes: string[] = [];
    if (Math.abs(qtyDelta) >= 0.005) {
      causes.push(
        `WFM calculation ${direction === 'Decrease' ? 'below' : 'above'} the Max Potential rate`,
      );
    }
    if (priceChanged) {
      causes.push(
        `WFM price per unit ${direction === 'Decrease' ? 'below' : 'above'} Max Potential`,
      );
    }
    const causeText = causes.length ? ` due to ${causes.join(' and ')}` : '';

    const isHeadcount = toBillingType(row.billing_type) === 'Headcount';
    const fromText = quantityText(row.billing_type, capQty);
    const toText = quantityText(row.billing_type, wfmQty);
    const deltaText = quantityText(row.billing_type, Math.abs(qtyDelta) || wfmQty);
    const pct = manual !== 0 ? (diff / manual) * 100 : 0;

    // Headcount dibulatkan ke atas, jadi jangan tulis "from 54 to 54" —
    // cukup bandingkan nominalnya.
    const basis =
      fromText === toText
        ? `approved Rp ${formatCurrency(manual)} vs WFM Rp ${formatCurrency(row.subtotal)}`
        : `from ${fromText} ${unit} (Rp ${formatCurrency(manual)}) ` +
          `to ${toText} ${unit} (Rp ${formatCurrency(row.subtotal)})`;

    const text =
      `${row.role} position: ${direction} ${deltaText} ${unit}` +
      `${isHeadcount ? ' (rounded up)' : ''} equal ${formatSignedRupiah(diff)} ` +
      `(${formatPercent(pct)}) — ${basis}${causeText}.`;

    lines.push({ role: row.role, direction, text, amount: diff });
    total = round2(total + diff);
  });

  if (!lines.length) return null;

  // Urutkan deficit terbesar lebih dulu, sesuai gaya laporan.
  lines.sort((a, b) => a.amount - b.amount);

  return {
    heading: total < 0 ? 'Deficit amount due to:' : 'Surplus amount due to:',
    lines,
    total,
  };
}

/**
 * Alasan variance untuk posisi yang ada orangnya tidak masuk penuh.
 *
 * Dipakai saat Accrual Input sama dengan Max Potential (jadi tidak ada variance
 * nominal), tetapi sum rate posisi tetap di bawah jumlah orang karena ada yang
 * hari kerjanya kurang.
 */
export function shortfallReason(
  position: AttendancePosition,
  standardDays: number,
): AutoReason | null {
  if (!position.shortfalls?.length) return null;

  const label =
    `${position.shortfalls.length} of ${position.head} headcount below full working days — ` +
    `WFM sum rate ${formatQuantity(position.quantity)} of ${formatQuantity(position.head)} ` +
    `(${formatQuantity(position.missing_days)} working days short)`;

  return { code: 'Quantity shortage', label };
}

/**
 * Rincian orang yang hari kerjanya kurang dari hari kerja standar.
 *
 * Ditampilkan di bawah alasan variance otomatis pada Accrual Input, supaya
 * selisih sum rate langsung jelas siapa yang tidak masuk penuh dan berapa hari.
 */
function ShortfallNote({
  shortfalls,
  standardDays,
}: {
  shortfalls: AttendanceShortfall[];
  standardDays: number;
}) {
  if (!shortfalls.length) return null;

  return (
    <div className="bak-shortfall">
      <span className="bak-shortfall__t">
        {shortfalls.length} orang kurang hari (standar {standardDays} hari):
      </span>
      {shortfalls.map((item) => (
        <span key={`${item.name}-${item.days}`} className="bak-shortfall__i">
          <b>{item.name}</b> {formatQuantity(item.days)}/{standardDays} hari
          <span className="bak-shortfall__m">−{formatQuantity(item.missing)} hari</span>
          {item.manual ? <span className="bak-shortfall__tag">manual</span> : null}
        </span>
      ))}
    </div>
  );
}

/** Normalisasi nilai billing type dari API (fallback: Hours). */
function toBillingType(value: unknown): BillingType {
  const raw = typeof value === 'string' ? value.trim() : '';
  const match = (BILLING_TYPES as readonly string[]).find(
    (type) => type.toLowerCase() === raw.toLowerCase(),
  );
  return (match ?? 'Hours') as BillingType;
}

/**
 * Nilai alasan manual yang aman dipakai di dropdown: kode otomatis (data lama
 * dari versi sebelumnya) dianggap belum ada alasan manual.
 */
function toManualReason(value: unknown): string {
  const reason = typeof value === 'string' ? value : '';
  return isAutoReason(reason) ? '' : reason;
}

/** Amount = Price/Unit × Rate × Quantity (dibulatkan 2 desimal). */
export function computeAmount(
  pricePerUnit: number | null | undefined,
  quantity: number | null | undefined,
): number | null {
  if (
    pricePerUnit == null ||
    quantity == null ||
    !Number.isFinite(pricePerUnit) ||
    !Number.isFinite(quantity)
  ) {
    return null;
  }
  return round2(pricePerUnit * DEFAULT_RATE * quantity);
}

/** Ambil array item dari berbagai bentuk response. */
function extractItems(payload: unknown): any[] {
  if (Array.isArray(payload)) return payload;
  if (payload && typeof payload === 'object') {
    const p = payload as Record<string, unknown>;
    if (Array.isArray(p.items)) return p.items as any[];
    if (Array.isArray(p.rows)) return p.rows as any[];
    if (Array.isArray(p.data)) return p.data as any[];
  }
  return [];
}

function readStatus(payload: unknown, fallback: BAKStatus = 'DRAFT'): BAKStatus {
  if (payload && typeof payload === 'object') {
    const status = (payload as Record<string, unknown>).status;
    if (typeof status === 'string') {
      const upper = status.toUpperCase();
      if (['DRAFT', 'SUBMITTED', 'APPROVED', 'LOCKED'].includes(upper)) {
        return upper as BAKStatus;
      }
    }
  }
  return fallback;
}

function errorMessage(error: unknown): string {
  if (error instanceof Error && error.message) return error.message;
  return 'Terjadi kesalahan yang tidak diketahui.';
}

/* -------------------------------- Normalizer ------------------------------ */

function normalizeMaxPotential(payload: unknown): MaxPotentialEarnings {
  const source = extractItems(payload);
  const rawCategories = (() => {
    if (payload && typeof payload === 'object') {
      const c = (payload as Record<string, unknown>).categories;
      if (Array.isArray(c)) return c as any[];
    }
    return null;
  })();

  const items: MaxPotentialRow[] = source.map((item, index) => {
    const price = toNumber(item?.price_per_unit ?? item?.price ?? item?.rate, 0);
    const quantity = toNumber(item?.quantity ?? item?.qty ?? item?.unit, 0);
    const subtotal =
      item?.subtotal != null ? toNumber(item.subtotal) : round2(price * quantity);
    // manual_amount null berarti Max Potential manual belum diisi.
    const manualRaw = item?.manual_amount;
    const manual =
      manualRaw === null || manualRaw === undefined || manualRaw === ''
        ? null
        : toNumber(manualRaw);
    const base = item?.base_amount != null ? toNumber(item.base_amount) : (manual ?? subtotal);
    const manualRateRaw = item?.manual_rate;
    const manualRate =
      manualRateRaw === null || manualRateRaw === undefined || manualRateRaw === ''
        ? null
        : toNumber(manualRateRaw);
    return {
      id: item?.id ?? item?.role_id ?? `mp-${index}`,
      role: String(item?.role ?? item?.role_name ?? item?.name ?? '-'),
      category: item?.category ?? item?.category_name ?? undefined,
      billing_type: toBillingType(item?.billing_type ?? item?.billing),
      price_per_unit: price,
      price_manual: Number(item?.price_manual ?? 0) === 1,
      quantity,
      subtotal,
      manual_amount: manual,
      manual_rate: manualRate,
      base_amount: base,
      is_custom: Number(item?.is_custom ?? 0) === 1,
    };
  });

  const grouped = new Map<string, number>();
  items.forEach((item) => {
    const key = item.category ?? 'Uncategorized';
    grouped.set(key, round2((grouped.get(key) ?? 0) + (item.base_amount ?? item.subtotal)));
  });

  const categories: CategoryBreakdown[] = rawCategories
    ? rawCategories.map((c) => ({
        category: String(c?.category ?? c?.name ?? 'Uncategorized'),
        subtotal: toNumber(c?.subtotal ?? c?.total, 0),
      }))
    : Array.from(grouped.entries()).map(([category, subtotal]) => ({ category, subtotal }));

  const computedTotal = round2(
    items.reduce((acc, item) => acc + (item.base_amount ?? item.subtotal), 0),
  );
  const wfmTotal = round2(items.reduce((acc, item) => acc + item.subtotal, 0));
  const manualTotal = round2(items.reduce((acc, item) => acc + (item.manual_amount ?? 0), 0));
  const rawTotal =
    payload && typeof payload === 'object'
      ? (payload as Record<string, unknown>).grand_total
      : undefined;

  return {
    items,
    categories,
    grand_total: rawTotal != null ? toNumber(rawTotal) : computedTotal,
    wfm_total: wfmTotal,
    manual_total: manualTotal,
  };
}

function buildSellerRows(maxPotential: MaxPotentialRow[], saved: any[]): SellerInputRow[] {
  const byKey = new Map<string, any>();
  saved.forEach((row) => {
    if (row?.max_potential_id != null) byKey.set(String(row.max_potential_id), row);
    if (row?.role != null) byKey.set(String(row.role), row);
  });

  return maxPotential.map((item) => {
    const draft = byKey.get(String(item.id)) ?? byKey.get(String(item.role));
    return {
      // key berasal dari max_potential (selalu unik), sedangkan id adalah id
      // baris seller_input di server — jangan pernah mengisi id dengan id
      // max_potential, karena dua baris bisa ber-id sama dan saling menimpa.
      key: `mp-${item.id}`,
      id: draft?.id ?? null,
      max_potential_id: item.id,
      role: item.role,
      category: item.category,
      billing_type: item.billing_type,
      price_per_unit: item.price_per_unit,
      auto_quantity: draft?.auto_quantity != null ? toNumber(draft.auto_quantity) : null,
      auto_price_per_unit:
        draft?.auto_price_per_unit != null ? toNumber(draft.auto_price_per_unit) : null,
      // Harga/unit tersimpan menang atas rate card supaya hasil edit user tidak hilang.
      sales_price_per_unit:
        draft?.price_per_unit != null ? toNumber(draft.price_per_unit) : item.price_per_unit,
      // Quantity tersimpan menang atas rate card supaya hasil edit user tidak hilang.
      quantity: draft?.quantity != null ? toNumber(draft.quantity) : item.quantity,
      max_potential_quantity: item.manual_rate ?? item.quantity,
      // Max Potential selalu ikut rate card terkini ("Sync with Max Potential"),
      // supaya hasil edit di Step 1 langsung terlihat di Accrual Input walau
      // baris accrual sudah pernah tersimpan (snapshot lama tidak dipakai).
      // base_amount = Max Potential manual bila diisi, kalau tidak hasil WFM.
      max_potential: item.base_amount ?? item.subtotal,
      // Accrual Input otomatis mengikuti nilai Max Potential bila belum diisi.
      seller_input:
        draft?.seller_input == null
          ? item.base_amount ?? item.subtotal
          : toNumber(draft.seller_input),
      variance_reason: toManualReason(draft?.variance_reason) as SellerVarianceReason | '',
      notes: draft?.notes ?? '',
      locked: Number(draft?.is_locked ?? 0) === 1,
    };
  });
}

function buildActualRows(sellerRows: SellerInputRow[], saved: any[]): ActualInputRow[] {
  const byKey = new Map<string, any>();
  saved.forEach((row) => {
    if (row?.seller_input_id != null) byKey.set(String(row.seller_input_id), row);
    if (row?.role != null) byKey.set(String(row.role), row);
  });

  return (
    sellerRows
      // Baris accrual selalu punya acuan; baris tanpa id tetap bisa
      // diverifikasi karena server mencocokkan lewat max_potential_id.
      .filter((seller) => seller.id != null || seller.max_potential_id != null)
      .map((seller) => {
        const draft = byKey.get(String(seller.id)) ?? byKey.get(String(seller.role));
        return {
          key: seller.key,
          id: draft?.id ?? null,
          seller_input_id: seller.id ?? null,
          max_potential_id: seller.max_potential_id ?? null,
          role: seller.role,
          category: seller.category,
          billing_type: seller.billing_type,
          price_per_unit: seller.price_per_unit,
          seller_price_per_unit: seller.sales_price_per_unit ?? seller.price_per_unit,
          // Price/unit tersimpan menang; default dari harga accrual.
          actual_price_per_unit:
            draft?.price_per_unit != null
              ? toNumber(draft.price_per_unit)
              : (seller.sales_price_per_unit ?? seller.price_per_unit),
          quantity: draft?.quantity != null ? toNumber(draft.quantity) : seller.quantity,
          max_potential_quantity: seller.max_potential_quantity,
          seller_quantity: seller.quantity,
          max_potential: seller.max_potential,
          seller_input: seller.seller_input,
          // Actual Input otomatis mengikuti Accrual Input bila belum diisi.
          actual_input:
            draft?.actual_input == null ? seller.seller_input : toNumber(draft.actual_input),
          variance_reason_finance: toManualReason(draft?.variance_reason_finance) as FinanceVarianceReason | '',
          notes: draft?.notes ?? '',
          supporting_docs: draft?.supporting_docs ?? '',
          status: readStatus(draft, 'DRAFT'),
        };
      })
  );
}

/* ---------------------------- Auto variance reason -------------------------- */

/** Variance Accrual Input terhadap Max Potential Earnings (Step 2). */
export function autoReasonForSeller(row: SellerInputRow): AutoReason | null {
  return buildAutoReason(
    row.billing_type,
    row.max_potential_quantity,
    row.quantity,
    row.max_potential,
    row.seller_input,
    row.price_per_unit,
    row.sales_price_per_unit,
    'Max Potential',
  );
}

/** Variance Actual Input terhadap Accrual Input (Step 3). */
export function autoReasonForActual(row: ActualInputRow): AutoReason | null {
  return buildAutoReason(
    row.billing_type,
    row.seller_quantity,
    row.quantity,
    row.seller_input,
    row.actual_input,
    row.seller_price_per_unit,
    row.actual_price_per_unit,
    'accrual',
  );
}

/** Variance Actual Input terhadap Max Potential Earnings (Step 3). */
export function autoReasonActualVsMax(row: ActualInputRow): AutoReason | null {
  return buildAutoReason(
    row.billing_type,
    row.max_potential_quantity,
    row.quantity,
    row.max_potential,
    row.actual_input,
    row.price_per_unit,
    row.actual_price_per_unit,
    'Max Potential',
  );
}

/* ------------------------------- Serializer -------------------------------- */

function serializeSellerRow(row: SellerInputRow) {
  const { variance, variancePct } = computeVariance(row.max_potential, row.seller_input);
  return {
    id: row.id ?? null,
    max_potential_id: row.max_potential_id ?? null,
    role: row.role,
    max_potential: row.max_potential,
    quantity: row.quantity ?? null,
    price_per_unit: row.sales_price_per_unit ?? null,
    seller_input: row.seller_input,
    variance,
    variance_pct: variancePct,
    variance_reason: row.variance_reason || null,
    notes: row.notes || null,
  };
}

function serializeActualRow(row: ActualInputRow, status?: BAKStatus) {
  const { variance, variancePct } = computeVariance(row.seller_input, row.actual_input);
  return {
    id: row.id ?? null,
    seller_input_id: row.seller_input_id ?? null,
    max_potential_id: row.max_potential_id ?? null,
    role: row.role,
    max_potential: row.max_potential,
    seller_input: row.seller_input,
    quantity: row.quantity ?? null,
    price_per_unit: row.actual_price_per_unit ?? null,
    actual_input: row.actual_input,
    variance,
    variance_pct: variancePct,
    variance_reason_finance: row.variance_reason_finance || null,
    notes: row.notes || null,
    supporting_docs: row.supporting_docs || null,
    status: status ?? row.status,
  };
}

/* -------------------------------- Validation ------------------------------- */

const STATUS_RANK: Record<BAKStatus, number> = {
  DRAFT: 0,
  SUBMITTED: 1,
  APPROVED: 2,
  LOCKED: 3,
};

/**
 * Status akhir saat menyimpan — tidak pernah menurunkan status baris.
 * "Save Draft" pada baris yang sudah SUBMITTED tidak boleh mengembalikannya
 * ke DRAFT.
 */
function resolveActualStatus(current: BAKStatus, target: BAKStatus): BAKStatus {
  return STATUS_RANK[target] > STATUS_RANK[current] ? target : current;
}

function validateSellerRows(rows: SellerInputRow[]): ValidationIssue[] {
  const issues: ValidationIssue[] = [];
  rows.forEach((row) => {
    // Baris yang sudah LOCKED read-only dan tidak perlu diisi lagi.
    if (row.locked) return;
    const id = row.key;
    if (row.seller_input == null) {
      issues.push({ id, field: 'seller_input', message: 'Accrual input wajib diisi.' });
    } else if (row.seller_input < 0) {
      issues.push({ id, field: 'seller_input', message: 'Nilai tidak boleh negatif.' });
    }
    if (row.quantity == null) {
      issues.push({ id, field: 'quantity', message: 'Quantity wajib diisi.' });
    } else if (row.quantity < 0) {
      issues.push({ id, field: 'quantity', message: 'Nilai tidak boleh negatif.' });
    }
    if (row.sales_price_per_unit == null) {
      issues.push({ id, field: 'price_per_unit', message: 'Price/Unit wajib diisi.' });
    } else if (row.sales_price_per_unit < 0) {
      issues.push({ id, field: 'price_per_unit', message: 'Nilai tidak boleh negatif.' });
    }
    const { variancePct } = computeVariance(row.max_potential, row.seller_input);
    // Alasan otomatis (kolom terpisah) sudah menjelaskan selisih HC/nilai,
    // jadi alasan manual hanya diwajibkan bila tidak ada penjelasan otomatis.
    if (Math.abs(variancePct) > SELLER_WARN_PCT && !row.variance_reason && !autoReasonForSeller(row)) {
      issues.push({
        id,
        field: 'variance_reason',
        message: `Variance ${formatPercent(variancePct)} di luar ±${SELLER_WARN_PCT}% — alasan wajib dipilih.`,
      });
    }
  });
  return issues;
}

function validateActualRows(rows: ActualInputRow[], requireAll: boolean): ValidationIssue[] {
  const issues: ValidationIssue[] = [];
  rows.forEach((row) => {
    const id = row.key;
    const { variancePct } = computeVariance(row.seller_input, row.actual_input);
    const absPct = Math.abs(variancePct);

    if (row.actual_input == null && requireAll) {
      issues.push({ id, field: 'actual_input', message: 'Actual input wajib diisi.' });
    }
    if (row.actual_input != null && row.actual_input < 0) {
      issues.push({ id, field: 'actual_input', message: 'Nilai tidak boleh negatif.' });
    }
    if (row.quantity == null) {
      issues.push({ id, field: 'quantity', message: 'Quantity wajib diisi.' });
    } else if (row.quantity < 0) {
      issues.push({ id, field: 'quantity', message: 'Nilai tidak boleh negatif.' });
    }
    if (row.actual_price_per_unit == null) {
      issues.push({ id, field: 'price_per_unit', message: 'Price/Unit wajib diisi.' });
    } else if (row.actual_price_per_unit < 0) {
      issues.push({ id, field: 'price_per_unit', message: 'Nilai tidak boleh negatif.' });
    }
    if (
      row.actual_input != null &&
      absPct > FINANCE_GREEN_PCT &&
      !row.variance_reason_finance &&
      !autoReasonForActual(row)
    ) {
      issues.push({
        id,
        field: 'variance_reason_finance',
        message: `Variance ${formatPercent(variancePct)} — alasan finance wajib dipilih.`,
      });
    }
    if (row.actual_input != null && absPct > DOC_REQUIRED_PCT && !row.supporting_docs.trim()) {
      issues.push({
        id,
        field: 'supporting_docs',
        message: `Variance > ${DOC_REQUIRED_PCT}% — dokumen pendukung wajib diisi.`,
      });
    }
  });
  return issues;
}

/* ========================================================================== */
/* API layer (fetch, tanpa dependency eksternal)                               */
/* ========================================================================== */

export class BAKApiError extends Error {
  status: number;
  payload: unknown;

  constructor(message: string, status: number, payload: unknown) {
    super(message);
    this.name = 'BAKApiError';
    this.status = status;
    this.payload = payload;
  }
}

/** true bila respons berupa halaman login (sesi habis, bukan endpoint salah). */
export function isLoginPage(text: string): boolean {
  if (!text) return false;
  const head = text.slice(0, 4000);
  if (!/<html|<!doctype/i.test(head)) return false;
  return /page=auth&(?:amp;)?action=login/i.test(head) || /name=["']?password/i.test(head);
}

/** true bila error berasal dari sesi yang sudah habis. */
export function isSessionExpiredError(error: unknown): boolean {
  return error instanceof BAKApiError && error.status === 401;
}

export interface BAKApi {
  getMaxPotentialEarnings(): Promise<unknown>;
  saveMaxPotentialEarnings(rows: MaxPotentialSaveItem[]): Promise<unknown>;
  getSellerInput(): Promise<unknown>;
  saveSellerInput(rows: ReturnType<typeof serializeSellerRow>[]): Promise<unknown>;
  getActualInput(): Promise<unknown>;
  saveActualInput(rows: ReturnType<typeof serializeActualRow>[]): Promise<unknown>;
  approveActualInput(id: string | number): Promise<unknown>;
  /** Absensi BAK (Step 1A–1C). */
  getAttendance(period: string): Promise<AttendancePayload>;
  uploadAttendanceFile(file: File, period: string): Promise<AttendancePayload>;
  uploadAttendanceText(text: string, period: string): Promise<AttendancePayload>;
  saveAttendancePrices(period: string, prices: Record<string, number>): Promise<{ summary: AttendanceSummary }>;
  saveAttendanceEmployeeDays(
    period: string,
    entries: Array<{ position: string; name: string; days?: number | null; active?: number | null }>,
  ): Promise<{ summary: AttendanceSummary }>;
  addAttendancePositions(period: string, positions: string[]): Promise<{ summary: AttendanceSummary }>;
  syncAttendance(period: string): Promise<AttendanceSyncResult>;
  removeAttendance(period: string): Promise<AttendancePayload>;
}

export function createBakApi(baseUrl: string = DEFAULT_API_BASE): BAKApi {
  /** Baca respons JSON & lempar error yang informatif. */
  const parse = async <T,>(response: Response, path: string): Promise<T> => {
    const text = await response.text();
    let payload: any = null;
    if (text) {
      try {
        payload = JSON.parse(text);
      } catch {
        payload = text;
      }
    }

    if (!response.ok) {
      const message =
        (payload && typeof payload === 'object' && (payload.message || payload.error)) ||
        (response.status === 401
          ? 'Sesi login Anda sudah berakhir. Muat ulang halaman (F5) lalu login kembali.'
          : `Request gagal (HTTP ${response.status}).`);
      throw new BAKApiError(String(message), response.status, payload);
    }

    // Endpoint yang belum ada bisa di-rewrite oleh web server menjadi halaman
    // HTML dengan status 200. Tanpa pengecekan ini, HTML tersebut akan dianggap
    // "data kosong" dan menyembunyikan kesalahan konfigurasi.
    if (text && typeof payload === 'string') {
      // Halaman login berarti sesi habis — bukan endpoint yang salah.
      if (isLoginPage(payload)) {
        throw new BAKApiError(
          'Sesi login Anda sudah berakhir. Muat ulang halaman (F5) lalu login kembali.',
          401,
          null,
        );
      }
      throw new BAKApiError(
        `Endpoint ${path} tidak mengembalikan JSON (kemungkinan belum tersedia atau URL salah).`,
        response.status,
        payload,
      );
    }

    return ((payload && typeof payload === 'object' && 'data' in payload ? payload.data : payload) ?? null) as T;
  };

  const request = async <T,>(path: string, init?: RequestInit): Promise<T> => {
    const { headers, ...rest } = init ?? {};
    let response: Response;
    try {
      response = await fetch(`${baseUrl}${path}`, {
        ...rest,
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...((headers as Record<string, string>) ?? {}),
        },
      });
    } catch (networkError) {
      throw new BAKApiError('Tidak dapat menghubungi server. Periksa koneksi Anda.', 0, networkError);
    }

    return parse<T>(response, path);
  };

  /** Unggahan berkas: Content-Type diserahkan ke browser (multipart). */
  const requestForm = async <T,>(path: string, form: FormData): Promise<T> => {
    let response: Response;
    try {
      response = await fetch(`${baseUrl}${path}`, {
        method: 'POST',
        body: form,
        headers: { Accept: 'application/json' },
      });
    } catch (networkError) {
      throw new BAKApiError('Tidak dapat menghubungi server. Periksa koneksi Anda.', 0, networkError);
    }

    return parse<T>(response, path);
  };

  return {
    getMaxPotentialEarnings: () => request<unknown>('/max-potential-earnings'),
    saveMaxPotentialEarnings: (rows) =>
      request<unknown>('/max-potential-earnings', {
        method: 'POST',
        body: JSON.stringify({ items: rows }),
      }),
    getSellerInput: () => request<unknown>('/seller-input'),
    saveSellerInput: (rows) =>
      request<unknown>('/seller-input', { method: 'POST', body: JSON.stringify({ items: rows }) }),
    resetSellerInput: () => request<unknown>('/seller-input/reset', { method: 'POST' }),
    getActualInput: () => request<unknown>('/actual-input'),
    saveActualInput: (rows) =>
      request<unknown>('/actual-input', { method: 'POST', body: JSON.stringify({ items: rows }) }),
    resetActualInput: () => request<unknown>('/actual-input/reset', { method: 'POST' }),
    approveActualInput: (id) =>
      request<unknown>(`/actual-input/${encodeURIComponent(String(id))}/approve`, { method: 'POST' }),

    getAttendance: (period) =>
      request<AttendancePayload>(`/attendance?period=${encodeURIComponent(period)}`),
    uploadAttendanceFile: (file, period) => {
      const form = new FormData();
      form.append('file', file, file.name);
      form.append('period', period);
      return requestForm<AttendancePayload>('/attendance', form);
    },
    uploadAttendanceText: (text, period) =>
      request<AttendancePayload>('/attendance', {
        method: 'POST',
        body: JSON.stringify({ text, filename: 'paste-absensi.txt', period }),
      }),
    saveAttendancePrices: (period, prices) =>
      request<{ summary: AttendanceSummary }>('/attendance/prices', {
        method: 'POST',
        body: JSON.stringify({ period, prices }),
      }),
    addAttendancePositions: (period, positions) =>
      request<{ summary: AttendanceSummary }>('/attendance/positions', {
        method: 'POST',
        body: JSON.stringify({ period, positions }),
      }),
    saveAttendanceEmployeeDays: (period, entries) =>
      request<{ summary: AttendanceSummary }>('/attendance/employees', {
        method: 'POST',
        body: JSON.stringify({ period, entries }),
      }),
    syncAttendance: (period) =>
      request<AttendanceSyncResult>('/attendance/sync', {
        method: 'POST',
        body: JSON.stringify({ period }),
      }),
    removeAttendance: (period) =>
      request<AttendancePayload>('/attendance/remove', {
        method: 'POST',
        body: JSON.stringify({ period }),
      }),
  };
}

/* ========================================================================== */
/* Hooks                                                                       */
/* ========================================================================== */

interface ToastMessage {
  id: number;
  type: 'success' | 'error' | 'info';
  message: string;
}

function useToasts() {
  const [toasts, setToasts] = useState<ToastMessage[]>([]);
  const sequence = useRef(0);
  const timers = useRef<Set<ReturnType<typeof setTimeout>>>(new Set());

  useEffect(
    () => () => {
      timers.current.forEach(clearTimeout);
      timers.current.clear();
    },
    [],
  );

  const dismiss = useCallback((id: number) => {
    setToasts((prev) => prev.filter((toast) => toast.id !== id));
  }, []);

  const pushToast = useCallback(
    (type: ToastMessage['type'], message: string) => {
      sequence.current += 1;
      const id = sequence.current;
      setToasts((prev) => [...prev, { id, type, message }]);
      const timer = setTimeout(() => {
        timers.current.delete(timer);
        dismiss(id);
      }, TOAST_TTL_MS);
      timers.current.add(timer);
    },
    [dismiss],
  );

  return { toasts, pushToast, dismiss };
}

/* ========================================================================== */
/* UI primitives                                                               */
/* ========================================================================== */

function Spinner({ size = 16, label }: { size?: number; label?: string }) {
  return (
    <span className="bak-spinner-wrap" role="status" aria-live="polite">
      <span className="bak-spinner" style={{ width: size, height: size }} aria-hidden="true" />
      {label ? <span className="bak-spinner__label">{label}</span> : null}
      <span className="bak-sr-only">{label ?? 'Memuat'}</span>
    </span>
  );
}

const STATUS_LABELS: Record<BAKStatus, string> = {
  DRAFT: 'DRAFT',
  SUBMITTED: 'SUBMITTED',
  APPROVED: 'APPROVED',
  LOCKED: 'LOCKED',
};

function StatusBadge({ status }: { status: BAKStatus }) {
  return (
    <span className={`bak-badge bak-badge--${status.toLowerCase()}`}>{STATUS_LABELS[status]}</span>
  );
}

interface CurrencyInputProps {
  value: number | null;
  onChange: (value: number | null) => void;
  disabled?: boolean;
  invalid?: boolean;
  id?: string;
  ariaLabel: string;
  placeholder?: string;
}

function CurrencyInput({
  value,
  onChange,
  disabled,
  invalid,
  id,
  ariaLabel,
  placeholder = '0',
}: CurrencyInputProps) {
  const [focused, setFocused] = useState(false);
  const [draft, setDraft] = useState('');

  const display = focused ? draft : value == null ? '' : formatCurrency(value);

  const handleFocus = () => {
    setFocused(true);
    setDraft(value == null ? '' : formatCurrency(value));
  };

  const handleBlur = () => {
    setFocused(false);
    setDraft('');
  };

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    const parsed = parseCurrency(event.target.value);
    setDraft(parsed == null ? '' : formatCurrency(parsed));
    onChange(parsed);
  };

  return (
    <div className="bak-currency">
      <span className="bak-currency__prefix" aria-hidden="true">
        Rp
      </span>
      <input
        id={id}
        type="text"
        inputMode="numeric"
        autoComplete="off"
        className={`bak-input bak-input--currency${invalid ? ' bak-input--invalid' : ''}`}
        value={display}
        onChange={handleChange}
        onFocus={handleFocus}
        onBlur={handleBlur}
        disabled={disabled}
        placeholder={placeholder}
        aria-label={ariaLabel}
        aria-invalid={invalid || undefined}
      />
    </div>
  );
}

/**
 * Tampilan read-only yang meniru field `CurrencyInput` (prefix "Rp" + kotak),
 * dipakai untuk kolom nilai acuan seperti "Max Potential".
 */
function ReadOnlyCurrency({ value, ariaLabel }: { value: number | null; ariaLabel: string }) {
  return (
    <div className="bak-currency">
      <span className="bak-currency__prefix" aria-hidden="true">
        Rp
      </span>
      <input
        type="text"
        className="bak-input bak-input--currency"
        value={value == null ? '' : formatCurrency(value)}
        readOnly
        disabled
        tabIndex={-1}
        aria-label={ariaLabel}
      />
    </div>
  );
}

interface QuantityInputProps {
  value: number | null;
  onChange: (value: number | null) => void;
  disabled?: boolean;
  invalid?: boolean;
  id?: string;
  ariaLabel: string;
  placeholder?: string;
}

/** Input quantity / sum rate — mendukung desimal (mis. 59,48). */
function QuantityInput({
  value,
  onChange,
  disabled,
  invalid,
  id,
  ariaLabel,
  placeholder = '0',
}: QuantityInputProps) {
  const [focused, setFocused] = useState(false);
  const [draft, setDraft] = useState('');

  // Sum rate bisa desimal (rate 0,48), jadi jangan dibulatkan seperti mata uang.
  const display = focused ? draft : value == null ? '' : formatQuantity(value);

  const handleFocus = () => {
    setFocused(true);
    setDraft(value == null ? '' : formatQuantity(value));
  };

  const handleBlur = () => {
    setFocused(false);
    setDraft('');
  };

  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    const raw = event.target.value;
    // Biarkan pengguna mengetik koma / titik desimal tanpa langsung dirapikan.
    setDraft(raw);
    onChange(parseQuantity(raw));
  };

  return (
    <input
      id={id}
      type="text"
      inputMode="decimal"
      autoComplete="off"
      className={`bak-input bak-quantity${invalid ? ' bak-input--invalid' : ''}`}
      value={display}
      onChange={handleChange}
      onFocus={handleFocus}
      onBlur={handleBlur}
      disabled={disabled}
      placeholder={placeholder}
      aria-label={ariaLabel}
      aria-invalid={invalid || undefined}
    />
  );
}

/** Tombol info bundar "i" dengan tooltip yang muncul saat hover/fokus. */
function InfoTip({ text, label }: { text: string; label: string }) {
  return (
    <span className="bak-info">
      <button type="button" className="bak-info__btn" aria-label={label} title={text}>
        <span aria-hidden="true">i</span>
      </button>
      <span className="bak-info__tip" role="tooltip">
        {text}
      </span>
    </span>
  );
}

interface NotesInputProps {
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  ariaLabel: string;
  placeholder?: string;
}

/**
 * Catatan opsional: ringkas secara default, melebar seperti popup saat
 * difokus (klik) dan kembali ringkas saat blur. Placeholder tetap terlihat.
 */
function NotesInput({
  value,
  onChange,
  disabled,
  ariaLabel,
  placeholder = 'Catatan Opsional',
}: NotesInputProps) {
  const [expanded, setExpanded] = useState(false);
  return (
    <div className="bak-notes">
      <input
        type="text"
        className={`bak-input bak-notes__input${expanded ? ' bak-notes__input--expanded' : ''}`}
        value={value}
        placeholder={placeholder}
        disabled={disabled}
        aria-label={ariaLabel}
        onFocus={() => setExpanded(true)}
        onClick={() => setExpanded(true)}
        onBlur={() => setExpanded(false)}
        onChange={(event) => onChange(event.target.value)}
      />
    </div>
  );
}

interface DialogAction {
  label: string;
  variant?: 'primary' | 'ghost' | 'danger' | 'success';
  onClick: () => void;
}

function ConfirmDialog({
  open,
  title,
  message,
  actions,
  onClose,
}: {
  open: boolean;
  title: string;
  message: ReactNode;
  actions: DialogAction[];
  onClose: () => void;
}) {
  useEffect(() => {
    if (!open) return undefined;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="bak-modal" role="dialog" aria-modal="true" aria-label={title}>
      <div className="bak-modal__backdrop" onClick={onClose} />
      <div className="bak-modal__panel">
        <h3 className="bak-modal__title">{title}</h3>
        <div className="bak-modal__body">{message}</div>
        <div className="bak-modal__actions">
          {actions.map((action) => (
            <button
              key={action.label}
              type="button"
              className={`bak-btn bak-btn--${action.variant ?? 'ghost'}`}
              onClick={action.onClick}
            >
              {action.label}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

function ToastStack({
  toasts,
  onDismiss,
}: {
  toasts: ToastMessage[];
  onDismiss: (id: number) => void;
}) {
  if (toasts.length === 0) return null;
  return (
    <div className="bak-toasts" role="status" aria-live="polite">
      {toasts.map((toast) => (
        <div key={toast.id} className={`bak-toast bak-toast--${toast.type}`}>
          <span className="bak-toast__icon" aria-hidden="true">
            {toast.type === 'success' ? '✓' : toast.type === 'error' ? '!' : 'i'}
          </span>
          <span className="bak-toast__message">{toast.message}</span>
          <button
            type="button"
            className="bak-toast__close"
            onClick={() => onDismiss(toast.id)}
            aria-label="Tutup notifikasi"
          >
            ×
          </button>
        </div>
      ))}
    </div>
  );
}

/** Panel pembungkus dengan overlay loading + error banner. */
function StepPanel({
  visible,
  loading,
  loadingLabel,
  error,
  onRetry,
  children,
}: {
  visible: boolean;
  loading: boolean;
  loadingLabel: string;
  error: string | null;
  onRetry: () => void;
  children: ReactNode;
}) {
  if (!visible) return null;
  return (
    <section className="bak-panel" aria-busy={loading}>
      {error ? (
        <div className="bak-banner bak-banner--error" role="alert">
          <span>{error}</span>
          <button type="button" className="bak-btn bak-btn--ghost bak-btn--sm" onClick={onRetry}>
            Coba lagi
          </button>
        </div>
      ) : null}
      {loading ? (
        <div className="bak-overlay">
          <Spinner size={26} label={loadingLabel} />
        </div>
      ) : null}
      {children}
    </section>
  );
}

/* ========================================================================== */
/* Styles (scoped, no external CSS file)                                       */
/* ========================================================================== */

const BAK_CSS = `
.bak-root{--bak-primary:#2563eb;--bak-primary-dark:#1d4ed8;--bak-text:#0f172a;--bak-muted:#64748b;--bak-border:#e2e8f0;--bak-bg:#f8fafc;
--bak-green:#15803d;--bak-green-bg:#dcfce7;--bak-yellow:#a16207;--bak-yellow-bg:#fef9c3;--bak-red:#b91c1c;--bak-red-bg:#fee2e2;
font-family:system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;font-size:14px;line-height:1.5;color:var(--bak-text);
background:var(--bak-bg);padding:16px;border-radius:14px}
.bak-root *,.bak-root *::before,.bak-root *::after{box-sizing:border-box}
.bak-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.bak-h1{font-size:18px;font-weight:700;margin:0 0 2px}
.bak-h2{font-size:15px;font-weight:700;margin:0}
.bak-h2-row{display:flex;align-items:center;gap:8px}
.bak-info{position:relative;display:inline-flex;align-items:center}
.bak-info__btn{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;padding:0;border-radius:50%;
border:1px solid var(--bak-primary);background:#fff;color:var(--bak-primary);font:inherit;font-size:11.5px;font-weight:700;
font-style:italic;line-height:1;cursor:pointer}
.bak-info__btn:hover,.bak-info__btn:focus{background:var(--bak-primary);color:#fff;outline:none}
.bak-info__tip{position:absolute;left:0;top:calc(100% + 6px);z-index:20;width:max-content;max-width:240px;padding:6px 9px;border-radius:8px;
background:#0f172a;color:#fff;font-size:12px;font-weight:500;line-height:1.4;box-shadow:0 8px 20px rgba(15,23,42,.24);
opacity:0;visibility:hidden;transition:opacity .12s ease}
.bak-info:hover .bak-info__tip,.bak-info:focus-within .bak-info__tip{opacity:1;visibility:visible}
.bak-muted{color:var(--bak-muted);margin:0;font-size:12.5px}
.bak-num{text-align:center;font-variant-numeric:tabular-nums;white-space:nowrap}

/* Header + stepper */
.bak-header{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:16px}
.bak-stepper{position:relative;display:flex;gap:8px;margin-bottom:18px}
.bak-step{flex:1 1 0;display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--bak-border);border-radius:10px;
background:#fff;cursor:pointer;text-align:left;font:inherit;color:inherit;transition:border-color .15s,box-shadow .15s}
.bak-step:disabled{cursor:not-allowed;opacity:.6}
.bak-step--active{border-color:var(--bak-primary);box-shadow:0 0 0 3px rgba(37,99,235,.14)}
.bak-step--done{border-color:#bbf7d0;background:#f0fdf4}
.bak-step__index{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;width:26px;height:26px;border-radius:50%;
background:var(--bak-primary);color:#fff;font-size:12.5px;font-weight:700}
.bak-step--done .bak-step__index{background:var(--bak-green)}
.bak-step--todo .bak-step__index{background:#cbd5e1;color:#475569}
.bak-step__meta{display:flex;flex-direction:column;min-width:0}
.bak-step__title{font-weight:650;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bak-step__sub{font-size:11.5px;color:var(--bak-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bak-progress{height:4px;border-radius:99px;background:#e2e8f0;overflow:hidden;margin-bottom:18px}
.bak-progress__bar{height:100%;background:var(--bak-primary);transition:width .25s ease}

/* Panel + card */
.bak-panel{position:relative;background:#fff;border:1px solid var(--bak-border);border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(15,23,42,.05)}
.bak-panel + .bak-panel{margin-top:0}
.bak-toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-start;justify-content:space-between;margin-bottom:14px}
.bak-toolbar__actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.bak-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;gap:8px;background:rgba(255,255,255,.72);
border-radius:12px;z-index:5}
.bak-footer{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:14px;border-top:1px solid var(--bak-border)}
.bak-footer__left{margin-right:auto;display:flex;align-items:center;gap:10px;color:var(--bak-muted);font-size:12.5px}
.bak-hint{font-size:12px;color:var(--bak-muted);margin:8px 0 0}
.bak-saved{color:var(--bak-green);font-weight:600;font-size:12.5px;display:inline-flex;align-items:center;gap:6px}
.bak-unsaved{color:var(--bak-yellow);font-weight:600;font-size:12.5px;display:inline-flex;align-items:center;gap:6px}

/* Banner */
.bak-banner{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;padding:10px 12px;border-radius:10px;
margin-bottom:12px;font-size:13px;border:1px solid transparent}
.bak-banner--error{background:var(--bak-red-bg);color:var(--bak-red);border-color:#fecaca}
.bak-banner--info{background:#eff6ff;color:var(--bak-primary-dark);border-color:#bfdbfe}
.bak-banner ul{margin:0;padding-left:18px}

/* Table */
.bak-table-wrap{overflow-x:auto;border:1px solid var(--bak-border);border-radius:10px}
.bak-table{width:100%;border-collapse:collapse;min-width:640px;background:#fff}
.bak-table th{background:#f1f5f9;text-align:center;font-size:12px;text-transform:uppercase;letter-spacing:.02em;color:#475569;
padding:10px 12px;border-bottom:1px solid var(--bak-border);white-space:nowrap;font-weight:700}
.bak-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;font-size:13.5px;text-align:center}
.bak-table tbody tr:last-child td{border-bottom:0}
.bak-table tbody tr:hover{background:#f8fafc}
.bak-table th.bak-num,.bak-table td.bak-num{text-align:center}
.bak-table td .bak-input,.bak-table td .bak-select{text-align:center}
/* Kolom Role tetap rata kiri (kolom pertama di semua tabel) */
.bak-table th:first-child{text-align:left}
.bak-table td[data-label="Role"]{text-align:left}
.bak-row--danger{background:rgba(254,226,226,.45)}
.bak-row--warn{background:rgba(254,249,195,.45)}
.bak-row--ok{background:rgba(220,252,231,.45)}
.bak-cat{display:inline-block;font-size:11.5px;color:#475569;background:#f1f5f9;border-radius:99px;padding:1px 8px;margin-top:3px}
.bak-cell-label{display:block;font-size:11px;color:var(--bak-muted);font-weight:600}
.bak-total{display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;align-items:center;margin-top:12px;padding:12px 14px;
border-radius:10px;background:#eff6ff;border:1px solid #bfdbfe;font-weight:700}
.bak-total__value{font-size:16px}
.bak-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.bak-chip{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:99px;background:#f1f5f9;border:1px solid var(--bak-border);font-size:12.5px}
.bak-chip strong{font-variant-numeric:tabular-nums}

/* Form controls */
.bak-input,.bak-select,.bak-textarea{width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;
background:#fff;color:inherit;transition:border-color .15s,box-shadow .15s;min-height:36px}
.bak-input:focus,.bak-select:focus,.bak-textarea:focus{outline:none;border-color:var(--bak-primary);box-shadow:0 0 0 3px rgba(37,99,235,.16)}
.bak-input:disabled,.bak-select:disabled{background:#f1f5f9;color:#64748b;cursor:not-allowed}
.bak-input--invalid,.bak-select--invalid{border-color:#ef4444;background:#fff5f5}
.bak-currency{display:flex;align-items:center;justify-content:center;gap:6px;min-width:140px}
.bak-currency__prefix{color:var(--bak-muted);font-size:12.5px;font-weight:600}
.bak-input--currency{text-align:center;font-variant-numeric:tabular-nums}
.bak-quantity{text-align:center;font-variant-numeric:tabular-nums;min-width:90px}
.bak-err{display:block;color:var(--bak-red);font-size:11.5px;margin-top:4px}
.bak-docs{display:flex;gap:6px;align-items:center;justify-content:center;min-width:190px}
/* Notes: ringkas, melebar seperti popup saat diklik/difokus */
.bak-notes{position:relative;display:inline-block;width:140px;vertical-align:middle}
.bak-notes__input{width:100%}
.bak-notes__input--expanded{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:300px;z-index:30;
box-shadow:0 10px 28px rgba(15,23,42,.18)}
.bak-docs .bak-input{min-width:130px}
.bak-readonly{color:#475569}
.bak-auto-reason{display:inline-block;max-width:220px;color:#0f172a;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;
padding:3px 8px;font-size:12px;line-height:1.35}
.bak-balanced{display:inline-block;color:var(--bak-green);background:var(--bak-green-bg);border:1px solid #bbf7d0;border-radius:8px;
padding:3px 8px;font-size:12px;font-weight:700}

/* Buttons */
.bak-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 14px;border-radius:8px;border:1px solid transparent;
font:inherit;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s,opacity .15s;white-space:nowrap}
.bak-btn:disabled{opacity:.55;cursor:not-allowed}
.bak-btn--primary{background:var(--bak-primary);color:#fff}
.bak-btn--primary:hover:not(:disabled){background:var(--bak-primary-dark)}
.bak-btn--success{background:var(--bak-green);color:#fff}
.bak-btn--danger{background:#dc2626;color:#fff}
.bak-btn--ghost{background:#fff;border-color:#cbd5e1;color:#334155}
.bak-btn--ghost:hover:not(:disabled){background:#f8fafc}
.bak-btn--sm{padding:5px 10px;font-size:12.5px}

/* Status badge */
.bak-badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;font-size:11.5px;font-weight:700;letter-spacing:.03em;
border:1px solid transparent;white-space:nowrap}
.bak-badge--draft{background:#f1f5f9;color:#475569;border-color:#e2e8f0}
.bak-badge--submitted{background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe}
.bak-badge--approved{background:var(--bak-green-bg);color:var(--bak-green);border-color:#bbf7d0}
.bak-badge--locked{background:#1f2937;color:#fff;border-color:#111827}
.bak-badge--readonly{background:#f1f5f9;color:#334155;border-color:#cbd5e1}

/* Variance pill */
.bak-variance{display:inline-flex;flex-direction:column;align-items:center;gap:2px;font-variant-numeric:tabular-nums;font-weight:600;text-align:center}
.bak-variance__pct{font-size:11.5px;padding:0 6px;border-radius:99px}
.bak-variance--ok .bak-variance__pct{background:var(--bak-green-bg);color:var(--bak-green)}
.bak-variance--warn .bak-variance__pct{background:var(--bak-yellow-bg);color:var(--bak-yellow)}
.bak-variance--danger .bak-variance__pct{background:var(--bak-red-bg);color:var(--bak-red)}
.bak-variance--neutral .bak-variance__pct{background:#f1f5f9;color:var(--bak-muted)}

/* Spinner */
.bak-spinner-wrap{display:inline-flex;align-items:center;gap:8px}
.bak-spinner{display:inline-block;border-radius:50%;border:2px solid rgba(37,99,235,.25);border-top-color:var(--bak-primary);
animation:bak-spin .7s linear infinite}
@keyframes bak-spin{to{transform:rotate(360deg)}}
.bak-spinner__label{font-size:12.5px;color:var(--bak-muted)}

/* Toasts */
.bak-toasts{position:fixed;right:16px;bottom:16px;z-index:60;display:flex;flex-direction:column;gap:8px;max-width:min(360px,92vw)}
.bak-toast{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:10px;background:#fff;color:var(--bak-text);
box-shadow:0 8px 24px rgba(15,23,42,.16);border-left:4px solid var(--bak-primary);animation:bak-slide-in .18s ease-out}
.bak-toast--success{border-left-color:var(--bak-green)}
.bak-toast--error{border-left-color:#dc2626}
.bak-toast__icon{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;color:#fff;font-size:12px;
font-weight:700;flex:0 0 auto;background:var(--bak-primary)}
.bak-toast--success .bak-toast__icon{background:var(--bak-green)}
.bak-toast--error .bak-toast__icon{background:#dc2626}
.bak-toast__message{flex:1 1 auto;font-size:13px;word-break:break-word}
.bak-toast__close{background:none;border:0;color:var(--bak-muted);font-size:16px;line-height:1;cursor:pointer;padding:0 2px}
@keyframes bak-slide-in{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

/* Modal */
.bak-modal{position:fixed;inset:0;z-index:70;display:flex;align-items:center;justify-content:center;padding:16px}
.bak-modal__backdrop{position:absolute;inset:0;background:rgba(15,23,42,.45)}
.bak-modal__panel{position:relative;background:#fff;border-radius:12px;padding:18px;width:min(460px,100%);
box-shadow:0 20px 45px rgba(15,23,42,.28)}
.bak-modal__title{margin:0 0 8px;font-size:15.5px;font-weight:700}
.bak-modal__body{font-size:13.5px;color:#334155}
.bak-modal__actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end;margin-top:16px}

/* Print-friendly: sembunyikan kontrol interaktif, ratakan tabel & total */
@media print{
.bak-root{padding:0;background:#fff;font-size:12px}
.bak-stepper,.bak-progress,.bak-toolbar__actions,.bak-footer,.bak-btn,.bak-toasts,.bak-modal,.bak-overlay,.bak-docs .bak-btn{display:none !important}
.bak-panel{box-shadow:none;border:0;padding:0}
.bak-table-wrap{overflow:visible;border:1px solid #cbd5e1;border-radius:0}
.bak-table{min-width:0}
.bak-table tr{break-inside:avoid;page-break-inside:avoid}
.bak-total{break-inside:avoid}
}

/* Responsive: tabel berubah menjadi daftar kartu di layar kecil */
@media (max-width:768px){
.bak-root{padding:12px}
.bak-header{flex-direction:column}
.bak-stepper{flex-direction:column}
.bak-step{padding:8px 10px}
.bak-step__sub{display:none}
.bak-footer{justify-content:stretch}
.bak-footer .bak-btn{flex:1 1 100%}
.bak-footer__left{width:100%;order:-1}
.bak-table-wrap{border:0;overflow:visible}
.bak-table{min-width:0}
.bak-table thead{display:none}
.bak-table tbody tr{display:block;border:1px solid var(--bak-border);border-radius:10px;margin-bottom:10px;padding:8px 10px;background:#fff}
.bak-table td{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;border:0;padding:6px 0;text-align:right}
.bak-table td::before{content:'';font-weight:700;color:#475569;font-size:12px;text-align:left;flex:0 0 auto;padding-right:8px}
.bak-table td[data-label]::before{content:attr(data-label)}
.bak-table td>div,.bak-table td>span:not(.bak-badge){min-width:0;max-width:64%}
.bak-currency{min-width:0;width:100%;max-width:200px}
.bak-toasts{left:12px;right:12px;bottom:12px;max-width:none}
}

/* ---------- Absensi BAK (Step 1A–1C) ---------- */
.bak-att{margin:0 0 18px}
.bak-att.is-locked{opacity:.55;pointer-events:none}
.bak-sec-no{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:21px;padding:0 6px;
border-radius:6px;background:#0f172a;color:#fff;font-size:11px;font-weight:800;letter-spacing:.03em;flex:0 0 auto}
.bak-badge-new{font-size:10px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#1d4ed8;
background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:1px 5px}
.bak-badge-ok{display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;padding:3px 9px;
border-radius:999px;background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;white-space:nowrap}
.bak-field{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#64748b;font-weight:600}
.bak-input{font:inherit;font-size:13px;padding:6px 9px;border:1px solid var(--bak-border);border-radius:8px;
background:#fff;color:var(--bak-text);min-height:34px}
.bak-input:focus{outline:none;border-color:var(--bak-primary);box-shadow:0 0 0 3px rgba(37,99,235,.16)}
.bak-tabs{display:inline-flex;border:1px solid var(--bak-border);border-radius:9px;overflow:hidden;background:#fff}
.bak-tabs button{font:inherit;font-size:12.5px;font-weight:650;padding:7px 12px;border:0;background:transparent;
color:var(--bak-muted);cursor:pointer;white-space:nowrap}
.bak-tabs button+button{border-left:1px solid var(--bak-border)}
.bak-tabs button.is-on{background:#0f172a;color:#fff}
.bak-drop{border:2px dashed #cbd5e1;border-radius:12px;background:#fff;padding:22px 16px;text-align:center;
cursor:pointer;transition:border-color .15s,background .15s}
.bak-drop:hover,.bak-drop:focus-visible{border-color:#93c5fd;background:#f8fbff;outline:none}
.bak-drop.is-over{border-color:var(--bak-primary);background:#eff6ff}
.bak-drop__ico{font-size:22px;color:var(--bak-primary);line-height:1}
.bak-drop__t{font-weight:700;margin:8px 0 2px}
.bak-drop__s{font-size:12px;color:var(--bak-muted)}
.bak-paste{width:100%;min-height:120px;resize:vertical;border:1px solid var(--bak-border);border-radius:10px;
padding:10px 12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11.5px;
line-height:1.5;color:var(--bak-text);background:#fff}
.bak-paste:focus{outline:none;border-color:var(--bak-primary);box-shadow:0 0 0 3px rgba(37,99,235,.16)}
.bak-filebar{display:flex;flex-wrap:wrap;gap:8px 12px;align-items:center;padding:9px 12px;border-radius:10px;
background:#f0fdf4;border:1px solid #bbf7d0;font-size:12.5px;margin-top:12px}
.bak-filebar__name{font-weight:700;display:flex;align-items:center;gap:6px;min-width:0;max-width:100%;
overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.bak-filebar__meta{color:var(--bak-muted);margin-left:auto;font-variant-numeric:tabular-nums}
.bak-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(108px,1fr));gap:8px;margin-top:10px}
.bak-stat{border:1px solid var(--bak-border);border-radius:10px;padding:8px 10px;background:#fff}
.bak-stat__l{font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--bak-muted);font-weight:800}
.bak-stat__v{font-size:15px;font-weight:800;font-variant-numeric:tabular-nums;margin-top:2px}
.bak-alert{display:flex;flex-wrap:wrap;gap:6px;padding:9px 11px;border-radius:10px;font-size:12.5px;
line-height:1.55;margin-top:10px}
.bak-alert--info{background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8}
.bak-alert--warn{background:#fef9c3;border:1px solid #fde68a;color:#a16207}
.bak-cnt{display:inline-flex;align-items:center;justify-content:center;min-width:28px;padding:2px 6px;border-radius:6px;
font-weight:750;font-variant-numeric:tabular-nums;font-size:11.5px;background:#f8fafc;color:#cbd5e1}
.bak-cnt.is-h{background:#dcfce7;color:#15803d}
.bak-cnt.is-sc{background:#fef9c3;color:#a16207}
.bak-cnt.is-sick{background:#fee2e2;color:#b91c1c}
.bak-cnt.is-ct{background:#eff6ff;color:#1d4ed8}
.bak-cnt.is-off{background:#f1f5f9;color:#94a3b8}
.bak-row-new td{background:#f8fbff}
.bak-price-box{display:inline-flex;align-items:center;border:1px solid #cbd5e1;border-radius:8px;overflow:hidden;
background:#fff;height:32px}
.bak-price-box:focus-within{border-color:var(--bak-primary);box-shadow:0 0 0 3px rgba(37,99,235,.16)}
.bak-price-box>span{font-size:11.5px;color:var(--bak-muted);padding:0 6px;background:#f8fafc;align-self:stretch;
display:flex;align-items:center;border-right:1px solid #eef2f7}
.bak-price-box>input{font:inherit;font-size:12.5px;border:0;outline:0;width:104px;padding:0 8px;text-align:right;
font-variant-numeric:tabular-nums;background:transparent;color:var(--bak-text)}
.bak-recap-wrap{max-height:620px;overflow:auto}
.bak-recap{min-width:1080px}
.bak-recap thead th{position:sticky;top:0;z-index:2}
.bak-recap td,.bak-recap th{white-space:nowrap}
.bak-recap td[data-label="Nama"]{white-space:nowrap;text-align:left;font-weight:600}
.bak-recap__no{width:52px;color:var(--bak-muted);font-variant-numeric:tabular-nums}
.bak-recap td.bak-recap__remarks,.bak-recap th.bak-recap__remarks{white-space:nowrap;max-width:none;
font-size:12px;color:var(--bak-muted)}
.bak-recap__row--excluded>td{opacity:.55}
.bak-recap__row--excluded .bak-rate{background:#f1f5f9;color:#64748b}
.bak-skip{display:inline-block;margin-left:4px;padding:1px 5px;border-radius:4px;font-size:10px;
font-weight:700;text-transform:uppercase;letter-spacing:.02em;background:#fee2e2;color:#b91c1c}
.bak-rate{display:inline-flex;align-items:center;justify-content:center;min-width:46px;padding:2px 8px;
border-radius:6px;font-weight:800;font-size:12px;font-variant-numeric:tabular-nums}
.bak-rate.is-full{background:#dcfce7;color:#15803d}
.bak-rate.is-partial{background:#fef9c3;color:#a16207}
.bak-days-box{display:inline-flex;align-items:center;gap:2px}
.bak-days-box>input{font:inherit;font-size:12.5px;width:56px;padding:3px 6px;text-align:right;color:var(--bak-text);
border:1px solid #cbd5e1;border-radius:6px;background:#fff;font-variant-numeric:tabular-nums}
.bak-days-box>input:focus{outline:none;border-color:var(--bak-primary);box-shadow:0 0 0 3px rgba(37,99,235,.16)}
.bak-days-reset{font:inherit;font-size:11px;line-height:1;padding:4px 5px;border-radius:6px;cursor:pointer;
border:1px solid #fde68a;background:#fef9c3;color:#a16207}
.bak-days-reset:hover{border-color:#fcd34d}
.bak-days-unit{font-size:11px;color:var(--bak-muted);font-variant-numeric:tabular-nums}
.bak-shortfall{margin-top:6px;display:flex;flex-direction:column;gap:3px;padding:6px 8px;border-radius:8px;
background:#fffbeb;border:1px solid #fde68a}
.bak-shortfall__t{font-size:10.5px;font-weight:800;letter-spacing:.03em;text-transform:uppercase;color:#a16207}
.bak-shortfall__i{font-size:11.5px;line-height:1.45;color:#78350f;display:flex;flex-wrap:wrap;gap:4px;align-items:baseline}
.bak-shortfall__m{font-weight:800;color:#b45309}
.bak-shortfall__tag{font-size:9.5px;font-weight:800;text-transform:uppercase;padding:1px 4px;border-radius:4px;
background:#fef3c7;border:1px solid #fcd34d;color:#92400e}
.bak-session{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 14px;padding:11px 13px;
border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
.bak-session__ico{font-size:16px;line-height:1}
.bak-session__body{display:flex;flex-direction:column;gap:2px;flex:1 1 240px;min-width:0;font-size:12.5px;line-height:1.5}
.bak-session__body strong{font-size:13px}
@media (max-width:768px){.bak-session{padding:10px}.bak-session .bak-btn{width:100%}}
.bak-zero{color:#94a3b8}
.bak-role-input{width:100%;min-width:120px;text-align:left}
.bak-diff{font-weight:800;font-variant-numeric:tabular-nums}
.bak-diff.is-up{color:#15803d}
.bak-diff.is-down{color:#b91c1c}
.bak-diff.is-zero{color:#94a3b8}
.bak-var-reason{white-space:nowrap;font-size:11.5px;font-weight:700;font-variant-numeric:tabular-nums}
.bak-var-down{color:#b91c1c}
.bak-var-up{color:#15803d}
.bak-narrative{margin-top:10px;padding:10px 12px;border:1px solid var(--bak-border);border-radius:10px;
background:#f8fafc;font-size:12px;line-height:1.65;color:#334155}
.bak-narrative__t{font-weight:800;margin-bottom:4px}
.bak-narrative__list{margin:0;padding-left:18px}
.bak-narrative__list>li{margin:2px 0}
.bak-narrative__list>li.is-down{color:#b91c1c}
.bak-narrative__list>li.is-up{color:#15803d}
.bak-narrative__total{margin-top:6px;padding-top:6px;border-top:1px dashed #cbd5e1;font-variant-numeric:tabular-nums}
.bak-compare{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;margin-top:10px}
.bak-compare__i{display:flex;flex-direction:column;gap:2px;padding:8px 11px;border-radius:9px;
border:1px solid var(--bak-border);background:#fff}
.bak-compare__i>span{font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--bak-muted);
font-weight:800}
.bak-compare__i>strong{font-size:14px;font-variant-numeric:tabular-nums}
.bak-compare__i>strong.is-up{color:#15803d}
.bak-compare__i>strong.is-down{color:#b91c1c}
.bak-locked-rate{display:inline-flex;align-items:center;gap:3px;padding:2px 7px;border-radius:6px;
background:#f1f5f9;border:1px solid #e2e8f0;color:#64748b;font-weight:700;font-size:12px;
font-variant-numeric:tabular-nums}
.bak-locked-rate>span{font-size:9px;opacity:.55}
.bak-act-col{width:52px;text-align:center}
.bak-del-btn{font:inherit;font-size:13px;line-height:1;padding:5px 7px;border-radius:6px;cursor:pointer;
border:1px solid #fecaca;background:#fef2f2;color:#b91c1c}
.bak-del-btn:hover{background:#fee2e2;border-color:#fca5a5}
@media print{
.bak-recap-wrap{max-height:none;overflow:visible}
.bak-recap{min-width:0}
.bak-recap thead th{position:static}
}
@media (max-width:768px){
.bak-recap-wrap{max-height:none;overflow:visible}
.bak-recap{min-width:0}
.bak-recap td,.bak-recap th{white-space:normal}
.bak-recap td[data-label="Nama"]{text-align:right;font-weight:400}
.bak-recap__no{width:auto}
}
.bak-history{margin-top:12px;border:1px solid var(--bak-border);border-radius:10px;background:#fff;overflow:hidden}
.bak-history__t{padding:8px 12px;background:#f8fafc;border-bottom:1px solid #f1f5f9;font-size:10.5px;
text-transform:uppercase;letter-spacing:.05em;color:var(--bak-muted);font-weight:800}
.bak-history__row{display:flex;flex-wrap:wrap;gap:6px 12px;align-items:center;padding:8px 12px;
border-bottom:1px solid #f8fafc;font-size:12.5px}
.bak-history__row:last-child{border-bottom:0}
.bak-history__row.is-now{background:#f0fdf4}
.bak-history__p{font-weight:750;min-width:92px}
.bak-history__m{color:var(--bak-muted);font-variant-numeric:tabular-nums}
.bak-history__tag{margin-left:auto}
@media (max-width:768px){
.bak-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
.bak-filebar__meta{margin-left:0;width:100%}
.bak-history__tag{margin-left:0}
.bak-price-box>input{width:88px}
}
@media (max-width:480px){
.bak-toolbar__actions{width:100%}
.bak-field{width:100%}
.bak-field .bak-input{width:100%}
.bak-tabs{width:100%}
.bak-tabs button{flex:1 1 0;text-align:center}
}
`;

/* ========================================================================== */
/* Main component                                                              */
/* ========================================================================== */

const STEPS = [
  { id: 1 as const, title: 'WFM Calculation', subtitle: 'Absensi, rekap & harga posisi' },
  { id: 2 as const, title: 'Max Potential Earnings', subtitle: 'Rate card & quantity' },
  { id: 3 as const, title: 'Accrual Input', subtitle: 'Input nilai accrual' },
  { id: 4 as const, title: 'Actual Input', subtitle: 'Verifikasi finance' },
];

export default function BAKStepper({
  apiBaseUrl = DEFAULT_API_BASE,
  initialStep = 1,
  onLocked,
  uploadSupportingDoc,
  onError,
}: BAKStepperProps) {
  const rawApi = useMemo(() => createBakApi(apiBaseUrl), [apiBaseUrl]);
  /** true bila server menyatakan sesi login sudah habis. */
  const [sessionExpired, setSessionExpired] = useState(false);
  const sessionExpiredRef = useRef(false);

  /**
   * Bungkus semua pemanggilan API: begitu server membalas 401, tampilkan
   * peringatan sesi habis sekali saja, bukan pesan "endpoint tidak ada".
   */
  const api = useMemo(() => {
    const wrapped = {} as BAKApi;
    (Object.keys(rawApi) as Array<keyof BAKApi>).forEach((key) => {
      const fn = rawApi[key] as (...args: unknown[]) => Promise<unknown>;
      (wrapped as Record<string, unknown>)[key as string] = async (...args: unknown[]) => {
        try {
          return await fn(...args);
        } catch (error) {
          if (isSessionExpiredError(error) && !sessionExpiredRef.current) {
            sessionExpiredRef.current = true;
            setSessionExpired(true);
          }
          throw error;
        }
      };
    });
    return wrapped;
  }, [rawApi]);

  const { toasts, pushToast, dismiss } = useToasts();

  /* ------------------------------- State ---------------------------------- */
  const [step, setStep] = useState<1 | 2 | 3 | 4>(initialStep);

  const [maxPotential, setMaxPotential] = useState<MaxPotentialEarnings | null>(null);
  const [sellerRows, setSellerRows] = useState<SellerInputRow[]>([]);
  const [actualRows, setActualRows] = useState<ActualInputRow[]>([]);
  const [sellerStatus, setSellerStatus] = useState<BAKStatus>('DRAFT');
  const [actualStatus, setActualStatus] = useState<BAKStatus>('DRAFT');

  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [pendingIds, setPendingIds] = useState<string[]>([]);
  const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);
  const [showSellerErrors, setShowSellerErrors] = useState(false);
  const [showActualErrors, setShowActualErrors] = useState(false);
  const [pendingNav, setPendingNav] = useState<1 | 2 | 3 | 4 | null>(null);
  /** Step yang isian manualnya sedang dikonfirmasi untuk di-reset. */
  const [resetTarget, setResetTarget] = useState<'seller' | 'actual' | null>(null);
  const [resetBusy, setResetBusy] = useState(false);
  /** true bila ada perubahan pada Step 3 yang belum disimpan ke server. */
  const [actualDirty, setActualDirty] = useState(false);
  /** Mode edit Step 1 (Max Potential Earnings). */
  const [editingMax, setEditingMax] = useState(false);
  const [maxDirty, setMaxDirty] = useState(false);

  /* ------------------- Absensi (Step 1A–1C) ------------------- */
  /** Periode absensi (YYYY-MM). Default: bulan berjalan. */
  const [attPeriod, setAttPeriod] = useState<string>(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  });
  const [attSummary, setAttSummary] = useState<AttendanceSummary | null>(null);
  const [attPeriods, setAttPeriods] = useState<AttendancePeriodInfo[]>([]);
  const [attBusy, setAttBusy] = useState(false);
  /** 'file' = unggah berkas, 'paste' = tempel dari Excel. */
  const [attMode, setAttMode] = useState<'file' | 'paste'>('file');
  const [attText, setAttText] = useState('');
  const [attDragging, setAttDragging] = useState(false);
  const [attNotice, setAttNotice] = useState<string | null>(null);
  const attFileRef = useRef<HTMLInputElement | null>(null);

  /** Rekap per orang: No. | NIK | Nama | Posisi | Remarks | Working days | Rate | Unit Price | Counting Price */
  const recapCols = 9;

  /**
   * Role di rate card yang tidak ada di berkas periode ini TETAP ditampilkan
   * dengan Rate 0, supaya Max Potential manual-nya masih bisa diisi.
   */
  const zeroRateRoles = (maxPotential?.items ?? [])
    .filter((item) => !item.quantity)
    .map((item) => item.role);
  const maxVisibleItems = maxPotential?.items ?? [];

  /** Peta posisi -> data posisi yang punya orang kurang hari (alasan variance). */
  const shortfallByPosition = useMemo(() => {
    const map = new Map<string, AttendancePosition>();
    (attSummary?.positions ?? []).forEach((item) => {
      if (item.shortfalls?.length) map.set(item.position, item);
    });
    return map;
  }, [attSummary]);

  /* ------------------------------- Refs ----------------------------------- */
  const loaded = useRef({ seller: false, actual: false });
  const onErrorRef = useRef(onError);
  const sellerRowsRef = useRef<SellerInputRow[]>([]);
  const actualRowsRef = useRef<ActualInputRow[]>([]);
  const maxPotentialRef = useRef<MaxPotentialEarnings | null>(null);
  const timersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());
  const fullSaveRef = useRef(false);
  const docInputRef = useRef<HTMLInputElement | null>(null);
  const activeDocRowRef = useRef<string | null>(null);

  useEffect(() => {
    onErrorRef.current = onError;
  }, [onError]);

  useEffect(() => {
    sellerRowsRef.current = sellerRows;
  }, [sellerRows]);

  useEffect(() => {
    actualRowsRef.current = actualRows;
  }, [actualRows]);

  useEffect(() => {
    maxPotentialRef.current = maxPotential;
    // Rate card berubah (mis. Working days dikoreksi) -> baris Accrual & Actual
    // dimuat ulang saat step-nya dibuka supaya nilainya ikut sinkron.
    loaded.current.seller = false;
    loaded.current.actual = false;
  }, [maxPotential]);

  useEffect(() => {
    const timers = timersRef.current;
    return () => {
      timers.forEach(clearTimeout);
      timers.clear();
    };
  }, []);

  /* ---------------------------- Derived state ----------------------------- */
  const isFullyLocked = actualRows.length > 0 && actualRows.every((row) => row.status === 'LOCKED');
  /** Perubahan seller yang masih menunggu autosave (Step 2). */
  const isDirty = pendingIds.length > 0;
  /** Perubahan belum tersimpan pada step yang sedang aktif. */
  const stepDirty =
    step === 4 ? actualDirty : step === 2 ? maxDirty : step === 3 ? isDirty : false;

  /** Breakdown kategori + grand total dihitung dari baris terkini (mengikuti hasil edit). */
  const maxSummary = useMemo(() => {
    const items = maxPotential?.items ?? [];
    const grouped = new Map<string, number>();
    items.forEach((item) => {
      const key = item.category ?? 'Uncategorized';
      grouped.set(key, round2((grouped.get(key) ?? 0) + (item.base_amount ?? item.subtotal)));
    });
    const wfmTotal = round2(items.reduce((acc, item) => acc + item.subtotal, 0));
    // "Dipakai" = Max Potential manual bila diisi, kalau tidak hasil WFM.
    // Menjumlahkan manual_amount saja akan salah saat hanya sebagian baris
    // yang punya nilai manual.
    const usedTotal = round2(
      items.reduce((acc, item) => acc + (item.base_amount ?? item.manual_amount ?? item.subtotal), 0),
    );
    const manualTotal = round2(items.reduce((acc, item) => acc + (item.manual_amount ?? 0), 0));
    const wfmRate = round2(items.reduce((acc, item) => acc + item.quantity, 0));
    const manualRate = round2(items.reduce((acc, item) => acc + (item.manual_rate ?? 0), 0));
    return {
      categories: Array.from(grouped.entries()).map(([category, subtotal]) => ({
        category,
        subtotal,
      })),
      grandTotal: usedTotal,
      wfmTotal,
      manualTotal,
      usedTotal,
      wfmRate,
      manualRate,
      selisih: round2(wfmTotal - usedTotal),
    };
  }, [maxPotential]);

  /** Narasi variance Max Potential vs WFM (format laporan finance). */
  const varianceNarrative = useMemo(() => buildVarianceLines(maxVisibleItems), [maxVisibleItems]);

  const sellerIssues = useMemo(() => validateSellerRows(sellerRows), [sellerRows]);
  const actualIssues = useMemo(() => validateActualRows(actualRows, false), [actualRows]);

  const sellerIssuesById = useMemo(() => groupIssues(sellerIssues), [sellerIssues]);
  const actualIssuesById = useMemo(() => groupIssues(actualIssues), [actualIssues]);

  const sellerTotals = useMemo(() => {
    const totals = sellerRows.reduce(
      (acc, row) => {
        const { variance } = computeVariance(row.max_potential, row.seller_input);
        acc.maxPotential += row.max_potential;
        acc.sellerInput += row.seller_input ?? 0;
        acc.variance += row.seller_input == null ? 0 : variance;
        return acc;
      },
      { maxPotential: 0, sellerInput: 0, variance: 0 },
    );
    return {
      maxPotential: round2(totals.maxPotential),
      sellerInput: round2(totals.sellerInput),
      variance: round2(totals.variance),
      variancePct: totals.maxPotential === 0 ? 0 : round2((totals.variance / totals.maxPotential) * 100),
    };
  }, [sellerRows]);

  /** Role yang belum punya baris seller_input sehingga belum bisa diverifikasi di Step 3. */
  const rolesWithoutSellerBaseline = useMemo(
    () => sellerRows.filter((row) => row.id == null).map((row) => row.role),
    [sellerRows],
  );

  const actualTotals = useMemo(() => {
    const totals = actualRows.reduce(
      (acc, row) => {
        const { variance } = computeVariance(row.seller_input, row.actual_input);
        acc.maxPotential += row.max_potential;
        acc.sellerInput += row.seller_input ?? 0;
        acc.actualInput += row.actual_input ?? 0;
        acc.variance += row.actual_input == null ? 0 : variance;
        return acc;
      },
      { maxPotential: 0, sellerInput: 0, actualInput: 0, variance: 0 },
    );
    return {
      maxPotential: round2(totals.maxPotential),
      sellerInput: round2(totals.sellerInput),
      actualInput: round2(totals.actualInput),
      variance: round2(totals.variance),
      variancePct: totals.sellerInput === 0 ? 0 : round2((totals.variance / totals.sellerInput) * 100),
    };
  }, [actualRows]);

  /* ---------------------------- Data loading ------------------------------ */

  /**
   * Role yang dipakai periode ini. Baris di luar daftar ini disembunyikan
   * supaya Step 2 & 3 sama persis dengan isi berkas absensi.
   */
  const activeRolesRef = useRef<Set<string> | null>(null);

  useEffect(() => {
    activeRolesRef.current =
      attSummary?.uploaded && attSummary.positions.length
        ? new Set(attSummary.positions.map((item) => item.position))
        : null;
  }, [attSummary]);

  const loadMaxPotential = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const raw = await api.getMaxPotentialEarnings();
      setMaxPotential(normalizeMaxPotential(raw));
    } catch (error) {
      setLoadError(errorMessage(error));
      onErrorRef.current?.(error);
    } finally {
      setLoading(false);
    }
  }, [api]);

  /* ------------------- Absensi: muat, unggah, harga, sinkron ------------------- */

  const loadAttendance = useCallback(
    async (period: string) => {
      setAttBusy(true);
      try {
        const data = await api.getAttendance(period);
        setAttSummary(data.summary);
        setAttPeriods(data.periods ?? []);
      } catch (error) {
        // Absensi bersifat tambahan: kegagalan di sini tidak boleh
        // menghentikan wizard, cukup tampilkan pesan.
        setAttNotice(errorMessage(error));
      } finally {
        setAttBusy(false);
      }
    },
    [api],
  );

  const applyAttendancePayload = useCallback((data: AttendancePayload, note: string) => {
    setAttSummary(data.summary);
    setAttPeriods(data.periods ?? []);
    setAttPeriod(data.summary?.period ?? '');
    setAttNotice(note);
    // Backend menyinkronkan rate card otomatis setelah upload, jadi tabel
    // Max Potential Earnings ikut disegarkan.
    void loadMaxPotential();
  }, [loadMaxPotential]);

  const uploadAttendanceFile = useCallback(
    async (file: File) => {
      setAttBusy(true);
      setAttNotice(null);
      try {
        const data = await api.uploadAttendanceFile(file, attPeriod);
        applyAttendancePayload(
          data,
          `Berkas "${file.name}" dibaca: ${data.summary.total_rows.toLocaleString('id-ID')} baris, ` +
            `${data.summary.total_people} orang, hari kerja standar ${data.summary.standard_days}.`,
        );
        pushToast('success', 'Absensi tersimpan.');
      } catch (error) {
        setAttNotice(errorMessage(error));
        pushToast('error', errorMessage(error));
      } finally {
        setAttBusy(false);
      }
    },
    [api, attPeriod, applyAttendancePayload, pushToast],
  );

  const uploadAttendanceText = useCallback(async () => {
    if (!attText.trim()) {
      setAttNotice('Tempel dulu data dari Excel, lalu tekan Proses.');
      return;
    }
    setAttBusy(true);
    setAttNotice(null);
    try {
      const data = await api.uploadAttendanceText(attText, attPeriod);
      applyAttendancePayload(
        data,
        `Data tempel dibaca: ${data.summary.total_rows.toLocaleString('id-ID')} baris, ` +
          `${data.summary.total_people} orang, hari kerja standar ${data.summary.standard_days}.`,
      );
      pushToast('success', 'Absensi tersimpan.');
    } catch (error) {
      setAttNotice(errorMessage(error));
      pushToast('error', errorMessage(error));
    } finally {
      setAttBusy(false);
    }
  }, [api, attPeriod, attText, applyAttendancePayload, pushToast]);

  const saveAttendancePrice = useCallback(
    async (position: string, raw: string) => {
      const digits = raw.replace(/[^\d]/g, '');
      const value = digits ? Number(digits) : 0;
      setAttBusy(true);
      try {
        const data = await api.saveAttendancePrices(attPeriod, { [position]: value });
        setAttSummary(data.summary);
        // Harga baru langsung mengalir ke rate card Max Potential.
        void loadMaxPotential();
      } catch (error) {
        setAttNotice(errorMessage(error));
        pushToast('error', errorMessage(error));
      } finally {
        setAttBusy(false);
      }
    },
    [api, attPeriod, pushToast, loadMaxPotential],
  );

  const addMissingPositions = useCallback(async () => {
    const missing = attSummary?.missing_in_rate ?? [];
    if (!missing.length) return;
    setAttBusy(true);
    try {
      const data = await api.addAttendancePositions(attPeriod, missing);
      setAttSummary(data.summary);
      setAttNotice(`${missing.length} posisi baru ditambahkan. Isi harganya di tabel 1C.`);
      pushToast('success', `${missing.length} posisi ditambahkan.`);
      // Posisi baru sudah masuk rate card otomatis — segarkan tabel Max Potential.
      void loadMaxPotential();
    } catch (error) {
      setAttNotice(errorMessage(error));
      pushToast('error', errorMessage(error));
    } finally {
      setAttBusy(false);
    }
  }, [api, attPeriod, attSummary, pushToast, loadMaxPotential]);

  const saveAttendanceActive = useCallback(
    async (position: string, name: string, rawActive: string) => {
      const trimmed = rawActive.trim();
      if (trimmed !== '' && !/^\d+(?:[.,]\d{1,2})?$/.test(trimmed)) {
        setAttNotice('Working days harus berupa angka (contoh 31 atau 15,5).');
        pushToast('error', 'Working days harus berupa angka.');
        return;
      }
      const active = trimmed === '' ? null : Number(trimmed.replace(',', '.'));
      setAttBusy(true);
      try {
        const data = await api.saveAttendanceEmployeeDays(attPeriod, [{ position, name, active }]);
        setAttSummary(data.summary);
        setAttNotice(
          active === null
            ? `Working days ${name} dikembalikan ke nilai otomatis.`
            : `Working days ${name} dikoreksi menjadi ${active}.`,
        );
        // Rate berubah -> quantity & subtotal Max Potential ikut menyesuaikan.
        void loadMaxPotential();
      } catch (error) {
        setAttNotice(errorMessage(error));
        pushToast('error', errorMessage(error));
      } finally {
        setAttBusy(false);
      }
    },
    [api, attPeriod, pushToast, loadMaxPotential],
  );

  const runReset = useCallback(
    async (target: 'seller' | 'actual') => {
      setResetBusy(true);
      try {
        if (target === 'seller') {
          const raw = await api.resetSellerInput();
          const active = activeRolesRef.current;
          const base = (maxPotentialRef.current?.items ?? []).filter(
            (item) => active === null || active.has(item.role),
          );
          setSellerRows(buildSellerRows(base, extractItems(raw)));
          setSellerStatus(readStatus(raw, 'DRAFT'));
          setPendingIds([]);
          pushToast('info', 'Isian manual Accrual Input dihapus.');
        } else {
          const raw = await api.resetActualInput();
          const active = activeRolesRef.current;
          const visible = sellerRowsRef.current.filter(
            (row) => active === null || active.has(row.role),
          );
          setActualRows(buildActualRows(visible, extractItems(raw)));
          setActualStatus(readStatus(raw, 'DRAFT'));
          setActualDirty(false);
          pushToast('info', 'Isian manual Actual Input dihapus.');
        }
        setLastSavedAt(new Date());
      } catch (error) {
        pushToast('error', `Gagal mereset data: ${errorMessage(error)}`);
        onErrorRef.current?.(error);
      } finally {
        setResetBusy(false);
        setResetTarget(null);
      }
    },
    [api, pushToast],
  );

  const syncAttendance = useCallback(async () => {    setAttBusy(true);
    try {
      const data = await api.syncAttendance(attPeriod);
      setAttSummary(data.summary);
      setMaxPotential(normalizeMaxPotential({ items: data.items, categories: data.categories, grand_total: data.grand_total }));
      setEditingMax(false);
      setMaxDirty(false);
      setAttNotice('Max Potential Earnings sudah disinkronkan dari absensi.');
      pushToast('success', 'Max Potential Earnings tersinkron.');
    } catch (error) {
      setAttNotice(errorMessage(error));
      pushToast('error', errorMessage(error));
    } finally {
      setAttBusy(false);
    }
  }, [api, attPeriod, pushToast]);

  const removeAttendance = useCallback(async () => {
    setAttBusy(true);
    try {
      const data = await api.removeAttendance(attPeriod);
      applyAttendancePayload(data, 'Data absensi periode ini dihapus.');
      pushToast('info', 'Data absensi dihapus.');
    } catch (error) {
      setAttNotice(errorMessage(error));
      pushToast('error', errorMessage(error));
    } finally {
      setAttBusy(false);
    }
  }, [api, attPeriod, applyAttendancePayload, pushToast]);

  const updateMaxRow = useCallback(
    (
      id: string | number,
      patch: Partial<
        Pick<
          MaxPotentialRow,
          'billing_type' | 'price_per_unit' | 'quantity' | 'manual_amount' | 'manual_rate' | 'role'
        >
      >,
    ) => {
      setMaxPotential((prev) => {
        if (!prev) return prev;
        return {
          ...prev,
          items: prev.items.map((item) => {
            if (String(item.id) !== String(id)) return item;
            // Catat baris yang nilai manualnya benar-benar diubah user, supaya
            // simpan berikutnya tidak "mengunci" nilai WFM jadi manual.
            if ('manual_rate' in patch || 'manual_amount' in patch) {
              touchedManualRef.current.add(String(id));
            }
            const merged = { ...item, ...patch };
            const price = merged.price_per_unit ?? 0;
            const subtotal = computeAmount(price, merged.quantity) ?? merged.subtotal;

            // Max Potential Rate × Price/Unit = Max Potential (Manual).
            // Salah satu diedit, yang lain mengikuti agar tetap konsisten.
            let manualAmount = merged.manual_amount ?? null;
            let manualRate = merged.manual_rate ?? null;
            if ('manual_rate' in patch) {
              manualAmount = manualRate == null ? null : round2(manualRate * price);
            } else if ('manual_amount' in patch && manualAmount != null && price > 0) {
              manualRate = round2(manualAmount / price);
            }

            return {
              ...merged,
              subtotal,
              manual_rate: manualRate,
              manual_amount: manualAmount,
              // Nilai acuan ikut berubah: manual bila diisi, kalau tidak hasil WFM.
              base_amount: manualAmount ?? subtotal,
            };
          }),
        };
      });
      setMaxDirty(true);
    },
    [],
  );

  const cancelMaxEdit = useCallback(() => {
    setEditingMax(false);
    setMaxDirty(false);
    deletedMaxIdsRef.current = [];
    touchedManualRef.current = new Set();
    void loadMaxPotential();
  }, [loadMaxPotential]);

  /** Id role yang dihapus di mode edit (dikirim sebagai delete saat Simpan). */
  const deletedMaxIdsRef = useRef<number[]>([]);

  /**
   * Id baris yang nilai manualnya (Max Potential Rate / Max Potential) diubah
   * user pada sesi edit ini. Hanya baris ini yang dikirim ke server, supaya
   * klik Simpan tanpa mengubah apa pun tidak mengunci nilai WFM sebagai manual.
   */
  const touchedManualRef = useRef<Set<string>>(new Set());

  /** Tambah baris role baru (belum tersimpan) di akhir tabel. */
  const addMaxRole = useCallback(() => {
    setMaxPotential((prev) => {
      if (!prev) return prev;
      const draftId = `new-${Date.now()}`;
      return {
        ...prev,
        items: [
          ...prev.items,
          {
            id: draftId,
            role: '',
            billing_type: 'Headcount',
            price_per_unit: 0,
            quantity: 0,
            subtotal: 0,
            manual_amount: null,
            base_amount: 0,
            is_custom: true,
          },
        ],
      };
    });
    setMaxDirty(true);
  }, []);

  /** Hapus satu role dari tabel (baru benar-benar dihapus saat Simpan). */
  const removeMaxRole = useCallback((row: MaxPotentialRow) => {
    if (typeof row.id === 'number') {
      deletedMaxIdsRef.current = Array.from(new Set([...deletedMaxIdsRef.current, row.id]));
    }
    setMaxPotential((prev) =>
      prev ? { ...prev, items: prev.items.filter((item) => String(item.id) !== String(row.id)) } : prev,
    );
    setMaxDirty(true);
  }, []);

  const saveMaxPotential = useCallback(async () => {
    const items = maxPotentialRef.current?.items ?? [];
    const invalid = items.find(
      (item) =>
        item.price_per_unit == null ||
        item.price_per_unit < 0 ||
        (item.manual_amount != null && item.manual_amount < 0) ||
        (item.manual_rate != null && item.manual_rate < 0),
    );
    if (invalid) {
      pushToast(
        'error',
        `Price/Unit, Max Potential Rate, dan Max Potential "${invalid.role}" tidak boleh negatif.`,
      );
      return false;
    }
    const nameless = items.find((item) => String(item.role ?? '').trim() === '');
    if (nameless) {
      pushToast('error', 'Nama role baru wajib diisi.');
      return false;
    }

    setSaving(true);
    try {
      const payload: MaxPotentialSaveItem[] = items.map((item) => {
        const base = {
          id: typeof item.id === 'number' ? item.id : null,
          role: item.role,
          billing_type: item.billing_type ?? 'Hours',
          price_per_unit: item.price_per_unit ?? null,
        };
        // Baris baru selalu ikut; baris lama hanya bila user mengubah nilai
        // manualnya, supaya nilai WFM tidak otomatis terkunci sebagai manual.
        const touched =
          typeof item.id !== 'number' || touchedManualRef.current.has(String(item.id));
        return touched
          ? {
              ...base,
              manual_amount: item.manual_amount ?? null,
              manual_rate: item.manual_rate ?? null,
            }
          : base;
      });
      // Role yang dihapus dikirim sebagai item tersendiri.
      deletedMaxIdsRef.current.forEach((id) => {
        payload.push({
          id,
          billing_type: 'Headcount',
          price_per_unit: null,
          delete: true,
        });
      });

      await api.saveMaxPotentialEarnings(payload);
      deletedMaxIdsRef.current = [];
      touchedManualRef.current = new Set();
      setEditingMax(false);
      setMaxDirty(false);
      pushToast('success', 'Max potential earnings berhasil disimpan.');
      // Baris accrual & actual memakai rate card ini sebagai acuan, jadi
      // paksa keduanya dimuat ulang agar tetap sinkron.
      loaded.current.seller = false;
      loaded.current.actual = false;
      await loadMaxPotential();
      return true;
    } catch (error) {
      pushToast('error', `Gagal menyimpan max potential earnings: ${errorMessage(error)}`);
      onErrorRef.current?.(error);
      return false;
    } finally {
      setSaving(false);
    }
  }, [api, loadMaxPotential, pushToast]);

  const loadSellerInput = useCallback(async () => {
    if (!maxPotentialRef.current) return;
    setLoading(true);
    setLoadError(null);
    try {
      const raw = await api.getSellerInput();
      const active = activeRolesRef.current;
      const base = maxPotentialRef.current.items.filter(
        (item) => active === null || active.has(item.role),
      );
      setSellerRows(buildSellerRows(base, extractItems(raw)));
      setSellerStatus(readStatus(raw, 'DRAFT'));
      loaded.current.seller = true;
    } catch (error) {
      setLoadError(errorMessage(error));
      onErrorRef.current?.(error);
    } finally {
      setLoading(false);
    }
  }, [api]);

  const loadActualInput = useCallback(async () => {
    setLoading(true);
    setLoadError(null);
    try {
      const active = activeRolesRef.current;
      // Selalu ambil ulang Accrual Input dari server supaya nilai acuan
      // (dan baris hasil edit Step 2) benar-benar sinkron.
      const sellerRaw = await api.getSellerInput();
      const base = maxPotentialRef.current
        ? buildSellerRows(
            maxPotentialRef.current.items.filter((item) => active === null || active.has(item.role)),
            extractItems(sellerRaw),
          )
        : sellerRowsRef.current;
      setSellerRows(base);
      setSellerStatus(readStatus(sellerRaw, 'DRAFT'));
      loaded.current.seller = true;

      const raw = await api.getActualInput();
      const visible = base.filter((row) => active === null || active.has(row.role));
      setActualRows(buildActualRows(visible, extractItems(raw)));
      setActualStatus(readStatus(raw, 'DRAFT'));
      setActualDirty(false);
      loaded.current.actual = true;
    } catch (error) {
      setLoadError(errorMessage(error));
      onErrorRef.current?.(error);
    } finally {
      setLoading(false);
    }
  }, [api]);

  useEffect(() => {
    void loadMaxPotential();
  }, [loadMaxPotential]);

  useEffect(() => {
    void loadAttendance(attPeriod);
  }, [loadAttendance, attPeriod]);

  useEffect(() => {
    if (step === 3 && !loaded.current.seller && maxPotential) {
      void loadSellerInput();
    }
  }, [step, maxPotential, loadSellerInput]);

  useEffect(() => {
    if (step === 4 && !loaded.current.actual) {
      void loadActualInput();
    }
  }, [step, loadActualInput]);

  /* ------------------------------ Autosave -------------------------------- */

  const syncPending = useCallback(() => {
    setPendingIds(Array.from(timersRef.current.keys()));
  }, []);

  const clearTimers = useCallback(() => {
    timersRef.current.forEach(clearTimeout);
    timersRef.current.clear();
    syncPending();
  }, [syncPending]);

  const persistSellerRow = useCallback(
    async (rowKey: string) => {
      const timer = timersRef.current.get(rowKey);
      if (timer) clearTimeout(timer);
      timersRef.current.delete(rowKey);
      syncPending();

      if (fullSaveRef.current) return;
      const row =
        sellerRowsRef.current.find((item) => item.key === rowKey) ??
        sellerRowsRef.current.find((item) => String(item.max_potential_id) === rowKey);
      if (!row || row.locked || row.seller_input == null) return;

      try {
        const response = await api.saveSellerInput([serializeSellerRow(row)]);
        mergeSellerResponse(response, setSellerRows);
        setLastSavedAt(new Date());
      } catch (error) {
        pushToast('error', `Gagal auto-save baris "${row.role}": ${errorMessage(error)}`);
        onErrorRef.current?.(error);
      }
    },
    [api, pushToast, syncPending],
  );

  const scheduleSellerAutosave = useCallback(
    (rowKey: string) => {
      const existing = timersRef.current.get(rowKey);
      if (existing) clearTimeout(existing);
      timersRef.current.set(
        rowKey,
        setTimeout(() => {
          void persistSellerRow(rowKey);
        }, AUTOSAVE_DEBOUNCE_MS),
      );
      syncPending();
    },
    [persistSellerRow, syncPending],
  );

  const saveSellerDraft = useCallback(
    async (options?: { silent?: boolean }) => {
      // Permisif: draft menyimpan apa yang sudah ada. Kelengkapan form ditegakkan
      // di handleNext (tombol Next) dan di Submit step 3, bukan di sini — kalau
      // tidak, draft parsial tak pernah bisa tersimpan / dinavigasi.
      const rows = sellerRowsRef.current.filter(
        (row) =>
          !row.locked &&
          (row.seller_input != null ||
            row.variance_reason !== '' ||
            row.notes.trim() !== '' ||
            row.id != null),
      );

      if (rows.length === 0) {
        return true;
      }

      clearTimers();
      fullSaveRef.current = true;
      setSaving(true);
      try {
        const response = await api.saveSellerInput(rows.map(serializeSellerRow));
        mergeSellerResponse(response, setSellerRows);
        setSellerStatus(readStatus(response, sellerStatus));
        setLastSavedAt(new Date());
        if (!options?.silent) pushToast('success', 'Draft seller input berhasil disimpan.');
        return true;
      } catch (error) {
        pushToast('error', `Gagal menyimpan draft: ${errorMessage(error)}`);
        onErrorRef.current?.(error);
        return false;
      } finally {
        fullSaveRef.current = false;
        setSaving(false);
      }
    },
    [api, clearTimers, pushToast, sellerStatus],
  );

  const persistActualRows = useCallback(
    async (
      status: BAKStatus,
      options?: { silent?: boolean; rows?: ActualInputRow[] },
    ) => {
      const rows = options?.rows ?? actualRowsRef.current;
      fullSaveRef.current = true;
      setSaving(true);
      try {
        const response = await api.saveActualInput(
          rows.map((row) => serializeActualRow(row, resolveActualStatus(row.status, status))),
        );
        mergeActualResponse(response, setActualRows);
        setActualStatus(readStatus(response, status));
        setActualDirty(false);
        setLastSavedAt(new Date());
        if (!options?.silent) pushToast('success', 'Actual input berhasil disimpan.');
        return true;
      } catch (error) {
        pushToast('error', `Gagal menyimpan actual input: ${errorMessage(error)}`);
        onErrorRef.current?.(error);
        return false;
      } finally {
        fullSaveRef.current = false;
        setSaving(false);
      }
    },
    [api, pushToast],
  );

  /* ---------------------------- Row handlers ------------------------------ */

  const updateSellerRow = useCallback(
    (rowKey: string, patch: Partial<SellerInputRow>, options?: { autosave?: boolean }) => {
      const target = sellerRowsRef.current.find((row) => row.key === rowKey);
      if (target?.locked) return;

      setSellerRows((prev) =>
        prev.map((row) => (row.key === rowKey ? { ...row, ...patch } : row)),
      );
      if (options?.autosave !== false) scheduleSellerAutosave(rowKey);
    },
    [scheduleSellerAutosave],
  );

  const updateActualRow = useCallback((rowKey: string, patch: Partial<ActualInputRow>) => {
    setActualRows((prev) => prev.map((row) => (row.key === rowKey ? { ...row, ...patch } : row)));
    setActualDirty(true);
  }, []);

  const handlePickDoc = useCallback((rowKey: string) => {
    activeDocRowRef.current = rowKey;
    docInputRef.current?.click();
  }, []);

  const handleDocSelected = useCallback(
    async (event: ChangeEvent<HTMLInputElement>) => {
      const file = event.target.files?.[0];
      const rowKey = activeDocRowRef.current;
      event.target.value = '';
      if (!file || !rowKey) return;

      if (!uploadSupportingDoc) {
        updateActualRow(rowKey, { supporting_docs: file.name });
        pushToast('info', 'Uploader belum dikonfigurasi — nama file disimpan sebagai reference.');
        return;
      }

      try {
        setSaving(true);
        const reference = await uploadSupportingDoc(file);
        updateActualRow(rowKey, { supporting_docs: reference });
        pushToast('success', 'Dokumen pendukung berhasil diunggah.');
      } catch (error) {
        pushToast('error', `Gagal mengunggah dokumen: ${errorMessage(error)}`);
        onErrorRef.current?.(error);
      } finally {
        setSaving(false);
      }
    },
    [pushToast, updateActualRow, uploadSupportingDoc],
  );

  /* ----------------------------- Navigation ------------------------------- */

  const performNavigation = useCallback(
    async (target: 1 | 2 | 3 | 4) => {
      // Step 4 (Actual Input) memakai baris seller_input sebagai acuan, jadi
      // baris accrual harus tersimpan lebih dulu. Simpan hanya bila ada data /
      // perubahan tertunda — jangan memicu POST saat memang tidak ada apa pun.
      if (target > step && step === 3) {
        const hasPending = timersRef.current.size > 0;
        const hasInput = sellerRowsRef.current.some((row) => row.seller_input != null);
        if (hasPending || hasInput) {
          const ok = await saveSellerDraft({ silent: true });
          if (!ok) return;
        }
      }
      if (target > step && step === 4) {
        const issues = validateActualRows(actualRowsRef.current, false);
        if (issues.length > 0) {
          setShowActualErrors(true);
          pushToast('error', 'Perbaiki data sebelum submit.');
          return;
        }
      }
      setStep(target);
    },
    [pushToast, saveSellerDraft, step],
  );

  const requestNavigation = useCallback(
    (target: 1 | 2 | 3 | 4) => {
      if (target === step) return;
      if (stepDirty || saving) {
        setPendingNav(target);
        return;
      }
      void performNavigation(target);
    },
    [performNavigation, saving, step, stepDirty],
  );

  const discardChanges = useCallback(() => {
    clearTimers();
    setShowSellerErrors(false);
    setShowActualErrors(false);
    setActualDirty(false);
    if (step === 2) cancelMaxEdit();
    if (step === 3) {
      loaded.current.seller = false;
      void loadSellerInput();
    }
    if (step === 4) {
      loaded.current.actual = false;
      void loadActualInput();
    }
  }, [cancelMaxEdit, clearTimers, loadActualInput, loadSellerInput, step]);

  useEffect(() => {
    if (!stepDirty) return undefined;
    const handler = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = '';
      return '';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [stepDirty]);

  /* ------------------------------- Actions -------------------------------- */

  const handleNext = useCallback(() => {
    if (step === 3) {
      const issues = validateSellerRows(sellerRowsRef.current);
      if (issues.length > 0) {
        setShowSellerErrors(true);
        pushToast('error', `Terdapat ${issues.length} field yang perlu diperbaiki.`);
        return;
      }
    }
    requestNavigation((step + 1) as 1 | 2 | 3 | 4);
  }, [pushToast, requestNavigation, step]);

  const handleSaveSellerDraft = useCallback(() => {
    void saveSellerDraft();
  }, [saveSellerDraft]);

  const handleSaveActualDraft = useCallback(() => {
    const issues = validateActualRows(actualRowsRef.current, false);
    if (issues.length > 0) {
      setShowActualErrors(true);
      pushToast('error', `Terdapat ${issues.length} field yang perlu diperbaiki.`);
      return;
    }
    void persistActualRows('DRAFT');
  }, [persistActualRows, pushToast]);

  const handleSubmit = useCallback(async () => {
    const rows = actualRowsRef.current;
    const issues = validateActualRows(rows, true);
    if (issues.length > 0) {
      setShowActualErrors(true);
      pushToast('error', `Terdapat ${issues.length} field yang perlu diperbaiki.`);
      return;
    }
    const ok = await persistActualRows('SUBMITTED');
    if (ok) pushToast('success', 'BAK berhasil di-submit untuk approval finance.');
  }, [persistActualRows, pushToast]);

  const handleApproveAndLock = useCallback(async () => {
    const rows = actualRowsRef.current;
    const issues = validateActualRows(rows, true);
    if (issues.length > 0) {
      setShowActualErrors(true);
      pushToast('error', 'Lengkapi semua data sebelum approve & lock.');
      return;
    }

    setSaving(true);
    const approved: string[] = [];
    const failed: string[] = [];
    try {
      for (const row of rows) {
        if (row.status === 'LOCKED') continue;
        if (row.id == null) {
          // Baris belum pernah disimpan sehingga belum punya id di server.
          failed.push(`${row.role} (belum disimpan)`);
          continue;
        }
        try {
          await api.approveActualInput(row.id);
          approved.push(row.key);
        } catch (error) {
          failed.push(row.role);
          onErrorRef.current?.(error);
        }
      }
    } finally {
      setSaving(false);
    }

    if (approved.length > 0) {
      setActualRows((prev) =>
        prev.map((row) =>
          approved.includes(row.key) ? { ...row, status: 'LOCKED' as BAKStatus } : row,
        ),
      );
      setActualStatus(failed.length > 0 ? 'SUBMITTED' : 'LOCKED');
      setActualDirty(false);
      setLastSavedAt(new Date());
    }

    if (failed.length === 0 && approved.length > 0) {
      pushToast('success', 'Semua row berhasil di-approve dan dikunci.');
      onLocked?.(actualRowsRef.current.map((row) => ({ ...row, status: 'LOCKED' as BAKStatus })));
    } else if (failed.length > 0) {
      pushToast('error', `Gagal approve: ${failed.join(', ')}`);
    }
  }, [api, onLocked, pushToast]);

  /* ------------------------------- Render --------------------------------- */

  const progressPercent = ((step - 1) / (STEPS.length - 1)) * 100;

  return (
    <div className="bak-root">
      <style>{BAK_CSS}</style>

      {sessionExpired ? (
        <div className="bak-session" role="alert">
          <span className="bak-session__ico" aria-hidden="true">
            🔒
          </span>
          <div className="bak-session__body">
            <strong>Sesi login Anda sudah berakhir.</strong>
            <span>
              Data yang belum tersimpan tidak bisa dikirim ke server. Muat ulang halaman lalu login
              kembali — data absensi &amp; rate card yang sudah tersimpan tetap aman.
            </span>
          </div>
          <button
            type="button"
            className="bak-btn bak-btn--primary bak-btn--sm"
            onClick={() => window.location.reload()}
          >
            ↻ Muat ulang &amp; login
          </button>
        </div>
      ) : null}

      <div className="bak-header">
        <div>
          <h1 className="bak-h1">BAK - Berita Acara Kerja</h1>
          <p className="bak-muted">
            Step {step} dari {STEPS.length}: {STEPS[step - 1].title}
          </p>
        </div>
        <div className="bak-toolbar__actions">
          {step === 1 ? (
            <span className="bak-badge bak-badge--readonly">HASIL HITUNG WFM</span>
          ) : step === 2 ? (
            <span className="bak-badge bak-badge--readonly">RATE CARD</span>
          ) : (
            <StatusBadge status={step === 4 ? actualStatus : sellerStatus} />
          )}
        </div>
      </div>

      <nav className="bak-stepper" aria-label="BAK progress">
        {STEPS.map((item) => {
          const state = item.id === step ? 'active' : item.id < step ? 'done' : 'todo';
          return (
            <button
              key={item.id}
              type="button"
              className={`bak-step bak-step--${state}`}
              aria-current={item.id === step ? 'step' : undefined}
              onClick={() => requestNavigation(item.id)}
            >
              <span className="bak-step__index">{item.id < step ? '✓' : item.id}</span>
              <span className="bak-step__meta">
                <span className="bak-step__title">{item.title}</span>
                <span className="bak-step__sub">{item.subtitle}</span>
              </span>
            </button>
          );
        })}
      </nav>
      <div className="bak-progress" aria-hidden="true">
        <div className="bak-progress__bar" style={{ width: `${progressPercent}%` }} />
      </div>

      {/* ------------------------------ STEP 1 ------------------------------ */}
      <StepPanel
        visible={step === 1}
        loading={loading}
        loadingLabel="Memuat data WFM…"
        error={loadError}
        onRetry={() => void loadMaxPotential()}
      >
        {/* ================= 1A. UPLOAD ABSENSI ================= */}
        <section className="bak-att">
          <div className="bak-toolbar">
            <div>
              <div className="bak-h2-row">
                <span className="bak-sec-no">1A</span>
                <h2 className="bak-h2">Upload Absensi</h2>
                <span className="bak-badge-new">baru</span>
              </div>
              <p className="bak-muted">
                Berkas konfirmasi attendance WFM — dibaca kolom <b>D (Position)</b> dan{' '}
                <b>Q (Actual Attendance Code)</b>.
              </p>
            </div>
            <div className="bak-toolbar__actions">
              <label className="bak-field">
                <span>Periode</span>
                <input
                  type="month"
                  className="bak-input"
                  value={attPeriod}
                  aria-label="Periode absensi"
                  onChange={(event) => setAttPeriod(event.target.value)}
                  disabled={attBusy}
                />
              </label>
              <div className="bak-tabs" role="group" aria-label="Cara input absensi">
                <button
                  type="button"
                  className={attMode === 'file' ? 'is-on' : ''}
                  onClick={() => setAttMode('file')}
                >
                  File .xlsx
                </button>
                <button
                  type="button"
                  className={attMode === 'paste' ? 'is-on' : ''}
                  onClick={() => setAttMode('paste')}
                >
                  Paste dari Excel
                </button>
              </div>
            </div>
          </div>

          {attMode === 'file' ? (
            <div
              className={`bak-drop${attDragging ? ' is-over' : ''}`}
              onDragOver={(event) => {
                event.preventDefault();
                setAttDragging(true);
              }}
              onDragLeave={() => setAttDragging(false)}
              onDrop={(event) => {
                event.preventDefault();
                setAttDragging(false);
                const file = event.dataTransfer?.files?.[0];
                if (file) void uploadAttendanceFile(file);
              }}
              onClick={() => attFileRef.current?.click()}
              role="button"
              tabIndex={0}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') attFileRef.current?.click();
              }}
            >
              <div className="bak-drop__ico">⇪</div>
              <div className="bak-drop__t">Tarik berkas ke sini, atau klik untuk memilih</div>
              <div className="bak-drop__s">
                .xlsx / .xls / .csv · maks 8 MB · baris 1 boleh header · data mulai baris 2
              </div>
              <input
                ref={attFileRef}
                type="file"
                accept=".xlsx,.xls,.csv,.txt"
                hidden
                onChange={(event) => {
                  const file = event.target.files?.[0];
                  if (file) void uploadAttendanceFile(file);
                  event.target.value = '';
                }}
              />
            </div>
          ) : (
            <div>
              <textarea
                className="bak-paste"
                spellCheck={false}
                placeholder="Pilih sel lalu Ctrl+C di Excel, tempel di sini (Ctrl+V). Kolom D = Position, kolom Q = Actual Attendance Code."
                value={attText}
                onChange={(event) => setAttText(event.target.value)}
                disabled={attBusy}
              />
              <div className="bak-toolbar__actions" style={{ marginTop: 10 }}>
                <button
                  type="button"
                  className="bak-btn bak-btn--primary bak-btn--sm"
                  onClick={() => void uploadAttendanceText()}
                  disabled={attBusy}
                >
                  {attBusy ? <Spinner size={12} label="Memproses…" /> : 'Proses data'}
                </button>
                <button
                  type="button"
                  className="bak-btn bak-btn--ghost bak-btn--sm"
                  onClick={() => setAttText('')}
                  disabled={attBusy}
                >
                  Kosongkan
                </button>
              </div>
            </div>
          )}

          {attNotice ? <div className="bak-alert bak-alert--info">{attNotice}</div> : null}

          {attSummary?.uploaded ? (
            <>
              <div className="bak-filebar">
                <span className="bak-filebar__name">▤ {attSummary.file_name || 'absensi'}</span>
                <span className="bak-badge-ok">
                  {attSummary.total_rows.toLocaleString('id-ID')} baris
                </span>
                <span className="bak-filebar__meta">
                  {attSummary.total_people} orang · standard {attSummary.standard_days} hari
                </span>
                <button
                  type="button"
                  className="bak-btn bak-btn--ghost bak-btn--sm"
                  onClick={() => void removeAttendance()}
                  disabled={attBusy}
                >
                  Hapus
                </button>
              </div>

              <div className="bak-stats">
                <div className="bak-stat">
                  <div className="bak-stat__l">Baris</div>
                  <div className="bak-stat__v">{attSummary.total_rows.toLocaleString('id-ID')}</div>
                </div>
                <div className="bak-stat">
                  <div className="bak-stat__l">Orang</div>
                  <div className="bak-stat__v">{attSummary.total_people}</div>
                </div>
                <div className="bak-stat">
                  <div className="bak-stat__l">Dihitung</div>
                  <div className="bak-stat__v">{attSummary.totals.billable ?? 0}</div>
                </div>
                <div className="bak-stat">
                  <div className="bak-stat__l">Dikecualikan</div>
                  <div className="bak-stat__v">{attSummary.totals.excluded ?? 0}</div>
                </div>
                <div className="bak-stat">
                  <div className="bak-stat__l">Hari kerja</div>
                  <div className="bak-stat__v">{attSummary.standard_days}</div>
                </div>
                <div className="bak-stat">
                  <div className="bak-stat__l">H</div>
                  <div className="bak-stat__v">{attSummary.totals.h.toLocaleString('id-ID')}</div>
                </div>
                <div className="bak-stat">
                  <div className="bak-stat__l">CT</div>
                  <div className="bak-stat__v">{attSummary.totals.ct.toLocaleString('id-ID')}</div>
                </div>
                <div className="bak-stat">
                  <div className="bak-stat__l">Day Off</div>
                  <div className="bak-stat__v">{attSummary.totals.off.toLocaleString('id-ID')}</div>
                </div>
              </div>

              {attSummary.missing_in_rate.length ? (
                <div className="bak-alert bak-alert--warn">
                  <b>{attSummary.missing_in_rate.length} posisi belum punya harga</b> —{' '}
                  {attSummary.missing_in_rate.join(', ')}. Tambahkan lewat bagian 1B.
                </div>
              ) : null}
              {attSummary.absent_in_data.length ? (
                <div className="bak-alert bak-alert--info">
                  {attSummary.absent_in_data.length} posisi di rate card tidak ada di absensi periode ini —{' '}
                  quantity-nya akan menjadi 0 saat sinkron: {attSummary.absent_in_data.join(', ')}.
                </div>
              ) : null}
            </>
          ) : (
            <div className="bak-muted" style={{ marginTop: 10 }}>
              Belum ada absensi untuk periode {attPeriod}. Unggah berkas atau tempel data dari Excel.
            </div>
          )}
        </section>

        {/* ================= 1B. REKAP PER ORANG ================= */}
        <section className={`bak-att${attSummary?.uploaded ? '' : ' is-locked'}`}>
          <div className="bak-toolbar">
            <div>
              <div className="bak-h2-row">
                <span className="bak-sec-no">1B</span>
                <h2 className="bak-h2">Rekap per Orang</h2>
                <span className="bak-badge-new">review awal</span>
              </div>
              <p className="bak-muted">
                <b>Rate = Working days &divide; jumlah hari bulan</b> ({attSummary?.days_in_month ?? 0}{' '}
                hari). Sebulan penuh selalu <b>1,00</b> berapa pun hari Off-nya. Yang bisa memotong
                rate hanya <b>tanggal resign</b> di Remarks &mdash; mis. &ldquo;Resign per 15 Oct
                2026&rdquo; menjadi 15 &divide; {attSummary?.days_in_month ?? 0} = 0,48. Kolom{' '}
                <b>Working days</b> bisa dikoreksi manual (tombol &#8634; untuk kembali ke nilai
                otomatis), dan hari kerja dari Excel tetap tampil sebagai tooltip untuk audit.
                Absen/alpha tidak memotong rate &mdash; muncul sebagai <b>variance</b> di Accrual
                Input. Yang <b>tidak dihitung</b> ke tahap berikutnya hanya baris dengan Remarks
                mengandung <b>Non Billable</b> atau <b>Buffer</b>; Remarks lain &mdash; termasuk
                &ldquo;Billable: Resign per &hellip;&rdquo; dan &ldquo;Resign per &hellip;&rdquo;
                saja &mdash; tetap dihitung.
              </p>
            </div>
            <div className="bak-toolbar__actions">
              {attSummary?.uploaded ? (
                <span className="bak-badge-ok">
                  {attSummary.employees?.length ?? 0} orang &middot; {attSummary.days_in_month} hari kalender
                  {attSummary.totals.excluded > 0 ? (
                    <>
                      {' '}&middot; <b>{attSummary.totals.billable} dihitung</b>
                      {' '}&middot; {attSummary.totals.excluded} dikecualikan
                    </>
                  ) : null}
                </span>
              ) : null}
            </div>
          </div>

          <div className="bak-table-wrap bak-recap-wrap">
            <table className="bak-table bak-recap">
              <thead>
                <tr>
                  <th scope="col" className="bak-recap__no">
                    No.
                  </th>
                  <th scope="col">NIK</th>
                  <th scope="col" className="bak-recap__name">
                    Nama
                  </th>
                  <th scope="col">Posisi</th>
                  <th scope="col">Remarks</th>
                  <th scope="col" className="bak-num">
                    Working days
                  </th>
                  <th scope="col" className="bak-num">
                    Rate
                  </th>
                  <th scope="col" className="bak-num">
                    Unit Price
                  </th>
                  <th scope="col" className="bak-num">
                    Counting Price
                  </th>
                </tr>
              </thead>
              <tbody>
                {attSummary?.uploaded && attSummary.employees?.length ? (
                  attSummary.employees.map((row, index) => (
                    <tr
                      key={`${row.position}-${row.name}-${index}`}
                      className={row.billable ? undefined : 'bak-recap__row--excluded'}
                      title={row.billable ? undefined : 'Remarks Non Billable / Buffer — tidak dihitung ke tahap berikutnya'}
                    >
                      <td data-label="No." className="bak-recap__no">
                        {index + 1}
                      </td>
                      <td data-label="NIK">{row.employee_id || '\u2013'}</td>
                      <td data-label="Nama" className="bak-recap__name">
                        {row.name}
                      </td>
                      <td data-label="Posisi">{row.position}</td>
                      <td data-label="Remarks" className="bak-recap__remarks">
                        {row.billable ? (
                          row.remarks || '\u2013'
                        ) : (
                          <>
                            {row.remarks || '\u2013'} <span className="bak-skip">tidak dihitung</span>
                          </>
                        )}
                      </td>
                      <td data-label="Working days" className="bak-num">
                        <span className="bak-days-box">
                          <input
                            key={`${row.position}-${row.name}-${row.active_days}`}
                            type="text"
                            inputMode="decimal"
                            defaultValue={row.active_days}
                            aria-label={`Working days ${row.name}`}
                            title={[
                              row.resign_date
                                ? `Resign ${row.resign_date} — hari aktif otomatis`
                                : `Sebulan penuh (${attSummary.days_in_month} hari aktif)`,
                              `Excel: ${row.days_auto} hari kerja`,
                              row.active_manual ? `Manual — otomatis ${row.active_auto}` : '',
                            ]
                              .filter(Boolean)
                              .join(' · ')}
                            disabled={attBusy}
                            onBlur={(event) => {
                              const value = event.target.value.trim();
                              const current = row.active_days;
                              if (value === '' || Number(value.replace(',', '.')) === current) {
                                event.target.value = String(current);
                                return;
                              }
                              void saveAttendanceActive(row.position, row.name, value);
                            }}
                            onKeyDown={(event) => {
                              if (event.key === 'Enter') event.currentTarget.blur();
                              if (event.key === 'Escape') {
                                event.currentTarget.value = String(row.active_days);
                                event.currentTarget.blur();
                              }
                            }}
                          />
                          <span className="bak-days-unit">/{attSummary.days_in_month}</span>
                          {row.active_manual ? (
                            <button
                              type="button"
                              className="bak-days-reset"
                              aria-label={`Kembalikan working days ${row.name} ke nilai otomatis`}
                              title={`Manual — otomatis ${row.active_auto}. Klik untuk mengembalikan.`}
                              disabled={attBusy}
                              onClick={() => void saveAttendanceActive(row.position, row.name, '')}
                            >
                              ↺
                            </button>
                          ) : null}
                        </span>
                        {row.resign_date ? (
                          <span className="bak-cat">resign {row.resign_date}</span>
                        ) : null}
                      </td>
                      <td data-label="Rate" className="bak-num">
                        <span className={`bak-rate${row.rate >= 1 ? ' is-full' : ' is-partial'}`}>
                          {formatRate(row.rate)}
                        </span>
                      </td>
                      <td data-label="Unit Price" className="bak-num">
                        {formatCurrency(row.price) || '0'}
                      </td>
                      <td data-label="Counting Price" className="bak-num">
                        {row.billable ? (
                          <strong>{formatCurrency(row.counted) || '0'}</strong>
                        ) : (
                          <span className="bak-muted">{'\u2013'}</span>
                        )}
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={recapCols} className="bak-muted">
                      {attSummary?.uploaded
                        ? 'Tidak ada data orang pada berkas.'
                        : 'Upload absensi dulu untuk melihat rekap ini.'}
                    </td>
                  </tr>
                )}
              </tbody>
              {attSummary?.uploaded && attSummary.employees?.length ? (
                <tfoot>
                  <tr>
                    <td colSpan={5}>
                      Total &middot; {attSummary.totals.billable ?? attSummary.employees.length} orang dihitung
                      {attSummary.totals.excluded > 0
                        ? ` (${attSummary.totals.excluded} dikecualikan)`
                        : ''}
                    </td>
                    <td className="bak-num">
                      {formatQuantity(
                        attSummary.employees.reduce(
                          (sum, item) => sum + (item.billable ? item.active_days : 0),
                          0,
                        ),
                      )}
                    </td>
                    <td className="bak-num">{formatQuantity(attSummary.totals.quantity)}</td>
                    <td className="bak-num" />
                    <td className="bak-num">
                      <strong>{formatCurrency(attSummary.totals.counted) || '0'}</strong>
                    </td>
                  </tr>
                </tfoot>
              ) : null}
            </table>
          </div>
        </section>

        {/* ================= 1C. HARGA & RINGKASAN PER POSISI ================= */}
        <section className={`bak-att${attSummary?.uploaded ? '' : ' is-locked'}`}>
          <div className="bak-toolbar">
            <div>
              <div className="bak-h2-row">
                <span className="bak-sec-no">1C</span>
                <h2 className="bak-h2">Harga &amp; Ringkasan per Posisi</h2>
                <span className="bak-badge-new">baru</span>
              </div>
              <p className="bak-muted">
                Sum Rate = jumlah rate dari rekap per orang. Subtotal = Harga &times; Sum Rate.
                Masuk penuh (rate 1,00) dihitung 1; kurang dari penuh dihitung sesuai porsi.
                Rate &amp; Sum Rate dibulatkan <b>ROUND 2 desimal</b>, dan angka hasil pembulatan
                itu yang dipakai untuk menghitung uang.
              </p>
            </div>
            <div className="bak-toolbar__actions">
              {attSummary?.missing_in_rate?.length ? (
                <button
                  type="button"
                  className="bak-btn bak-btn--primary bak-btn--sm"
                  onClick={() => void addMissingPositions()}
                  disabled={attBusy}
                >
                  Tambah {attSummary.missing_in_rate.length} posisi baru
                </button>
              ) : null}
            </div>
          </div>

          <div className="bak-table-wrap">
            <table className="bak-table">
              <thead>
                <tr>
                  <th scope="col">Posisi (D)</th>
                  <th scope="col" className="bak-num">Orang</th>
                  <th scope="col" className="bak-num">Sum Rate</th>
                  <th scope="col" className="bak-num">Harga / Posisi</th>
                  <th scope="col" className="bak-num">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                {attSummary?.uploaded && attSummary.positions.length ? (
                  attSummary.positions.map((row) => (
                    <tr key={row.position} className={row.known ? '' : 'bak-row-new'}>
                      <td data-label="Posisi">
                        <span>{row.position}</span>
                        {row.known ? null : <span className="bak-cat">belum ada harga</span>}
                      </td>
                      <td data-label="Orang" className="bak-num">
                        {row.head}
                        <span className="bak-cat">orang unik</span>
                      </td>
                      <td data-label="Sum Rate" className="bak-num">
                        <strong>{formatQuantity(row.quantity)}</strong>
                        {row.partial ? <span className="bak-cat">{row.partial} prorata</span> : null}
                      </td>
                      <td data-label="Harga / Posisi" className="bak-num">
                        <span className="bak-price-box">
                          <span>Rp</span>
                          <input
                            key={`${row.position}-${row.price}`}
                            type="text"
                            inputMode="numeric"
                            defaultValue={row.price ? row.price.toLocaleString('id-ID') : ''}
                            aria-label={`Harga per posisi ${row.position}`}
                            disabled={attBusy}
                            onBlur={(event) => {
                              const digits = event.target.value.replace(/[^\d]/g, '');
                              const value = digits ? Number(digits) : 0;
                              // Rapikan tampilan (input tidak dikendalikan React).
                              event.target.value = value ? value.toLocaleString('id-ID') : '';
                              void saveAttendancePrice(row.position, digits);
                            }}
                            onKeyDown={(event) => {
                              if (event.key === 'Enter') event.currentTarget.blur();
                            }}
                          />
                        </span>
                      </td>
                      <td data-label="Subtotal" className="bak-num">
                        <strong>{formatCurrency(row.subtotal) || '0'}</strong>
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan={5} className="bak-muted">
                      {attSummary?.uploaded
                        ? 'Tidak ada posisi pada berkas.'
                        : 'Upload absensi dulu untuk mengisi tabel ini.'}
                    </td>
                  </tr>
                )}
              </tbody>
              {attSummary?.uploaded && attSummary.positions.length ? (
                <tfoot>
                  <tr>
                    <td>Total</td>
                    <td className="bak-num">{attSummary.totals.people}</td>
                    <td className="bak-num">{formatQuantity(attSummary.totals.quantity)}</td>
                    <td className="bak-num" />
                    <td className="bak-num">{formatCurrency(attSummary.totals.subtotal) || '0'}</td>
                  </tr>
                </tfoot>
              ) : null}
            </table>
          </div>

          <div className="bak-total" style={{ marginTop: 12 }}>
            <span>Grand Total Absensi</span>
            <span className="bak-total__value">
              Rp {formatCurrency(attSummary?.totals.subtotal ?? 0) || '0'}
            </span>
          </div>

          <div className="bak-footer">
            <div className="bak-footer__left">
              {attSummary?.uploaded
                ? 'Klik Sinkron untuk mengisi Price/Unit & Quantity di Max Potential Earnings.'
                : 'Sinkron tersedia setelah absensi diunggah.'}
            </div>
            <button
              type="button"
              className="bak-btn bak-btn--primary"
              onClick={() => void syncAttendance()}
              disabled={attBusy || !attSummary?.uploaded}
            >
              {attBusy ? <Spinner size={12} label="Memproses…" /> : '⇄ Sinkron ke Max Potential'}
            </button>
          </div>

          {attPeriods.length ? (
            <div className="bak-history">
              <div className="bak-history__t">Riwayat upload per bulan</div>
              {attPeriods.map((item) => (
                <div
                  key={item.period}
                  className={`bak-history__row${item.period === attPeriod ? ' is-now' : ''}`}
                >
                  <span className="bak-history__p">{item.period}</span>
                  <span className="bak-history__m">
                    {item.total_rows.toLocaleString('id-ID')} baris · {item.total_people} orang · standard{' '}
                    {item.standard_days} hari
                  </span>
                  <span className="bak-history__tag">
                    {item.period === attPeriod ? (
                      <span className="bak-badge-ok">sedang dipakai</span>
                    ) : (
                      <button
                        type="button"
                        className="bak-btn bak-btn--ghost bak-btn--sm"
                        onClick={() => setAttPeriod(item.period)}
                        disabled={attBusy}
                      >
                        Buka
                      </button>
                    )}
                  </span>
                </div>
              ))}
            </div>
          ) : null}
        </section>

        <div className="bak-footer">
          <div className="bak-footer__left">
            {attSummary?.uploaded
              ? 'Lanjut ke Max Potential Earnings untuk melihat rate card hasil hitung ini.'
              : 'Upload absensi dulu agar rekap per orang dan harga posisi terisi.'}
          </div>
          <button
            type="button"
            className="bak-btn bak-btn--primary"
            onClick={() => requestNavigation(2)}
            disabled={attBusy}
          >
            Next →
          </button>
        </div>
      </StepPanel>

      {/* ------------------------------ STEP 2 ------------------------------ */}
      <StepPanel
        visible={step === 2}
        loading={loading}
        loadingLabel="Memuat max potential earnings…"
        error={loadError}
        onRetry={() => void loadMaxPotential()}
      >
        {/* ================= 1D. MAX POTENTIAL ================= */}
        <div className="bak-toolbar">
          <div>
            <div className="bak-h2-row">
              <span className="bak-sec-no">1D</span>
              <h2 className="bak-h2">Max Potential Earnings</h2>
              <InfoTip
                text="Max Potential Earning = Revenue Forecasting"
                label="Informasi Max Potential Earnings"
              />
            </div>            <p className="bak-muted">
              {editingMax
                ? 'Rate WFM terkunci. Max Potential Rate × Price/Unit = Max Potential (Manual) — isi salah satu, yang lain mengikuti. Role juga bisa dihapus.'
                : 'Perbandingan Rate & nominal hasil hitung WFM dengan Max Potential (manual). Selisih = WFM − Max Potential, jadi minus berarti hasil hitung WFM kurang dari Max Potential (mis. 53,7 dari 54 karena ada yang tidak masuk penuh).'}
            </p>
          </div>
          <div className="bak-toolbar__actions">
            {editingMax ? (
              <button
                type="button"
                className="bak-btn bak-btn--ghost bak-btn--sm"
                onClick={addMaxRole}
                disabled={saving}
              >
                ＋ Tambah Role
              </button>
            ) : null}
            {editingMax ? (
              maxDirty ? (
                <span className="bak-unsaved">Ada perubahan yang belum disimpan.</span>
              ) : (
                <span className="bak-saved">Mode edit aktif</span>
              )
            ) : (
              <>
                <button
                  type="button"
                  className="bak-btn bak-btn--ghost bak-btn--sm"
                  onClick={() => void loadMaxPotential()}
                  disabled={loading}
                >
                  ↻ Muat ulang
                </button>
                <button
                  type="button"
                  className="bak-btn bak-btn--primary bak-btn--sm"
                  onClick={() => setEditingMax(true)}
                  disabled={loading || !maxVisibleItems.length}
                >
                  ✎ Edit
                </button>
              </>
            )}
          </div>
        </div>

        <div className="bak-table-wrap">
          <table className="bak-table">
            <thead>
              <tr>
                <th scope="col">Role</th>
                <th scope="col">Billing Type</th>
                <th scope="col" className="bak-num">
                  Rate WFM Calculation
                </th>
                <th scope="col" className="bak-num">
                  Max Potential Rate
                </th>
                <th scope="col" className="bak-num">
                  Price/Unit
                </th>
                <th scope="col" className="bak-num">
                  Max Potential (WFM)
                </th>
                <th scope="col" className="bak-num">
                  Max Potential (Manual)
                </th>
                <th scope="col" className="bak-num">
                  Selisih
                </th>
                <th scope="col">Variance Reason</th>
                {editingMax ? (
                  <th scope="col" className="bak-act-col">
                    Aksi
                  </th>
                ) : null}
              </tr>
            </thead>
            <tbody>
              {maxVisibleItems.length ? (
                maxVisibleItems.map((row) => {
                  const isNew = typeof row.id !== 'number';
                  const manual = row.manual_amount;
                  // Selisih = WFM − Max Potential: minus berarti hasil WFM kurang.
                  const diff = manual == null ? null : round2(row.subtotal - manual);
                  return (
                    <tr
                      key={String(row.id)}
                      className={isNew || row.is_custom ? 'bak-row-new' : ''}
                    >
                      <td data-label="Role">
                        {editingMax && isNew ? (
                          <input
                            type="text"
                            className="bak-input bak-role-input"
                            aria-label="Nama role baru"
                            placeholder="Nama role baru"
                            value={row.role}
                            onChange={(event) => updateMaxRow(row.id, { role: event.target.value })}
                          />
                        ) : (
                          <>
                            <span>{row.role}</span>
                            {row.category ? <span className="bak-cat">{row.category}</span> : null}
                          </>
                        )}
                      </td>
                      <td data-label="Billing Type">
                        {editingMax ? (
                          <select
                            className="bak-select"
                            aria-label={`Billing type rate card untuk ${row.role}`}
                            value={row.billing_type ?? 'Hours'}
                            onChange={(event) =>
                              updateMaxRow(row.id, { billing_type: event.target.value as BillingType })
                            }
                          >
                            {BILLING_TYPES.map((type) => (
                              <option key={type} value={type}>
                                {type}
                              </option>
                            ))}
                          </select>
                        ) : (
                          <span className="bak-cat">{row.billing_type ?? 'Hours'}</span>
                        )}
                      </td>
                      <td data-label="Rate WFM Calculation" className="bak-num">
                        <span
                          className={`bak-locked-rate${row.quantity ? '' : ' bak-zero'}`}
                          title="Dari WFM Calculation — tidak bisa diubah di sini"
                        >
                          {formatQuantity(row.quantity)}
                          <span aria-hidden="true">🔒</span>
                        </span>
                      </td>
                      <td data-label="Max Potential Rate" className="bak-num">
                        {editingMax ? (
                          <QuantityInput
                            ariaLabel={`Max potential rate untuk ${row.role}`}
                            value={row.manual_rate ?? null}
                            onChange={(value) => updateMaxRow(row.id, { manual_rate: value })}
                          />
                        ) : row.manual_rate == null ? (
                          <span className="bak-zero">0</span>
                        ) : (
                          <strong>{formatQuantity(row.manual_rate)}</strong>
                        )}
                      </td>
                      <td data-label="Price/Unit" className="bak-num">
                        {editingMax ? (
                          <CurrencyInput
                            ariaLabel={`Price per unit rate card untuk ${row.role}`}
                            value={row.price_per_unit}
                            onChange={(value) => updateMaxRow(row.id, { price_per_unit: value ?? 0 })}
                          />
                        ) : (
                          formatCurrency(row.price_per_unit) || '0'
                        )}
                      </td>
                      <td data-label="Max Potential (WFM)" className="bak-num">
                        {row.subtotal ? (
                          formatCurrency(row.subtotal)
                        ) : (
                          <span className="bak-zero">0</span>
                        )}
                      </td>
                      <td data-label="Max Potential (Manual)" className="bak-num">
                        {editingMax ? (
                          <CurrencyInput
                            ariaLabel={`Max potential manual untuk ${row.role}`}
                            value={manual ?? null}
                            placeholder="0"
                            onChange={(value) => updateMaxRow(row.id, { manual_amount: value })}
                          />
                        ) : manual == null ? (
                          <span className="bak-zero">0</span>
                        ) : (
                          <strong>{formatCurrency(manual)}</strong>
                        )}
                      </td>
                      <td data-label="Selisih" className="bak-num">
                        {diff == null ? (
                          <span className="bak-zero">–</span>
                        ) : (
                          <span
                            className={`bak-diff${
                              Math.abs(diff) < 0.005
                                ? ' is-zero'
                                : diff > 0
                                  ? ' is-up'
                                  : ' is-down'
                            }`}
                          >
                            {formatSignedCurrency(diff)}
                          </span>
                        )}
                      </td>
                      <td data-label="Variance Reason" className="bak-var-reason">
                        {diff == null || Math.abs(diff) < 0.005 ? (
                          <span className="bak-zero">–</span>
                        ) : (
                          (() => {
                            const line = varianceNarrative?.lines.find(
                              (item) => item.role === row.role,
                            );
                            if (!line) {
                              return (
                                <span className={diff < 0 ? 'bak-var-down' : 'bak-var-up'}>
                                  {formatSignedRupiah(diff)}
                                </span>
                              );
                            }
                            return (
                              <span
                                className={diff < 0 ? 'bak-var-down' : 'bak-var-up'}
                                title={line.text}
                              >
                                {line.direction} · {formatSignedRupiah(diff)}
                              </span>
                            );
                          })()
                        )}
                      </td>
                      {editingMax ? (
                        <td data-label="Aksi" className="bak-act-col">
                          <button
                            type="button"
                            className="bak-del-btn"
                            aria-label={`Hapus role ${row.role}`}
                            title="Hapus role ini"
                            onClick={() => removeMaxRole(row)}
                          >
                            🗑
                          </button>
                        </td>
                      ) : null}
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={editingMax ? 10 : 9} className="bak-muted">
                    {loading ? 'Memuat…' : 'Belum ada data max potential earnings.'}
                  </td>
                </tr>
              )}
            </tbody>
            {maxVisibleItems.length ? (
              <tfoot>
                <tr>
                  <td colSpan={2}>Total</td>
                  <td className="bak-num">{formatQuantity(maxSummary.wfmRate)}</td>
                  <td className="bak-num">{formatQuantity(maxSummary.manualRate)}</td>
                  <td className="bak-num" />
                  <td className="bak-num">
                    <strong>{formatCurrency(maxSummary.wfmTotal) || '0'}</strong>
                  </td>
                  <td className="bak-num">
                    <strong>{formatCurrency(maxSummary.usedTotal) || '0'}</strong>
                  </td>
                  <td className="bak-num">
                    <span
                      className={`bak-diff${
                        Math.abs(maxSummary.selisih) < 0.005
                          ? ' is-zero'
                          : maxSummary.selisih > 0
                            ? ' is-up'
                            : ' is-down'
                      }`}
                    >
                      {formatSignedCurrency(maxSummary.selisih)}
                    </span>
                  </td>
                  <td>
                    {Math.abs(maxSummary.selisih) < 0.005 ? (
                      <span className="bak-zero">–</span>
                    ) : (
                      <span
                        className={maxSummary.selisih < 0 ? 'bak-var-down' : 'bak-var-up'}
                      >
                        {varianceNarrative
                          ? `${varianceNarrative.lines.length} role · ${
                              maxSummary.selisih < 0 ? 'deficit' : 'surplus'
                            } ${formatSignedRupiah(maxSummary.selisih)}`
                          : formatSignedRupiah(maxSummary.selisih)}
                      </span>
                    )}
                  </td>
                  {editingMax ? <td className="bak-act-col" /> : null}
                </tr>
              </tfoot>
            ) : null}
          </table>
        </div>

        {varianceNarrative ? (
          <div className="bak-narrative">
            <div className="bak-narrative__t">{varianceNarrative.heading}</div>
            <ul className="bak-narrative__list">
              {varianceNarrative.lines.map((line) => (
                <li key={line.role} className={line.amount < 0 ? 'is-down' : 'is-up'}>
                  {line.text}
                </li>
              ))}
            </ul>
            <div className="bak-narrative__total">
              Total selisih Max Potential: <b>{formatSignedRupiah(varianceNarrative.total)}</b>
            </div>
          </div>
        ) : null}

        {zeroRateRoles.length ? (
          <p className="bak-muted" style={{ marginTop: 8 }}>
            {zeroRateRoles.length} role tidak ada di berkas WFM periode {attPeriod} sehingga Rate-nya
            0: {zeroRateRoles.join(', ')}.
          </p>
        ) : null}

        {maxSummary.categories.length > 0 ? (
          <div className="bak-chips">
            {maxSummary.categories.map((category) => (
              <span key={category.category} className="bak-chip">
                {category.category}
                <strong>{formatCurrency(category.subtotal) || '0'}</strong>
              </span>
            ))}
          </div>
        ) : null}

        <div className="bak-total">
          <span>
            Max Potential dipakai (manual bila diisi)
          </span>
          <span className="bak-total__value">Rp {formatCurrency(maxSummary.grandTotal) || '0'}</span>
        </div>

        <div className="bak-compare">
          <div className="bak-compare__i">
            <span>Hasil hitung WFM</span>
            <strong>Rp {formatCurrency(maxSummary.wfmTotal) || '0'}</strong>
          </div>
          <div className="bak-compare__i">
            <span>Max Potential dipakai</span>
            <strong>Rp {formatCurrency(maxSummary.usedTotal) || '0'}</strong>
          </div>
          <div className="bak-compare__i">
            <span>Selisih (WFM − Max Potential)</span>
            <strong
              className={
                Math.abs(maxSummary.selisih) < 0.005
                  ? ''
                  : maxSummary.selisih > 0
                    ? 'is-up'
                    : 'is-down'
              }
            >
              {formatSignedCurrency(maxSummary.selisih)}
            </strong>
          </div>
        </div>

        <div className="bak-footer">
          <div className="bak-footer__left">
            {editingMax
              ? maxDirty
                ? 'Ada perubahan yang belum disimpan.'
                : 'Tidak ada perubahan.'
              : 'Klik Edit untuk mengubah Price/Unit, Max Potential Rate, Max Potential manual, menghapus role, atau menambah role.'}
          </div>
          {editingMax ? (
            <>
              <button
                type="button"
                className="bak-btn bak-btn--ghost"
                onClick={cancelMaxEdit}
                disabled={saving}
              >
                Batal
              </button>
              <button
                type="button"
                className="bak-btn bak-btn--primary"
                onClick={() => void saveMaxPotential()}
                disabled={saving}
              >
                {saving ? <Spinner size={12} label="Menyimpan…" /> : 'Simpan'}
              </button>
            </>
          ) : (
            <>
              <button
                type="button"
                className="bak-btn bak-btn--ghost"
                onClick={() => requestNavigation(1)}
                disabled={loading}
              >
                ← Back
              </button>
              <button
                type="button"
                className="bak-btn bak-btn--primary"
                onClick={() => requestNavigation(3)}
                disabled={loading}
              >
                Next →
              </button>
            </>
          )}
        </div>
      </StepPanel>

      {/* ------------------------------ STEP 3 ------------------------------ */}
      <StepPanel
        visible={step === 3}
        loading={loading}
        loadingLabel="Memuat seller input…"
        error={loadError}
        onRetry={() => void loadSellerInput()}
      >
        <div className="bak-toolbar">
          <div>
            <h2 className="bak-h2">Accrual Input</h2>
            <p className="bak-muted">
              Otomatis tersinkron dengan Max Potential Earnings. Auto-save aktif{' '}
              {AUTOSAVE_DEBOUNCE_MS / 1000} detik setelah input berubah. Variance di luar ±
              {SELLER_WARN_PCT}% ditandai merah dan wajib memiliki alasan. Tombol{' '}
              <b>↻ Refresh</b> menghapus isian manual dan mengembalikannya ke nilai Max Potential.
            </p>
          </div>
          <div className="bak-toolbar__actions">
            <button
              type="button"
              className="bak-btn bak-btn--ghost bak-btn--sm"
              onClick={() => setResetTarget('seller')}
              disabled={resetBusy || !sellerRows.length}
              title="Hapus isian manual, kembalikan ke nilai otomatis dari Max Potential Earnings"
            >
              ↻ Refresh
            </button>
            {isDirty ? (
              <span className="bak-unsaved">
                <Spinner size={12} /> Menyimpan…
              </span>
            ) : lastSavedAt ? (
              <span className="bak-saved">✓ Tersimpan {formatClock(lastSavedAt)}</span>
            ) : null}
          </div>
        </div>

        {showSellerErrors && sellerIssues.length > 0 ? (
          <div className="bak-banner bak-banner--error" role="alert">
            <span>{sellerIssues.length} field perlu diperbaiki sebelum lanjut.</span>
            <button
              type="button"
              className="bak-btn bak-btn--ghost bak-btn--sm"
              onClick={() => setShowSellerErrors(false)}
            >
              Tutup
            </button>
          </div>
        ) : null}

        <div className="bak-table-wrap">
          <table className="bak-table">
            <thead>
              <tr>
                <th scope="col">Role</th>
                <th scope="col">Billing Type</th>
                <th scope="col" className="bak-num">
                  Max Potential
                </th>
                <th scope="col" className="bak-num">
                  Quantity (Sum Rate)
                </th>
                <th scope="col" className="bak-num">
                  Price/Unit
                </th>
                <th scope="col" className="bak-num">
                  Accrual Input
                </th>
                <th scope="col" className="bak-num">
                  Variance
                </th>
                <th scope="col">Variance Reason (Auto)</th>
                <th scope="col">Variance Reason (Manual)</th>
                <th scope="col">Notes</th>
              </tr>
            </thead>
            <tbody>
              {sellerRows.length ? (
                sellerRows.map((row) => {
                  const rowIssues = showSellerErrors ? sellerIssuesById[row.key] ?? {} : {};
                  const autoReason = autoReasonForSeller(row);
                  const { variance, variancePct } = computeVariance(row.max_potential, row.seller_input);
                  const outOfRange = Math.abs(variancePct) > SELLER_WARN_PCT;
                  const tone =
                    row.seller_input == null
                      ? 'neutral'
                      : varianceTone(variance, variancePct, 0, SELLER_WARN_PCT);
                  return (
                    <tr
                      key={row.key}
                      className={
                        row.seller_input == null
                          ? undefined
                          : tone === 'danger'
                            ? 'bak-row--danger'
                            : outOfRange
                              ? 'bak-row--danger'
                              : undefined
                      }
                    >
                      <td data-label="Role">
                        <span>{row.role}</span>
                        {row.category ? <span className="bak-cat">{row.category}</span> : null}
                        {row.locked ? (
                          <span className="bak-cat">
                            <StatusBadge status="LOCKED" />
                          </span>
                        ) : null}
                      </td>
                      <td data-label="Billing Type">
                        <span className="bak-cat">{row.billing_type ?? 'Hours'}</span>
                      </td>
                      <td data-label="Max Potential">
                        <ReadOnlyCurrency
                          value={row.max_potential}
                          ariaLabel={`Max potential untuk ${row.role}`}
                        />
                      </td>
                      <td data-label="Quantity">
                        <QuantityInput
                          ariaLabel={`Quantity untuk ${row.role}`}
                          value={row.quantity ?? null}
                          disabled={row.locked}
                          invalid={Boolean(rowIssues.quantity)}
                          onChange={(value) =>
                            updateSellerRow(row.key, {
                              quantity: value,
                              seller_input:
                                value != null
                                  ? computeAmount(
                                      row.sales_price_per_unit ?? row.price_per_unit,
                                      value,
                                    ) ?? row.seller_input
                                  : row.seller_input,
                            })
                          }
                        />
                        {rowIssues.quantity ? (
                          <span className="bak-err">{rowIssues.quantity}</span>
                        ) : null}
                      </td>
                      <td data-label="Price/Unit">
                        <CurrencyInput
                          ariaLabel={`Price per unit untuk ${row.role}`}
                          value={row.sales_price_per_unit ?? null}
                          disabled={row.locked}
                          invalid={Boolean(rowIssues.price_per_unit)}
                          onChange={(value) =>
                            updateSellerRow(row.key, {
                              sales_price_per_unit: value,
                              seller_input:
                                value != null
                                  ? computeAmount(value, row.quantity) ?? row.seller_input
                                  : row.seller_input,
                            })
                          }
                        />
                        {rowIssues.price_per_unit ? (
                          <span className="bak-err">{rowIssues.price_per_unit}</span>
                        ) : null}
                      </td>
                      <td data-label="Accrual Input">
                        <CurrencyInput
                          ariaLabel={`Accrual input untuk ${row.role}`}
                          value={row.seller_input}
                          disabled={row.locked}
                          invalid={Boolean(rowIssues.seller_input)}
                          onChange={(value) => updateSellerRow(row.key, { seller_input: value })}
                        />
                        {rowIssues.seller_input ? (
                          <span className="bak-err">{rowIssues.seller_input}</span>
                        ) : null}
                      </td>
                      <td data-label="Variance" className="bak-num">
                        <span className={`bak-variance bak-variance--${tone}`}>
                          <span>{row.seller_input == null ? '-' : formatVariance(variance)}</span>
                          <span className="bak-variance__pct">
                            {row.seller_input == null ? '-' : formatPercent(variancePct)}
                          </span>
                        </span>
                      </td>
                      <td data-label="Variance Reason (Auto)">
                        {(() => {
                          const shortPos = shortfallByPosition.get(row.role);
                          const standard = attSummary?.standard_days ?? 0;
                          const short = shortPos ? shortfallReason(shortPos, standard) : null;
                          if (autoReason) {
                            return <span className="bak-auto-reason">{autoReason.label}</span>;
                          }
                          if (short) {
                            return <span className="bak-auto-reason">{short.label}</span>;
                          }
                          return <span className="bak-balanced">Balance</span>;
                        })()}
                        <ShortfallNote
                          shortfalls={shortfallByPosition.get(row.role)?.shortfalls ?? []}
                          standardDays={attSummary?.standard_days ?? 0}
                        />
                      </td>
                      <td data-label="Variance Reason (Manual)">
                        <select
                          className={`bak-select${rowIssues.variance_reason ? ' bak-select--invalid' : ''}`}
                          aria-label={`Alasan variance manual untuk ${row.role}`}
                          value={row.variance_reason}
                          disabled={row.locked}
                          onChange={(event) =>
                            updateSellerRow(
                              row.key,
                              { variance_reason: event.target.value as SellerVarianceReason | '' },
                              { autosave: true },
                            )
                          }
                        >
                          <option value="">— Pilih alasan —</option>
                          {SELLER_VARIANCE_REASONS.map((reason) => (
                            <option key={reason} value={reason}>
                              {reason}
                            </option>
                          ))}
                        </select>
                        {rowIssues.variance_reason ? (
                          <span className="bak-err">{rowIssues.variance_reason}</span>
                        ) : null}
                      </td>
                      <td data-label="Notes">
                        <NotesInput
                          ariaLabel={`Catatan untuk ${row.role}`}
                          value={row.notes}
                          disabled={row.locked}
                          onChange={(value) => updateSellerRow(row.key, { notes: value })}
                        />
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={10} className="bak-muted">
                    {loading ? 'Memuat…' : 'Belum ada data seller input.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {sellerRows.length > 0 ? (
          <div className="bak-total">
            <span>Total Accrual Input</span>
            <span className="bak-total__value">
              Rp {formatCurrency(sellerTotals.sellerInput) || '0'}{' '}
              <span className="bak-muted">
                (max Rp {formatCurrency(sellerTotals.maxPotential) || '0'} · variance{' '}
                {formatPercent(sellerTotals.variancePct)})
              </span>
            </span>
          </div>
        ) : null}

        <div className="bak-footer">
          <div className="bak-footer__left">
            {isDirty ? 'Ada perubahan yang belum tersimpan.' : 'Semua perubahan tersimpan.'}
          </div>
          <button type="button" className="bak-btn bak-btn--ghost" onClick={() => requestNavigation(2)}>
            ← Back
          </button>
          <button
            type="button"
            className="bak-btn bak-btn--ghost"
            onClick={handleSaveSellerDraft}
            disabled={saving}
          >
            {saving ? <Spinner size={12} label="Menyimpan…" /> : 'Save Draft'}
          </button>
          <button
            type="button"
            className="bak-btn bak-btn--primary"
            onClick={handleNext}
            disabled={saving}
          >
            Next →
          </button>
        </div>
      </StepPanel>

      {/* ------------------------------ STEP 4 ------------------------------ */}
      <StepPanel
        visible={step === 4}
        loading={loading}
        loadingLabel="Memuat actual input…"
        error={loadError}
        onRetry={() => void loadActualInput()}
      >
        <div className="bak-toolbar">
          <div>
            <h2 className="bak-h2">Actual Input — Finance Verification</h2>
            <p className="bak-muted">
              Otomatis tersinkron dengan Accrual Input dan Max Potential Earnings. Variance dihitung
              terhadap Max Potential Earnings dan Accrual Input. Hijau ≤ {FINANCE_GREEN_PCT}%, kuning{' '}
              {FINANCE_GREEN_PCT}-{FINANCE_YELLOW_PCT}%, merah &gt; {FINANCE_YELLOW_PCT}%. Tombol{' '}
              <b>↻ Refresh</b> menghapus isian manual finance.
            </p>
          </div>
          <div className="bak-toolbar__actions">
            <button
              type="button"
              className="bak-btn bak-btn--ghost bak-btn--sm"
              onClick={() => setResetTarget('actual')}
              disabled={resetBusy || !actualRows.length || isFullyLocked}
              title="Hapus isian manual finance, kembalikan ke nilai otomatis dari Accrual Input"
            >
              ↻ Refresh
            </button>
            <StatusBadge status={actualStatus} />
            {isFullyLocked ? (
              <span className="bak-saved">🔒 Terkunci</span>
            ) : lastSavedAt ? (
              <span className="bak-saved">✓ Tersimpan {formatClock(lastSavedAt)}</span>
            ) : null}
          </div>
        </div>

        {rolesWithoutSellerBaseline.length > 0 ? (
          <div className="bak-banner bak-banner--info">
            <span>
              {rolesWithoutSellerBaseline.length} role belum punya seller input sehingga belum bisa
              diverifikasi: {rolesWithoutSellerBaseline.join(', ')}.
            </span>
          </div>
        ) : null}

        {showActualErrors && actualIssues.length > 0 ? (
          <div className="bak-banner bak-banner--error" role="alert">
            <span>{actualIssues.length} field perlu diperbaiki.</span>
            <button
              type="button"
              className="bak-btn bak-btn--ghost bak-btn--sm"
              onClick={() => setShowActualErrors(false)}
            >
              Tutup
            </button>
          </div>
        ) : null}

        <input
          ref={docInputRef}
          type="file"
          className="bak-sr-only"
          onChange={(event) => void handleDocSelected(event)}
          tabIndex={-1}
          aria-hidden="true"
        />

        <div className="bak-table-wrap">
          <table className="bak-table">
            <thead>
              <tr>
                <th scope="col">Role</th>
                <th scope="col">Billing Type</th>
                <th scope="col" className="bak-num">
                  Max Potential
                </th>
                <th scope="col" className="bak-num">
                  Max Potential Quantity (Sum Rate)
                </th>
                <th scope="col" className="bak-num">
                  Accrual Input
                </th>
                <th scope="col" className="bak-num">
                  Accrual Quantity (Sum Rate)
                </th>
                <th scope="col" className="bak-num">
                  Actual Input
                </th>
                <th scope="col" className="bak-num">
                  Actual Price/Unit
                </th>
                <th scope="col" className="bak-num">
                  Actual Quantity (Sum Rate)
                </th>
                <th scope="col" className="bak-num">
                  Variance With Max Potential
                </th>
                <th scope="col">Variance With Max Potential Reason (Auto)</th>
                <th scope="col" className="bak-num">
                  Variance With Accrual
                </th>
                <th scope="col">Variance With Accrual Variance Reason (Auto)</th>
                <th scope="col">Variance Reason (Manual)</th>
                <th scope="col">Notes</th>
                <th scope="col">Supporting Docs</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              {actualRows.length ? (
                actualRows.map((row) => {
                  const rowIssues = showActualErrors ? actualIssuesById[row.key] ?? {} : {};
                  const autoReason = autoReasonForActual(row);
                  const autoReasonMax = autoReasonActualVsMax(row);
                  const locked = row.status === 'LOCKED' || isFullyLocked;
                  const { variance, variancePct } = computeVariance(row.seller_input, row.actual_input);
                  const maxVar = computeVariance(row.max_potential, row.actual_input);
                  const toneMax = row.actual_input == null
                    ? 'neutral'
                    : varianceTone(
                        maxVar.variance,
                        maxVar.variancePct,
                        FINANCE_GREEN_PCT,
                        FINANCE_YELLOW_PCT,
                      );
                  const tone = row.actual_input == null
                    ? 'neutral'
                    : varianceTone(variance, variancePct, FINANCE_GREEN_PCT, FINANCE_YELLOW_PCT);
                  const rowClass =
                    row.actual_input == null
                      ? undefined
                      : tone === 'ok'
                        ? 'bak-row--ok'
                        : tone === 'warn'
                          ? 'bak-row--warn'
                          : 'bak-row--danger';
                  return (
                    <tr key={row.key} className={rowClass}>
                      <td data-label="Role">
                        <span>{row.role}</span>
                        {row.category ? <span className="bak-cat">{row.category}</span> : null}
                      </td>
                      <td data-label="Billing Type">
                        <span className="bak-cat">{row.billing_type ?? 'Hours'}</span>
                      </td>
                      <td data-label="Max Potential">
                        <ReadOnlyCurrency
                          value={row.max_potential}
                          ariaLabel={`Max potential untuk ${row.role}`}
                        />
                      </td>
                      <td data-label="Max Potential Quantity" className="bak-num">
                        <span className="bak-readonly">
                          {row.max_potential_quantity == null
                            ? '-'
                            : formatQuantity(row.max_potential_quantity)}
                        </span>
                      </td>
                      <td data-label="Accrual Input" className="bak-num">
                        <span className="bak-readonly">
                          {row.seller_input == null ? '-' : formatCurrency(row.seller_input)}
                        </span>
                      </td>
                      <td data-label="Accrual Input Quantity" className="bak-num">
                        <span className="bak-readonly">
                          {row.seller_quantity == null ? '-' : formatQuantity(row.seller_quantity)}
                        </span>
                      </td>
                      <td data-label="Actual Input">
                        <ReadOnlyCurrency
                          value={row.actual_input}
                          ariaLabel={`Actual input untuk ${row.role}`}
                        />
                        {rowIssues.actual_input ? (
                          <span className="bak-err">{rowIssues.actual_input}</span>
                        ) : null}
                      </td>
                      <td data-label="Actual Price/Unit">
                        <CurrencyInput
                          ariaLabel={`Actual price per unit untuk ${row.role}`}
                          value={row.actual_price_per_unit ?? null}
                          disabled={locked}
                          invalid={Boolean(rowIssues.price_per_unit)}
                          onChange={(value) =>
                            updateActualRow(row.key, {
                              actual_price_per_unit: value,
                              actual_input:
                                value != null
                                  ? computeAmount(value, row.quantity) ?? row.actual_input
                                  : row.actual_input,
                            })
                          }
                        />
                        {rowIssues.price_per_unit ? (
                          <span className="bak-err">{rowIssues.price_per_unit}</span>
                        ) : null}
                      </td>
                      <td data-label="Actual Quantity">
                        <QuantityInput
                          ariaLabel={`Actual quantity untuk ${row.role}`}
                          value={row.quantity ?? null}
                          disabled={locked}
                          invalid={Boolean(rowIssues.quantity)}
                          onChange={(value) =>
                            updateActualRow(row.key, {
                              quantity: value,
                              actual_input:
                                value != null
                                  ? computeAmount(row.actual_price_per_unit, value) ?? row.actual_input
                                  : row.actual_input,
                            })
                          }
                        />
                        {rowIssues.quantity ? (
                          <span className="bak-err">{rowIssues.quantity}</span>
                        ) : null}
                      </td>
                      <td data-label="Variance With Max Potential" className="bak-num">
                        <span className={`bak-variance bak-variance--${toneMax}`}>
                          <span>
                            {row.actual_input == null ? '-' : formatVariance(maxVar.variance)}
                          </span>
                          <span className="bak-variance__pct">
                            {row.actual_input == null ? '-' : formatPercent(maxVar.variancePct)}
                          </span>
                        </span>
                      </td>
                      <td data-label="Variance With Max Potential Reason (Auto)">
                        {(() => {
                          const shortPos = shortfallByPosition.get(row.role);
                          const standard = attSummary?.standard_days ?? 0;
                          const short = shortPos ? shortfallReason(shortPos, standard) : null;
                          if (autoReasonMax) {
                            return <span className="bak-auto-reason">{autoReasonMax.label}</span>;
                          }
                          if (short) {
                            return <span className="bak-auto-reason">{short.label}</span>;
                          }
                          return <span className="bak-balanced">Balance</span>;
                        })()}
                        <ShortfallNote
                          shortfalls={shortfallByPosition.get(row.role)?.shortfalls ?? []}
                          standardDays={attSummary?.standard_days ?? 0}
                        />
                      </td>
                      <td data-label="Variance With Accrual" className="bak-num">
                        <span className={`bak-variance bak-variance--${tone}`}>
                          <span>{row.actual_input == null ? '-' : formatVariance(variance)}</span>
                          <span className="bak-variance__pct">
                            {row.actual_input == null ? '-' : formatPercent(variancePct)}
                          </span>
                        </span>
                      </td>
                      <td data-label="Variance With Accrual Variance Reason (Auto)">
                        {autoReason ? (
                          <span className="bak-auto-reason">{autoReason.label}</span>
                        ) : (
                          <span className="bak-balanced">Balance</span>
                        )}
                      </td>
                      <td data-label="Variance Reason (Manual)">
                        <select
                          className={`bak-select${rowIssues.variance_reason_finance ? ' bak-select--invalid' : ''}`}
                          aria-label={`Alasan variance finance manual untuk ${row.role}`}
                          value={row.variance_reason_finance}
                          disabled={locked}
                          onChange={(event) =>
                            updateActualRow(row.key, {
                              variance_reason_finance: event.target.value as FinanceVarianceReason | '',
                            })
                          }
                        >
                          <option value="">— Pilih alasan —</option>
                          {FINANCE_VARIANCE_REASONS.map((reason) => (
                            <option key={reason} value={reason}>
                              {reason}
                            </option>
                          ))}
                        </select>
                        {rowIssues.variance_reason_finance ? (
                          <span className="bak-err">{rowIssues.variance_reason_finance}</span>
                        ) : null}
                      </td>
                      <td data-label="Notes">
                        <NotesInput
                          ariaLabel={`Catatan actual untuk ${row.role}`}
                          value={row.notes}
                          disabled={locked}
                          onChange={(value) => updateActualRow(row.key, { notes: value })}
                        />
                      </td>
                      <td data-label="Supporting Docs">
                        <div className="bak-docs">
                          <input
                            type="text"
                            className={`bak-input${rowIssues.supporting_docs ? ' bak-input--invalid' : ''}`}
                            aria-label={`Dokumen pendukung untuk ${row.role}`}
                            placeholder="URL / no. dokumen"
                            value={row.supporting_docs}
                            disabled={locked}
                            onChange={(event) =>
                              updateActualRow(row.key, { supporting_docs: event.target.value })
                            }
                          />
                          {!locked ? (
                            <button
                              type="button"
                              className="bak-btn bak-btn--ghost bak-btn--sm"
                              onClick={() => handlePickDoc(row.key)}
                            >
                              Pilih file
                            </button>
                          ) : null}
                        </div>
                        {rowIssues.supporting_docs ? (
                          <span className="bak-err">{rowIssues.supporting_docs}</span>
                        ) : null}
                      </td>
                      <td data-label="Status">
                        <StatusBadge status={row.status} />
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={17} className="bak-muted">
                    {loading ? 'Memuat…' : 'Belum ada data actual input.'}
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>

        {actualRows.length > 0 ? (
          <div className="bak-total">
            <span>Total Actual Input</span>
            <span className="bak-total__value">
              Rp {formatCurrency(actualTotals.actualInput) || '0'}{' '}
              <span className="bak-muted">
                (seller Rp {formatCurrency(actualTotals.sellerInput) || '0'} · variance{' '}
                {formatPercent(actualTotals.variancePct)})
              </span>
            </span>
          </div>
        ) : null}

        <div className="bak-footer">
          <div className="bak-footer__left">
            {isFullyLocked
              ? 'BAK sudah di-approve & lock — semua field read-only.'
              : stepDirty
                ? 'Ada perubahan yang belum tersimpan.'
                : 'Semua perubahan tersimpan.'}
          </div>
          <button type="button" className="bak-btn bak-btn--ghost" onClick={() => requestNavigation(3)}>
            ← Back
          </button>
          <button
            type="button"
            className="bak-btn bak-btn--ghost"
            onClick={handleSaveActualDraft}
            disabled={saving || isFullyLocked}
          >
            {saving ? <Spinner size={12} label="Menyimpan…" /> : 'Save Draft'}
          </button>
          <button
            type="button"
            className="bak-btn bak-btn--primary"
            onClick={() => void handleSubmit()}
            disabled={saving || isFullyLocked}
          >
            Submit for Approval
          </button>
          <button
            type="button"
            className="bak-btn bak-btn--success"
            onClick={() => void handleApproveAndLock()}
            disabled={saving || isFullyLocked}
          >
            {isFullyLocked ? 'Locked 🔒' : 'Approve & Lock'}
          </button>
        </div>
      </StepPanel>

      <ConfirmDialog
        open={resetTarget !== null}
        title={resetTarget === 'actual' ? 'Refresh Actual Input' : 'Refresh Accrual Input'}
        message={
          <>
            Semua isian manual pada <strong>{resetTarget === 'actual' ? 'Actual Input' : 'Accrual Input'}</strong>{' '}
            akan dihapus dan dikembalikan ke nilai otomatis dari{' '}
            <strong>{resetTarget === 'actual' ? 'Accrual Input' : 'Max Potential Earnings'}</strong>.
            {resetTarget === 'actual' ? ' Baris yang sudah LOCKED tidak diubah.' : ''} Lanjutkan?
          </>
        }
        onClose={() => setResetTarget(null)}
        actions={[
          {
            label: 'Batal',
            variant: 'ghost',
            onClick: () => setResetTarget(null),
          },
          {
            label: resetBusy ? 'Memproses…' : 'Ya, refresh',
            variant: 'danger',
            onClick: () => {
              if (resetTarget) void runReset(resetTarget);
            },
          },
        ]}
      />

      <ConfirmDialog
        open={pendingNav !== null}
        title="Perubahan belum tersimpan"
        message={
          <>
            Masih ada perubahan yang belum tersimpan pada step <strong>{STEPS[step - 1].title}</strong>.
            Simpan dulu sebelum pindah step?
          </>
        }
        onClose={() => setPendingNav(null)}
        actions={[
          {
            label: 'Batal',
            variant: 'ghost',
            onClick: () => setPendingNav(null),
          },
          {
            label: 'Buang perubahan',
            variant: 'danger',
            onClick: () => {
              const target = pendingNav;
              setPendingNav(null);
              discardChanges();
              if (target) setStep(target);
            },
          },
          {
            label: 'Simpan & lanjut',
            variant: 'primary',
            onClick: () => {
              const target = pendingNav;
              setPendingNav(null);
              if (target == null) return;
              void (async () => {
                if (step === 2) {
                  const ok = await saveMaxPotential();
                  if (!ok) return;
                } else if (step === 3) {
                  const ok = await saveSellerDraft({ silent: true });
                  if (!ok) return;
                } else if (step === 4) {
                  const ok = await persistActualRows(actualStatus, { silent: true });
                  if (!ok) return;
                }
                setStep(target);
              })();
            },
          },
        ]}
      />

      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </div>
  );
}

/* ========================================================================== */
/* Helpers (internal)                                                          */
/* ========================================================================== */

function groupIssues(issues: ValidationIssue[]): Record<string, Record<string, string>> {
  const grouped: Record<string, Record<string, string>> = {};
  issues.forEach((issue) => {
    grouped[issue.id] = grouped[issue.id] ?? {};
    grouped[issue.id][issue.field] = issue.message;
  });
  return grouped;
}

function formatClock(date: Date): string {
  const hh = String(date.getHours()).padStart(2, '0');
  const mm = String(date.getMinutes()).padStart(2, '0');
  const ss = String(date.getSeconds()).padStart(2, '0');
  return `${hh}:${mm}:${ss}`;
}

/** Merge id/status hasil POST ke state seller (agar row berikutnya pakai id server). */
function mergeSellerResponse(
  response: unknown,
  setRows: React.Dispatch<React.SetStateAction<SellerInputRow[]>>,
): void {
  const items = extractItems(response);
  if (items.length === 0) return;
  const byKey = new Map<string, any>();
  items.forEach((item) => {
    if (item?.max_potential_id != null) byKey.set(String(item.max_potential_id), item);
    if (item?.role != null) byKey.set(String(item.role), item);
  });
  setRows((prev) =>
    prev.map((row) => {
      const saved = byKey.get(String(row.max_potential_id)) ?? byKey.get(String(row.role));
      if (!saved) return row;
      return {
        ...row,
        id: saved.id ?? row.id,
        variance_reason: (saved.variance_reason ?? row.variance_reason) as SellerVarianceReason | '',
      };
    }),
  );
}

/** Merge id/status hasil POST / approve ke state actual. */
function mergeActualResponse(
  response: unknown,
  setRows: React.Dispatch<React.SetStateAction<ActualInputRow[]>>,
): void {
  const items = extractItems(response);
  if (items.length === 0) return;
  const byKey = new Map<string, any>();
  items.forEach((item) => {
    if (item?.seller_input_id != null) byKey.set(String(item.seller_input_id), item);
    if (item?.role != null) byKey.set(String(item.role), item);
  });
  setRows((prev) =>
    prev.map((row) => {
      const saved = byKey.get(String(row.seller_input_id)) ?? byKey.get(String(row.role));
      if (!saved) return row;
      return {
        ...row,
        id: saved.id ?? row.id,
        status: readStatus(saved, row.status),
      };
    }),
  );
}
