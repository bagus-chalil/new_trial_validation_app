<?php

namespace Tests\Feature;

use App\Models\MasterLine;
use App\Models\MasterProduct;
use App\Models\MasterProductBulkCode;
use App\Models\MasterTestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

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
}
