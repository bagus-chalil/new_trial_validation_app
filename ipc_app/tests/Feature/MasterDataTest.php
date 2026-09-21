<?php

namespace Tests\Feature;

use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use App\Models\MasterTestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private function fakeExcelUpload(array $headings, array $rows, string $filename = 'import.xlsx'): UploadedFile
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

    // --- Master Line ---

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/masters/lines')->assertRedirect('/login');
        $this->get('/masters/products')->assertRedirect('/login');
        $this->get('/masters/test-types')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_list_lines(): void
    {
        MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/masters/lines')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('masters/lines/index')->has('lines.data', 1));
    }

    public function test_line_can_be_created(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/masters/lines', ['category' => 'Filling', 'area' => 'Filling Room', 'code' => 'FL 01', 'name' => 'Filling 01', 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('master_lines', ['code' => 'FL 01', 'name' => 'Filling 01']);
    }

    public function test_line_can_be_updated_by_id(): void
    {
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->post('/masters/lines', ['id' => $line->id, 'category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Renamed', 'is_active' => false])
            ->assertRedirect();

        $this->assertDatabaseHas('master_lines', ['id' => $line->id, 'name' => 'Renamed', 'is_active' => false]);
    }

    public function test_line_can_be_soft_deleted(): void
    {
        $line = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Make Up 01', 'is_active' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->delete("/masters/lines/{$line->id}")->assertRedirect();

        $this->assertSoftDeleted('master_lines', ['id' => $line->id]);
        $this->assertDatabaseHas('master_lines', ['id' => $line->id, 'deleted_by' => $user->id]);
    }

    public function test_line_requires_all_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/masters/lines', [])
            ->assertSessionHasErrors(['category', 'area', 'code', 'name']);
    }

    // --- Master Product + Bulk Codes ---

    public function test_authenticated_user_can_list_products_with_bulk_code_counts(): void
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        MasterProductBulkCode::create(['master_product_id' => $product->id, 'bulk_code' => 'BC-1', 'no_batch' => 'NB-1', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/masters/products')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->component('masters/products/index')
                    ->where('products.data.0.bulk_codes_count', 1)
                    ->has('products.data.0.bulk_codes', 1),
            );
    }

    public function test_product_can_be_created(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/masters/products', ['fg_code' => 'FG-2', 'product_name' => 'Product 2', 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('master_products', ['fg_code' => 'FG-2', 'product_name' => 'Product 2']);
    }

    public function test_product_fg_code_must_be_unique(): void
    {
        MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->post('/masters/products', ['fg_code' => 'FG-1', 'product_name' => 'Duplicate', 'is_active' => true])
            ->assertSessionHasErrors('fg_code');
    }

    public function test_product_can_be_soft_deleted(): void
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->delete("/masters/products/{$product->id}")->assertRedirect();

        $this->assertSoftDeleted('master_products', ['id' => $product->id]);
    }

    public function test_bulk_code_can_be_added_to_a_product(): void
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->post("/masters/products/{$product->id}/bulk-codes", ['bulk_code' => 'BC-1', 'no_batch' => 'NB-1', 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('master_product_bulk_codes', ['master_product_id' => $product->id, 'bulk_code' => 'BC-1', 'no_batch' => 'NB-1']);
    }

    public function test_bulk_code_must_be_unique_per_product_but_not_globally(): void
    {
        $productA = MasterProduct::create(['fg_code' => 'FG-A', 'product_name' => 'Product A', 'is_active' => true]);
        $productB = MasterProduct::create(['fg_code' => 'FG-B', 'product_name' => 'Product B', 'is_active' => true]);
        MasterProductBulkCode::create(['master_product_id' => $productA->id, 'bulk_code' => 'BC-1', 'no_batch' => 'NB-1', 'is_active' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/masters/products/{$productA->id}/bulk-codes", ['bulk_code' => 'BC-1', 'no_batch' => 'NB-2', 'is_active' => true])
            ->assertSessionHasErrors('bulk_code');

        $this->actingAs($user)
            ->post("/masters/products/{$productB->id}/bulk-codes", ['bulk_code' => 'BC-1', 'no_batch' => 'NB-2', 'is_active' => true])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_bulk_code_no_batch_is_optional(): void
    {
        $product = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Product 1', 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->post("/masters/products/{$product->id}/bulk-codes", ['bulk_code' => 'BC-1', 'is_active' => true])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('master_product_bulk_codes', ['master_product_id' => $product->id, 'bulk_code' => 'BC-1', 'no_batch' => null]);
    }

    public function test_bulk_code_can_be_deleted_only_through_its_own_product(): void
    {
        $productA = MasterProduct::create(['fg_code' => 'FG-A', 'product_name' => 'Product A', 'is_active' => true]);
        $productB = MasterProduct::create(['fg_code' => 'FG-B', 'product_name' => 'Product B', 'is_active' => true]);
        $bulkCode = MasterProductBulkCode::create(['master_product_id' => $productA->id, 'bulk_code' => 'BC-1', 'no_batch' => 'NB-1', 'is_active' => true]);

        $user = User::factory()->create();

        $this->actingAs($user)->delete("/masters/products/{$productB->id}/bulk-codes/{$bulkCode->id}")->assertNotFound();
        $this->assertDatabaseHas('master_product_bulk_codes', ['id' => $bulkCode->id, 'deleted_at' => null]);

        $this->actingAs($user)->delete("/masters/products/{$productA->id}/bulk-codes/{$bulkCode->id}")->assertRedirect();
        $this->assertSoftDeleted('master_product_bulk_codes', ['id' => $bulkCode->id]);
    }

    // --- Master Test Type ---

    public function test_authenticated_user_can_list_test_types_with_categories(): void
    {
        MasterTestType::create(['name' => 'Leak Test', 'category' => MasterTestType::CATEGORY_LEAKAGE, 'is_active' => true]);

        $this->actingAs(User::factory()->create())
            ->get('/masters/test-types')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->component('masters/test-types/index')
                    ->has('testTypes.data', 1)
                    ->where('categories', MasterTestType::CATEGORIES),
            );
    }

    public function test_test_type_can_be_created(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/masters/test-types', ['name' => 'Functional Test', 'category' => MasterTestType::CATEGORY_FUNCTIONAL, 'is_active' => true])
            ->assertRedirect();

        $this->assertDatabaseHas('master_test_types', ['name' => 'Functional Test', 'category' => MasterTestType::CATEGORY_FUNCTIONAL]);
    }

    public function test_test_type_category_must_be_a_known_value(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/masters/test-types', ['name' => 'Bogus', 'category' => 'NotACategory', 'is_active' => true])
            ->assertSessionHasErrors('category');
    }

    public function test_test_type_can_be_soft_deleted(): void
    {
        $testType = MasterTestType::create(['name' => 'Leak Test', 'category' => MasterTestType::CATEGORY_LEAKAGE, 'is_active' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->delete("/masters/test-types/{$testType->id}")->assertRedirect();

        $this->assertSoftDeleted('master_test_types', ['id' => $testType->id]);
    }

    // --- Master Product template + import ---

    public function test_products_template_can_be_downloaded(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/masters/products/template')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_products_can_be_imported_creating_and_updating_products_and_bulk_codes(): void
    {
        $existing = MasterProduct::create(['fg_code' => 'FG-1', 'product_name' => 'Old Name', 'is_active' => true]);

        $file = $this->fakeExcelUpload(
            ['FG Code', 'Nama Produk', 'Status Produk', 'Bulk Code', 'No Batch', 'Status Bulk Code'],
            [
                ['FG-1', 'Updated Name', 'Aktif', 'BLK-1', 'BATCH-1', 'Aktif'],
                ['FG-2', 'Brand New Product', 'Aktif', '', '', 'Aktif'],
                ['', '', '', 'BLK-ORPHAN', '', ''],
            ],
        );

        $this->actingAs(User::factory()->create())
            ->post('/masters/products/import', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('master_products', ['id' => $existing->id, 'product_name' => 'Updated Name']);
        $this->assertDatabaseHas('master_product_bulk_codes', ['master_product_id' => $existing->id, 'bulk_code' => 'BLK-1', 'no_batch' => 'BATCH-1']);
        $this->assertDatabaseHas('master_products', ['fg_code' => 'FG-2', 'product_name' => 'Brand New Product']);
        $this->assertDatabaseMissing('master_product_bulk_codes', ['bulk_code' => 'BLK-ORPHAN']);
    }

    public function test_products_import_counts_a_product_repeated_across_rows_only_once(): void
    {
        $file = $this->fakeExcelUpload(
            ['FG Code', 'Nama Produk', 'Status Produk', 'Bulk Code', 'No Batch', 'Status Bulk Code'],
            [
                ['FG-4', 'Multi Bulk Product', 'Aktif', 'BLK-A', '', 'Aktif'],
                ['FG-4', 'Multi Bulk Product', 'Aktif', 'BLK-B', '', 'Aktif'],
            ],
        );

        $this->actingAs(User::factory()->create())
            ->post('/masters/products/import', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success', 'Import selesai. 1 produk baru, 0 produk diperbarui. 2 bulk code baru, 0 bulk code diperbarui.');

        $this->assertSame(1, MasterProduct::where('fg_code', 'FG-4')->count());
    }

    public function test_products_import_can_leave_bulk_code_blank(): void
    {
        $file = $this->fakeExcelUpload(
            ['FG Code', 'Nama Produk', 'Status Produk', 'Bulk Code', 'No Batch', 'Status Bulk Code'],
            [['FG-3', 'No Bulk Code Product', 'Aktif', '', '', '']],
        );

        $this->actingAs(User::factory()->create())
            ->post('/masters/products/import', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionDoesntHaveErrors();

        $product = MasterProduct::where('fg_code', 'FG-3')->firstOrFail();
        $this->assertSame(0, $product->bulkCodes()->count());
    }

    public function test_products_import_rejects_a_non_spreadsheet_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/masters/products/import', ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('file');
    }

    // --- Master Line template + import ---

    public function test_lines_template_can_be_downloaded(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/masters/lines/template')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_lines_can_be_imported_creating_and_updating(): void
    {
        $existing = MasterLine::create(['category' => 'Packing', 'area' => 'Make Up', 'code' => 'MU 01', 'name' => 'Old Line Name', 'is_active' => true]);

        $file = $this->fakeExcelUpload(
            ['Kategori', 'Area', 'Kode Line', 'Nama Line', 'Status Line'],
            [
                ['Packing', 'Make Up', 'MU 01', 'Updated Line Name', 'Aktif'],
                ['Filling', 'Filling Room', 'FL 01', 'Filling 01', 'Aktif'],
                ['Filling', '', 'FL 02', 'Missing Area', 'Aktif'],
            ],
        );

        $this->actingAs(User::factory()->create())
            ->post('/masters/lines/import', ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('master_lines', ['id' => $existing->id, 'name' => 'Updated Line Name']);
        $this->assertDatabaseHas('master_lines', ['code' => 'FL 01', 'name' => 'Filling 01']);
        $this->assertDatabaseMissing('master_lines', ['code' => 'FL 02']);
    }

    public function test_lines_import_rejects_a_non_spreadsheet_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/masters/lines/import', ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('file');
    }
}
