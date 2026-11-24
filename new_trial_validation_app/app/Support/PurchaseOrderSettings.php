<?php

namespace App\Support;

use App\Models\ProjectSetting;
use Carbon\CarbonInterface;

/**
 * The Purchase Order template (FR.PRC.001.00): company block, numbering
 * pattern, default terms, billing/notice text and the three signer boxes.
 * Stored as one JSON row in `pm_settings` (key `po_template`); anything not
 * stored falls back to the defaults below, which are the legacy Project
 * Tracking defaults.
 *
 * The document language (en/id) is a property of the template, not of the UI
 * locale: a PO goes to a vendor, so it must not change with the viewer's
 * language.
 */
final class PurchaseOrderSettings
{
    public const KEY = 'po_template';

    public const DATE_FORMATS = ['dd-Mmm-yy', 'dd/mm/yyyy', 'dd Mmmm yyyy', 'dd Mmm yyyy'];

    public const LANGUAGES = ['en', 'id'];

    private const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    private const MONTHS = [
        'en' => ['s' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], 'f' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']],
        'id' => ['s' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'], 'f' => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']],
    ];

    private const LABELS = [
        'en' => ['to' => 'TO', 'ship' => 'SHIP TO', 'po' => 'PO. Number', 'date' => 'PO. Date', 'tel' => 'Telp', 'attn' => 'Attn', 'email' => 'E-mail', 'no' => 'No.', 'desc' => 'DESCRIPTION', 'qty' => 'QTY', 'unit' => 'Unit', 'due' => 'Due Date', 'price' => 'Unit Price', 'total' => 'Total', 'sub' => 'SUB TOTAL', 'tax' => 'Tax', 'grand' => 'GRAND TOTAL', 'pay' => 'Payment', 'lead' => 'Lead Time', 'deliv' => 'Delivery Place'],
        'id' => ['to' => 'KEPADA', 'ship' => 'KIRIM KE', 'po' => 'No. PO', 'date' => 'Tanggal PO', 'tel' => 'Telp', 'attn' => 'Up.', 'email' => 'E-mail', 'no' => 'No.', 'desc' => 'DESKRIPSI', 'qty' => 'QTY', 'unit' => 'Satuan', 'due' => 'Tgl. Kirim', 'price' => 'Harga Satuan', 'total' => 'Jumlah', 'sub' => 'SUB TOTAL', 'tax' => 'PPN', 'grand' => 'GRAND TOTAL', 'pay' => 'Pembayaran', 'lead' => 'Lead Time', 'deliv' => 'Lokasi Pengiriman'],
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'company' => 'PT COSMAX INDONESIA',
            'doc_code' => 'FR.PRC.001.00',
            'logo' => '',
            'address' => "Jl. Raya Bogor KM. 26,4\nCiracas, Jakarta Timur",
            'contact' => "Telp. (021) 29616781-86, Fax. (021) 29479397\nWebsite : www.cosmax.com",
            'title' => 'PURCHASE ORDER',
            'pattern' => '{NNN}/PO/PI/{RM}/{YYYY}',
            'date_format' => 'dd-Mmm-yy',
            'lang' => 'en',
            'tax' => 11,
            'terms' => '',
            'lead_time' => '',
            'delivery' => 'PT Cosmax Indonesia',
            'ship_name' => 'PT. COSMAX INDONESIA',
            'ship_address' => "Jl. Raya Bogor KM 26,4\nCiracas, Jakarta Timur 13740",
            'ship_tel' => '(021) 29616781-86',
            'ship_attn' => '',
            'billing' => "Payment Billing Must Be Attached :\n1) Copy PO & Original Tax Invoice\n2) Original Receipt & Original Delivery Order\n3) COA ( Certificate of Analysis )\n4) Tax Number/NPWP/NPPKP PT. Cosmax Indonesia : 03.178.339.2-009.000\n5) Address of tax number : Jl. Raya Bogor Km 26, 4 RT.003 RW.007 Ciracas - Ciracas Jakarta Timur",
            'notice' => "PT Cosmax Indonesia is committed to implementing an Anti-Bribery Management System and maintaining a zero-tolerance policy against bribery, gratuities, corruption, collusion, nepotism, and any other improper conduct, in accordance with the Company's policies and applicable laws and regulations.\n\nIf any violation or misconduct is identified, Business Partners (Vendors) may report it via e-mail: internal.audit@cosmax.com\n\nPT Cosmax Indonesia is committed to protecting whistleblowers and maintaining the confidentiality of all reports received.",
            'signers' => [
                ['label' => 'Approved', 'name' => '', 'title' => '', 'image' => ''],
                ['label' => '', 'name' => '', 'title' => '', 'image' => ''],
                ['label' => '', 'name' => '', 'title' => '', 'image' => ''],
            ],
        ];
    }

    /** @return array<string, mixed> stored template merged over the defaults */
    public static function get(): array
    {
        $stored = ProjectSetting::read(self::KEY);
        $merged = array_merge(self::defaults(), array_intersect_key($stored, self::defaults()));
        $merged['signers'] = self::normalizeSigners($merged['signers'] ?? []);
        $merged['lang'] = in_array($merged['lang'], self::LANGUAGES, true) ? $merged['lang'] : 'en';
        $merged['date_format'] = in_array($merged['date_format'], self::DATE_FORMATS, true) ? $merged['date_format'] : 'dd-Mmm-yy';

        return $merged;
    }

    /** @param  array<string, mixed>  $data */
    public static function save(array $data): void
    {
        $clean = array_intersect_key($data, self::defaults());
        $clean['signers'] = self::normalizeSigners($clean['signers'] ?? []);
        if (array_key_exists('logo', $clean)) {
            $clean['logo'] = self::safeImage($clean['logo'], 300 * 1024);
        }
        ProjectSetting::write(self::KEY, $clean);
    }

    /**
     * Always exactly three signer boxes; images must be small inline raster
     * data URLs (same rule as Check Tooling signatures), anything else is dropped.
     *
     * @return list<array{label: string, name: string, title: string, image: string}>
     */
    public static function normalizeSigners(mixed $signers): array
    {
        $signers = is_array($signers) ? array_values($signers) : [];
        $out = [];
        for ($i = 0; $i < 3; $i++) {
            $s = is_array($signers[$i] ?? null) ? $signers[$i] : [];
            $out[] = [
                'label' => mb_substr(trim((string) ($s['label'] ?? '')), 0, 60),
                'name' => mb_substr(trim((string) ($s['name'] ?? '')), 0, 100),
                'title' => mb_substr(trim((string) ($s['title'] ?? '')), 0, 100),
                'image' => self::safeImage($s['image'] ?? ($s['img'] ?? ''), 64 * 1024),
            ];
        }

        return $out;
    }

    /** Inline png/jpeg data URL within the size cap, else an empty string. */
    public static function safeImage(mixed $value, int $maxBytes): string
    {
        $value = is_string($value) ? trim($value) : '';
        if (preg_match('#^data:image/(png|jpeg|jpg);base64,[A-Za-z0-9+/=]+$#', $value) !== 1) {
            return '';
        }

        return strlen($value) <= $maxBytes ? $value : '';
    }

    /** @return array<string, string> */
    public static function labels(string $lang): array
    {
        return self::LABELS[$lang] ?? self::LABELS['en'];
    }

    /** Pattern with every code except {NNN} resolved for the given date. */
    public static function resolvePattern(string $pattern, CarbonInterface $date): string
    {
        $pattern = trim($pattern) === '' ? (string) self::defaults()['pattern'] : $pattern;
        if (! str_contains($pattern, '{NNN}')) {
            $pattern .= '/{NNN}';
        }

        $year = $date->format('Y');

        return str_replace(
            ['{YYYY}', '{YY}', '{MM}', '{RM}'],
            [$year, substr($year, 2), $date->format('m'), self::ROMAN[(int) $date->format('n') - 1]],
            $pattern,
        );
    }

    public static function formatDate(?CarbonInterface $date, string $format, string $lang): string
    {
        if ($date === null) {
            return '';
        }

        $months = self::MONTHS[$lang] ?? self::MONTHS['en'];
        $dd = $date->format('d');
        $mi = (int) $date->format('n') - 1;
        $year = $date->format('Y');

        return match ($format) {
            'dd/mm/yyyy' => $dd.'/'.$date->format('m').'/'.$year,
            'dd Mmmm yyyy' => $dd.' '.$months['f'][$mi].' '.$year,
            'dd Mmm yyyy' => $dd.' '.$months['s'][$mi].' '.$year,
            default => $dd.'-'.$months['s'][$mi].'-'.substr($year, 2),
        };
    }
}
