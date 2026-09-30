<?php

namespace Tests\Feature;

use App\Imports\ImportPlan;
use App\Imports\MasterProductsImportProcessor;
use App\Models\MasterImport;
use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

/**
 * Queue is sync in tests, so each POST runs its job (validate / commit) before the response.
 */
class MasterImportTest extends TestCase
{
    use RefreshDatabase;

    private const PRODUCT_HEADINGS = ['FG Code', 'Nama Produk', 'Bulk Code', 'Shelf Life (Bulan)'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function excel(array $headings, array $rows, string $filename = 'import.xlsx'): UploadedFile
    {
        $export = new class($headings, $rows) implements FromArray, WithHeadings
        {
            public function __construct(private array $headings, private array $rows) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        return UploadedFile::fake()->createWithContent($filename, Excel::raw($export, ExcelWriter::XLSX));
    }

    private function upload(User $user, UploadedFile $file, string $type = MasterImport::TYPE_PRODUCTS): array
    {
        $this->actingAs($user)->postJson("/masters/imports/{$type}", ['file' => $file])->assertCreated();

        return $this->actingAs($user)->getJson('/masters/imports/'.MasterImport::latest('id')->value('id'))->assertOk()->json();
    }

    public function test_validation_previews_changes_and_reports_issues_without_writing_master_data(): void
    {
        MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Old Name', 'is_active' => true]);
        $admin = User::factory()->admin()->create();

        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [
            ['FG-1', 'New Name', 'BLK-1', 36],
            ['FG-2', 'Brand New', '', ''],
            ['FG-2', 'Brand New', '', ''],           // identical -> skipped
            ['', 'No FG Code', 'BLK-X', ''],        // error
            ['FG-3', 'Bad Shelf Life', '', 'abc'],  // error
        ]));

        $this->assertSame('validated', $state['status']);
        $this->assertSame(5, $state['total_rows']);
        $this->assertSame(2, $state['valid_rows']);
        $this->assertSame(2, $state['error_rows']);
        $this->assertSame(1, $state['warning_rows']);
        $this->assertTrue($state['can_commit']);
        $this->assertSame(1, $state['preview']['products_new']);
        $this->assertSame(1, $state['preview']['products_updated']);
        $this->assertSame(1, $state['preview']['bulk_codes_new']);
        $this->assertSame(1, $state['preview']['skipped_duplicates']);
        $this->assertSame([5, 6], array_column($state['issues']['errors'], 'row_number'));
        $this->assertStringContainsString('FG Code wajib diisi', $state['issues']['errors'][0]['message']);
        $this->assertStringContainsString('Shelf Life harus bilangan bulat', $state['issues']['errors'][1]['message']);
        $this->assertStringContainsString('Duplikat identik dengan baris 3', $state['issues']['warnings'][0]['message']);

        // Nothing written yet.
        $this->assertDatabaseHas('master_products', ['fg_code' => 'FG-1', 'product_name' => 'Old Name']);
        $this->assertDatabaseMissing('master_products', ['fg_code' => 'FG-2']);
        $this->assertSame(0, MasterProductBulkCode::count());
    }

    public function test_commit_applies_valid_rows_and_skips_error_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [
            ['FG-1', 'Product One', 'BLK-1', 36],
            ['FG-1', 'Product One', 'BLK-2', ''],
            ['621106005', 'Numeric FG Code', '', 24],
            ['', 'Missing FG', '', ''],
        ]));

        $done = $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")->assertOk()->json();

        $this->assertSame('completed', $done['status']);
        $this->assertSame(2, $done['result']['products_new']);
        $this->assertSame(2, $done['result']['bulk_codes_new']);
        $product = MasterProduct::where('fg_code', 'FG-1')->firstOrFail();
        $this->assertSame(36, $product->shelf_life_months);
        $this->assertEqualsCanonicalizing(['BLK-1', 'BLK-2'], $product->bulkCodes()->pluck('bulk_code')->all());
        $this->assertDatabaseHas('master_products', ['fg_code' => '621106005', 'shelf_life_months' => 24]);
        $this->assertSame(2, MasterProduct::count());
        Storage::disk('local')->assertMissing(MasterImport::find($state['id'])->file_path);
    }

    public function test_blank_shelf_life_keeps_existing_value_and_first_name_wins_for_a_repeated_fg_code(): void
    {
        MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Same', 'shelf_life_months' => 30, 'is_active' => true]);
        $admin = User::factory()->admin()->create();

        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [
            ['FG-1', 'Same', 'BLK-1', ''],
            ['FG-1', 'Different Name', 'BLK-2', ''],
        ]));
        $this->assertSame(2, $state['valid_rows']);
        $this->assertSame(0, $state['warning_rows']);
        $this->assertSame(1, $state['preview']['products_unchanged']);

        $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")->assertOk();

        $product = MasterProduct::where('fg_code', 'FG-1')->firstOrFail();
        $this->assertSame('Same', $product->product_name);
        $this->assertSame(30, $product->shelf_life_months);
        $this->assertSame(2, $product->bulkCodes()->count());
    }

    public function test_an_import_file_without_the_shelf_life_column_still_works(): void
    {
        $admin = User::factory()->admin()->create();
        $state = $this->upload($admin, $this->excel(['FG Code', 'Nama Produk', 'Bulk Code'], [['FG-9', 'Old Template', 'BLK-9']]));

        $this->assertSame('validated', $state['status']);
        $this->assertSame(1, $state['valid_rows']);
    }

    public function test_a_file_missing_required_columns_fails_with_a_clear_message(): void
    {
        $admin = User::factory()->admin()->create();
        $state = $this->upload($admin, $this->excel(['Kode', 'Nama'], [['FG-1', 'X']]));

        $this->assertSame('failed', $state['status']);
        $this->assertStringContainsString('Kolom wajib tidak ditemukan: FG Code, Nama Produk', $state['error_message']);
        $this->assertFalse($state['can_commit']);
    }

    public function test_cancelling_a_validated_import_writes_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['FG-1', 'Product', '', '']]));

        $cancelled = $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/cancel")->assertOk()->json();

        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, MasterProduct::count());
        $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")->assertStatus(422);
    }

    public function test_a_commit_that_fails_midway_is_rolled_back_entirely_and_can_be_retried(): void
    {
        $admin = User::factory()->admin()->create();
        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [
            ['FG-1', 'Product One', 'BLK-1', ''],
            ['FG-2', 'Product Two', '', ''],
        ]));

        $this->app->bind(MasterProductsImportProcessor::class, fn () => new class extends MasterProductsImportProcessor
        {
            public function apply(ImportPlan $plan, callable $progress): array
            {
                parent::apply($plan, $progress);
                throw new RuntimeException('Simulated DB failure');
            }
        });

        $failed = $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")->assertOk()->json();
        $failed = $this->actingAs($admin)->getJson("/masters/imports/{$failed['id']}")->json();

        $this->assertSame('failed', $failed['status']);
        $this->assertStringContainsString('rollback', $failed['error_message']);
        $this->assertTrue($failed['can_commit']);
        $this->assertSame(0, MasterProduct::count());
        $this->assertSame(0, MasterProductBulkCode::count());

        $this->app->offsetUnset(MasterProductsImportProcessor::class);
        $done = $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")->assertOk()->json();
        $this->assertSame('completed', $done['status']);
        $this->assertSame(2, MasterProduct::count());
    }

    public function test_a_soft_deleted_product_is_restored_with_a_warning(): void
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Deleted', 'is_active' => true]);
        $product->delete();
        $admin = User::factory()->admin()->create();

        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['FG-1', 'Deleted', '', '']]));
        $this->assertSame(1, $state['preview']['products_restored']);
        $this->assertStringContainsString('Recycle Bin', $state['issues']['warnings'][0]['message']);

        $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")->assertOk();

        $this->assertNotSoftDeleted('master_products', ['id' => $product->id]);
    }

    public function test_a_commit_is_blocked_while_another_commit_of_the_same_master_is_running(): void
    {
        $admin = User::factory()->admin()->create();
        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['FG-1', 'Product', '', '']]));
        MasterImport::create([
            'user_id' => $admin->id, 'type' => MasterImport::TYPE_PRODUCTS, 'status' => MasterImport::STATUS_COMMITTING,
            'original_filename' => 'other.xlsx', 'file_path' => 'imports/other.xlsx',
        ]);

        $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")
            ->assertStatus(409)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'other.xlsx'));
    }

    public function test_a_new_upload_supersedes_the_users_unfinished_preview(): void
    {
        $admin = User::factory()->admin()->create();
        $first = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['FG-1', 'A', '', '']]));
        $second = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['FG-2', 'B', '', '']]));

        $this->assertSame('cancelled', MasterImport::find($first['id'])->status);
        $this->actingAs($admin)->getJson('/masters/imports/latest/master_products')->assertJsonPath('id', $second['id']);
    }

    public function test_stale_previews_are_expired_and_their_files_removed_on_the_next_upload(): void
    {
        $admin = User::factory()->admin()->create();
        $stale = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['FG-1', 'A', '', '']]));
        $stalePath = MasterImport::find($stale['id'])->file_path;
        MasterImport::whereKey($stale['id'])->update(['user_id' => User::factory()->admin()->create()->id, 'updated_at' => now()->subDays(2)]);

        $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['FG-2', 'B', '', '']]));

        Storage::disk('local')->assertMissing($stalePath);
        $this->assertSame('cancelled', MasterImport::find($stale['id'])->status);
        $this->actingAs($admin)->postJson("/masters/imports/{$stale['id']}/commit")->assertStatus(422);
    }

    public function test_issue_report_can_be_downloaded(): void
    {
        $admin = User::factory()->admin()->create();
        $state = $this->upload($admin, $this->excel(self::PRODUCT_HEADINGS, [['', 'No FG', '', '']]));

        $this->actingAs($admin)->get($state['issues_url'])
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_a_non_spreadsheet_file_is_rejected(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson('/masters/imports/master_products', ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_non_admins_cannot_import(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/masters/imports/master_products', ['file' => $this->excel(self::PRODUCT_HEADINGS, [['FG-1', 'A', '', '']])])
            ->assertForbidden();
    }

    public function test_lines_import_creates_updates_and_skips_a_repeated_code(): void
    {
        $existing = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Old', 'is_active' => true]);
        $admin = User::factory()->admin()->create();

        $state = $this->upload($admin, $this->excel(['Kategori', 'Area', 'Kode Line', 'Nama Line', 'Status Line'], [
            ['Packing', 'Make Up', 'MU 01', 'Updated', 'Aktif'],
            ['Filling', 'Filling Room', 'FL 01', 'Filling 01', 'Nonaktif'],
            ['Filling', 'Filling Room', 'FL 01', 'Different', 'Aktif'],
            ['Filling', '', 'FL 02', 'Missing Area', 'Aktif'],
        ]), MasterImport::TYPE_LINES);

        $this->assertSame(2, $state['valid_rows']);
        $this->assertSame(1, $state['error_rows']);
        $this->assertSame(1, $state['warning_rows']);

        $this->actingAs($admin)->postJson("/masters/imports/{$state['id']}/commit")->assertOk()->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('master_lines', ['id' => $existing->id, 'name' => 'Updated']);
        $this->assertDatabaseHas('master_lines', ['code' => 'FL 01', 'name' => 'Filling 01', 'is_active' => false]);
        $this->assertDatabaseMissing('master_lines', ['code' => 'FL 02']);
    }

    public function test_products_template_includes_the_shelf_life_column(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/masters/products/template')->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertSame(self::PRODUCT_HEADINGS, $sheet->rangeToArray('A1:D1')[0]);
        @unlink($path);
    }

    public function test_product_form_saves_and_validates_shelf_life(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/masters/products', ['fg_code' => 'FG-1', 'product_name' => 'P', 'shelf_life_months' => 36, 'is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('master_products', ['fg_code' => 'FG-1', 'shelf_life_months' => 36]);

        $this->actingAs($admin)->post('/masters/products', ['fg_code' => 'FG-2', 'product_name' => 'P', 'shelf_life_months' => 0, 'is_active' => true])
            ->assertSessionHasErrors('shelf_life_months');
    }
}
