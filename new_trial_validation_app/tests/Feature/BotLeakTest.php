<?php

use App\Models\MasterOption;
use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Endpoint-level sweep: whatever the bot is asked, a user must never see a
 * trial they could not open from the normal pages (TrialPolicy::view). Secret
 * trials are created (someone else's Draft, a soft-deleted one, an In Review
 * trial a reviewer was never assigned to) and every intent is asked; none of
 * their identifying text may appear in the response. A non-Draft trial is
 * deliberately NOT secret for Staff: the policy lets them open it.
 */
function secretTrial(string $code, array $attributes = []): Trial
{
    $trial = Trial::create(array_merge([
        'trial_code' => $code,
        'product_name' => 'SECRETPRODUCT-'.$code,
        'product_type' => 'Tube',
        'progress_status' => 'Draft',
        'current_step' => 'Validation',
        'revision_no' => 0,
        'created_by' => 'owner-secret@local.test',
    ], $attributes));

    DB::table('trials_header')->where('id', $trial->id)->update(['updated_at' => now()->subDays(60)]);

    return $trial;
}

beforeEach(function () {
    MasterOption::create(['type' => 'product_type', 'name' => 'Tube', 'is_active' => true]);

    secretTrial('TRIAL-SECRET-DRAFT');
    secretTrial('TRIAL-SECRET-INREVIEW', ['progress_status' => 'In Review', 'current_step' => 'Review']);
    $deleted = secretTrial('TRIAL-SECRET-DELETED', ['progress_status' => 'In Review', 'current_step' => 'Review']);
    DB::table('trials_header')->where('id', $deleted->id)->update(['deleted_at' => now()]);
});

dataset('bot questions', [
    'my queue' => 'apa yang harus saya kerjakan',
    'draft list' => 'trial draft untuk Tube',
    'approval list' => 'siap approval untuk Tube',
    'in review list' => 'trial in review Tube',
    'count' => 'berapa jumlah trial Tube',
    'stuck' => 'trial apa yang macet lebih dari 7 hari',
    'status draft' => 'status TRIAL-SECRET-DRAFT',
    'status in review' => 'status TRIAL-SECRET-INREVIEW',
    'status deleted' => 'status TRIAL-SECRET-DELETED',
    'hotspots' => 'parameter apa yang paling sering gagal di Tube',
    'weighing' => 'normal sebaran timbang filling untuk Tube',
    'summary' => 'ringkasan semua trial',
    'my trials' => 'tampilkan trial saya',
    'latest' => 'tampilkan 10 trial terbaru',
    'latest in review' => 'trial in review terbaru',
    'search product' => 'cari secretproduct',
    'search code part' => 'search trial-secret',
    'not ok draft' => 'kenapa TRIAL-SECRET-DRAFT NOT OK',
    'not ok in review' => 'why did TRIAL-SECRET-INREVIEW fail',
    'not ok deleted' => 'kenapa TRIAL-SECRET-DELETED gagal',
    'reviews draft' => 'siapa yang belum review TRIAL-SECRET-DRAFT',
    'reviews in review' => 'who has not reviewed TRIAL-SECRET-INREVIEW',
    'reviews deleted' => 'siapa reviewer TRIAL-SECRET-DELETED',
    'duration' => 'berapa lama proses review biasanya',
    'how to' => 'bagaimana cara membuat trial baru',
]);

test('a plain staff stranger never receives another user\'s trial data', function (string $question) {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);

    $body = json_encode(
        $this->actingAs($stranger)->postJson(route('bot.ask'), ['question' => $question])->assertOk()->json(),
        JSON_UNESCAPED_UNICODE,
    );

    expect($body)
        ->not->toContain('SECRETPRODUCT-TRIAL-SECRET-DRAFT')
        ->not->toContain('SECRETPRODUCT-TRIAL-SECRET-DELETED')
        ->not->toContain('TRIAL-SECRET-DRAFT')
        ->not->toContain('TRIAL-SECRET-DELETED')
        ->not->toContain('owner-secret');
})->with('bot questions');

test('a reviewer never sees a trial they were not assigned to review', function (string $question) {
    $reviewer = User::factory()->reviewUnit('PROD')->create(['role' => 'Reviewer', 'department' => 'Reviewer', 'email' => 'reviewer@local.test']);

    $body = json_encode(
        $this->actingAs($reviewer)->postJson(route('bot.ask'), ['question' => $question])->assertOk()->json(),
        JSON_UNESCAPED_UNICODE,
    );

    expect($body)->not->toContain('SECRET')->not->toContain('owner-secret');
})->with('bot questions');

test('the owner sees their own draft but not the hidden ones', function () {
    $owner = User::factory()->create(['role' => 'Staff', 'email' => 'owner-secret@local.test']);

    $draft = $this->actingAs($owner)->postJson(route('bot.ask'), ['question' => 'status TRIAL-SECRET-DRAFT'])->json();
    $deleted = $this->actingAs($owner)->postJson(route('bot.ask'), ['question' => 'status TRIAL-SECRET-DELETED'])->json();

    expect(collect($draft['trials'])->pluck('trial_code')->all())->toBe(['TRIAL-SECRET-DRAFT'])
        ->and($deleted['trials'])->toBe([]);
});

test('even an admin cannot see a soft-deleted trial through the bot', function () {
    $admin = User::factory()->create(['role' => 'Admin']);

    $response = $this->actingAs($admin)->postJson(route('bot.ask'), ['question' => 'status TRIAL-SECRET-DELETED'])->json();

    expect($response['trials'])->toBe([]);
});

test('my queue only counts the asker\'s own work', function () {
    $stranger = User::factory()->create(['role' => 'Staff', 'email' => 'stranger@local.test']);
    $owner = User::factory()->create(['role' => 'Staff', 'email' => 'owner-secret@local.test']);

    $strangerAnswer = $this->actingAs($stranger)->postJson(route('bot.ask'), ['question' => 'apa yang harus saya kerjakan'])->json('text');
    $ownerAnswer = $this->actingAs($owner)->postJson(route('bot.ask'), ['question' => 'apa yang harus saya kerjakan'])->json('text');

    expect($strangerAnswer)->not->toBe($ownerAnswer);
});
