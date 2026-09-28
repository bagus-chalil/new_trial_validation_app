<?php

namespace Tests\Feature;

use App\Models\IpcLog;
use App\Models\MasterProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VisionTestSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['vision.base_url' => 'http://vision.test']);
    }

    private function fakeVision(array $overrides = []): void
    {
        Http::fake(['vision.test/api/analyze' => Http::response([
            'status' => 'OK',
            'extracted_date_code' => '140627',
            'raw_ocr_text' => 'EXP 140627',
            'confidence' => 0.97,
            'format_valid' => true,
            'engine_used' => 'paddleocr',
            'processing_time_ms' => 24123.4,
            ...$overrides,
        ])]);
    }

    private function analyze(array $payload = [])
    {
        return $this->postJson(route('vision-test.analyze'), [
            'photo' => UploadedFile::fake()->image('tube.jpg'),
            'field_type' => 'tube_exp_date',
            ...$payload,
        ]);
    }

    public function test_staff_and_admin_can_open_the_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('vision-test.index'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('vision-test.index'))->assertOk();
    }

    public function test_approver_cannot_use_the_sandbox(): void
    {
        $this->actingAs(User::factory()->approver()->create());

        $this->get(route('vision-test.index'))->assertForbidden();
        $this->analyze()->assertForbidden();
    }

    public function test_matching_exp_is_pass_and_nothing_is_persisted(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeVision();

        $this->analyze(['exp_date' => '2027-06-14'])
            ->assertOk()
            ->assertJson(['decision' => 'PASS', 'ocr_value' => '140627', 'expected_value' => '140627']);

        $this->assertSame(0, IpcLog::count());
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_missing_exp_is_review(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeVision();

        $this->analyze()->assertOk()->assertJson(['decision' => 'REVIEW', 'expected_value' => null]);
    }

    public function test_low_confidence_stays_review_even_when_value_matches(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeVision(['status' => 'LOW_CONFIDENCE', 'confidence' => 0.84]);

        $this->analyze(['exp_date' => '2027-06-14'])->assertOk()->assertJson(['decision' => 'REVIEW']);
    }

    public function test_mfd_uses_the_selected_products_shelf_life(): void
    {
        $this->actingAs(User::factory()->create());
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'P1', 'shelf_life_months' => 36, 'is_active' => true]);
        $this->fakeVision(['extracted_date_code' => '140624']);

        $this->analyze(['field_type' => 'tube_mfd_date', 'master_product_id' => $product->id, 'exp_date' => '2027-06-14'])
            ->assertOk()
            ->assertJson(['decision' => 'PASS', 'ocr_value' => '140624', 'computed_value' => '140627']);
    }

    public function test_mfd_without_a_product_is_review(): void
    {
        $this->actingAs(User::factory()->create());
        $this->fakeVision(['extracted_date_code' => '140624']);

        $this->analyze(['field_type' => 'tube_mfd_date', 'exp_date' => '2027-06-14'])
            ->assertOk()
            ->assertJson(['decision' => 'REVIEW', 'computed_value' => null]);
    }

    public function test_unknown_field_type_and_deleted_product_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'P1', 'is_active' => true]);
        $product->delete();

        $this->analyze(['field_type' => 'something_else', 'master_product_id' => $product->id])
            ->assertJsonValidationErrors(['field_type', 'master_product_id']);
    }
}
