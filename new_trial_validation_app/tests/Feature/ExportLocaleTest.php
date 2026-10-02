<?php

use App\Mail\TrialLineConfigurationReturnedMail;
use App\Mail\TrialReviewRequestedMail;
use App\Models\Trial;
use App\Models\User;
use App\Services\Pdf\ExportFormat;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

function makeExportLocaleTrial(array $attributes = []): Trial
{
    return Trial::create([
        'trial_code' => 'TRIAL-EXPORT-1',
        'product_name' => 'Export Product',
        'finish_good_code' => 'FG-EX',
        'product_type' => 'Tube',
        'validation_scope' => ['Filling'],
        'machine_used' => ['Machine A'],
        'progress_status' => 'Approved',
        'final_decision' => 'Approved',
        'current_step' => 'Closed',
        'created_by' => 'owner@local.test',
        'approved_by' => 'owner@local.test',
        'approved_at' => '2026-10-02 14:30:00',
        'approval_comment' => 'OK to go',
        'pending_with' => '',
        ...$attributes,
    ]);
}

test('the per-trial report PDF follows the locale cookie', function (string $locale, string $expected) {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeExportLocaleTrial();

    $html = $this->actingAs($owner)
        ->withUnencryptedCookie('locale', $locale)
        ->get(route('trials.report.pdf', $trial))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('<html lang="'.$locale.'">')
        ->toContain($expected)
        // Stored values are never translated.
        ->toContain('TRIAL-EXPORT-1')
        ->toContain('OK to go');
})->with([
    ['id', 'Parameter Validasi'],
    ['en', 'Validation Parameters'],
    ['ko', '검증'],
]);

test('the Korean trial report PDF shows translated labels, status and dates', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeExportLocaleTrial();

    $html = $this->actingAs($owner)
        ->withUnencryptedCookie('locale', 'ko')
        ->get(route('trials.report.pdf', $trial))
        ->getContent();

    app()->setLocale('ko');

    expect($html)->toContain(e(__('report.page_title', ['code' => 'TRIAL-EXPORT-1'])))
        ->toContain(e(__('report.decision.title')))
        ->toContain(e(__('common.status.approved')))
        ->toContain('2026년 10월 2일 14:30 WIB');
});

test('list report PDFs use translated titles and empty-state text', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'ko')
        ->get(route('reports.approved.pdf'))
        ->assertOk()
        ->getContent();

    app()->setLocale('ko');

    expect($html)->toContain(__('exports.titles.approved'))
        ->toContain(__('exports.empty.approved'));
});

test('the Excel export uses translated sheet names and labels', function () {
    $owner = User::factory()->create(['email' => 'owner@local.test']);
    $trial = makeExportLocaleTrial();

    $response = $this->actingAs($owner)
        ->withUnencryptedCookie('locale', 'ko')
        ->get(route('trials.report.excel', $trial))
        ->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->streamedContent());
    $spreadsheet = (new XlsxReader)->load($path);
    unlink($path);

    app()->setLocale('ko');

    expect($spreadsheet->getSheetNames())->toBe([
        __('exports.excel.sheets.info'),
        __('exports.excel.sheets.validation'),
        __('exports.excel.sheets.weighing'),
        __('exports.excel.sheets.review'),
        __('exports.excel.sheets.decision'),
    ]);

    $info = $spreadsheet->getSheet(0);
    expect($info->getCell('A1')->getValue())->toBe(__('exports.excel.info_title'))
        ->and($info->getCell('A3')->getValue())->toBe(__('report.info.trial_id'))
        ->and($info->getCell('B3')->getValue())->toBe('TRIAL-EXPORT-1');
});

test('emails render in the locale active when they were created', function () {
    $trial = makeExportLocaleTrial();

    app()->setLocale('ko');
    $mail = new TrialReviewRequestedMail($trial, 'Reviewer Kim', 'QAC', 'https://example.test/r');
    app()->setLocale('id');

    // Rendering later (e.g. on a queue worker, or after the request locale
    // changed) still uses the captured locale.
    expect($mail->locale)->toBe('ko');
    $mail->assertHasSubject('트라이얼 TRIAL-EXPORT-1 검토 요청');
    $mail->assertSeeInHtml('Reviewer Kim님, 안녕하십니까.');
    $mail->assertSeeInHtml('QAC');
});

test('Indonesian emails keep stored values as-is', function () {
    app()->setLocale('id');
    $trial = makeExportLocaleTrial();

    $mail = new TrialLineConfigurationReturnedMail($trial, 'Drafter', 'Checked (PROD)', 'Butuh revisi', 'https://example.test/r');

    $mail->assertHasSubject('Line Configuration Report Dikembalikan untuk Revisi (TRIAL-EXPORT-1)');
    $mail->assertSeeInHtml('Checked (PROD)');
    $mail->assertSeeInHtml('Butuh revisi');
});

test('export dates are locale-aware and Asia/Jakarta-local', function () {
    $date = CarbonImmutable::parse('2026-10-02 14:30:00', config('app.timezone'));

    app()->setLocale('id');
    expect(ExportFormat::dateTime($date))->toBe('2 Oktober 2026 14:30 WIB');

    app()->setLocale('en');
    expect(ExportFormat::dateTime('2026-10-02 14:30:00'))->toBe('October 2, 2026, 14:30 WIB');

    app()->setLocale('ko');
    expect(ExportFormat::dateTime($date))->toBe('2026년 10월 2일 14:30 WIB')
        ->and(ExportFormat::date($date))->toBe('2026년 10월 2일')
        ->and(ExportFormat::dateTime(null))->toBe('-');
});
