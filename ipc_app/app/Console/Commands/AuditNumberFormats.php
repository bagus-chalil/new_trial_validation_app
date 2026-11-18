<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan ipc:audit-number-formats [--show=10]` — READ-ONLY data-quality check.
 *
 * Numbers must be stored with a dot as decimal separator (clean data for reports and later AI
 * training). Real decimal columns can't hold anything else, so only the free-text columns that
 * carry numbers are checked, each against the exact format its request validates.
 * Never writes. Exit code 1 when something non-conforming is found (usable in CI / cron).
 */
class AuditNumberFormats extends Command
{
    protected $signature = 'ipc:audit-number-formats {--show=10 : Jumlah contoh baris per temuan}';

    protected $description = 'Periksa (baca saja) kolom teks berisi angka: harus pakai titik desimal dan format yang valid';

    /** table => [column => [regex that valid values must match, human description]] */
    private const CHECKS = [
        'packing_checks' => ['standard_weight_mb' => ['/^\d+(\.\d+)?(-\d+(\.\d+)?)?$/', 'angka atau rentang, titik desimal']],
        'packing_check_revisions' => ['standard_weight_mb' => ['/^\d+(\.\d+)?(-\d+(\.\d+)?)?$/', 'angka atau rentang, titik desimal']],
        'finished_checks' => [
            'masterbox' => ['/^\d+(,\d+)*$/', 'bilangan bulat dipisah koma'],
            'no_pallet_qty' => ['/^\d+\/\d+(,\d+\/\d+)*$/', 'pallet/qty dipisah koma'],
        ],
        'finished_check_revisions' => [
            'masterbox' => ['/^\d+(,\d+)*$/', 'bilangan bulat dipisah koma'],
            'no_pallet_qty' => ['/^\d+\/\d+(,\d+\/\d+)*$/', 'pallet/qty dipisah koma'],
        ],
    ];

    public function handle(): int
    {
        $show = max(0, (int) $this->option('show'));
        $bad = 0;

        foreach (self::CHECKS as $table => $columns) {
            foreach ($columns as $column => [$pattern, $description]) {
                $total = 0;
                $invalid = [];

                DB::table($table)->whereNotNull($column)->select(['id', $column])->orderBy('id')
                    ->each(function ($row) use ($column, $pattern, &$total, &$invalid) {
                        $total++;
                        if (! preg_match($pattern, (string) $row->{$column})) {
                            $invalid[] = ['id' => $row->id, 'value' => $row->{$column}];
                        }
                    });

                $count = count($invalid);
                $bad += $count;
                $this->line(sprintf('%-26s %-20s %5d dicek, %s tidak sesuai (%s)', $table, $column, $total, $count, $description));

                if ($count > 0 && $show > 0) {
                    $this->table(['id', 'nilai'], array_slice($invalid, 0, $show));
                }
            }
        }

        $this->newLine();
        $bad === 0
            ? $this->info('Semua nilai sesuai format.')
            : $this->warn("{$bad} nilai tidak sesuai format. Perintah ini tidak mengubah data.");

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
