<?php

use App\Models\User;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;

test('the UI defaults to Indonesian', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'id')
            ->where('translations', fn ($t) => $t['common.nav.my_work'] === 'Pekerjaan Saya'));
});

test('the locale cookie switches the UI language', function (string $locale, string $myWork) {
    $this->actingAs(User::factory()->create());

    $this->withUnencryptedCookie('locale', $locale)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', $locale)
            ->where('translations', fn ($t) => $t['common.nav.my_work'] === $myWork));

    expect(app()->getLocale())->toBe($locale);
})->with([
    ['en', 'My Work'],
    ['ko', '내 업무'],
]);

test('an unsupported locale cookie falls back to Indonesian', function () {
    $this->actingAs(User::factory()->create());

    $this->withUnencryptedCookie('locale', 'fr')
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'id'));
});

test('every translation group has the same keys in every language', function () {
    $keys = function (string $locale): array {
        $all = [];

        foreach (glob(lang_path($locale.'/*.php')) as $file) {
            $group = basename($file, '.php');

            foreach (array_keys(Arr::dot(require $file)) as $key) {
                $all[] = $group.'.'.$key;
            }
        }

        sort($all);

        return $all;
    };

    expect($keys('en'))->toBe($keys('id'))
        ->and($keys('ko'))->toBe($keys('id'));
});

test('validation messages follow the UI language, with friendly field names', function (?string $locale, string $message) {
    $request = $this->actingAs(User::factory()->create());

    if ($locale !== null) {
        $request = $request->withUnencryptedCookie('locale', $locale);
    }

    $request->post(route('trials.store'), [])
        ->assertSessionHasErrors(['batch_number' => $message]);
})->with([
    'default (id)' => [null, 'Nomor Batch wajib diisi.'],
    'en' => ['en', 'The batch number field is required.'],
    'ko' => ['ko', '배치 번호 항목은 필수입니다.'],
]);

test('server-side wizard messages are translated', function () {
    app()->setLocale('ko');
    expect(__('messages.toast.trial_created'))->toBe('트라이얼이 생성되었습니다.');

    app()->setLocale('en');
    expect(__('messages.completeness.no_decision', ['name' => 'Weight']))
        ->toBe('Parameter Weight has no decision yet.');
});
