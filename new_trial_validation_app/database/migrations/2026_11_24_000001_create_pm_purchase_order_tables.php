<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Order documents (FR.PRC.001.00) of the Projects module: the PO
 * header + items, a vendor contact book that pre-fills them, and a small
 * key/value settings table holding the PO template (company block, numbering
 * pattern, billing/notice text, signers).
 *
 * Additive only, like every pm_* migration: every table is guarded with
 * Schema::hasTable and there are no foreign keys (project/user ids are plain
 * columns), so a re-run is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pm_purchase_orders')) {
            Schema::create('pm_purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->string('legacy_id', 40)->nullable()->unique();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('quotation_id')->nullable()->index();
                $table->string('po_no', 120)->unique();
                $table->date('po_date')->nullable();
                $table->string('vendor_name', 255)->default('');
                $table->string('currency', 8)->default('IDR');
                $table->text('vendor_address')->nullable();
                $table->string('attn', 150)->default('');
                $table->string('tel', 80)->default('');
                $table->string('email', 190)->default('');
                $table->string('quotation_no', 120)->default('');
                $table->string('subject', 255)->default('');
                $table->decimal('tax_percent', 5, 2)->default(0);
                $table->text('terms')->nullable();
                $table->string('delivery', 255)->default('');
                $table->string('lead_time', 255)->default('');
                $table->text('note')->nullable();
                $table->string('ship_name', 190)->default('');
                $table->text('ship_address')->nullable();
                $table->string('ship_tel', 80)->default('');
                $table->string('ship_attn', 150)->default('');
                $table->boolean('use_signature')->default(false);
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->string('created_by_name', 100)->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_purchase_order_items')) {
            Schema::create('pm_purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->text('description');
                $table->decimal('qty', 14, 2)->default(1);
                $table->string('unit', 30)->default('Unit');
                $table->decimal('unit_price', 18, 2)->default(0);
                $table->date('due_date')->nullable();
            });
        }

        if (! Schema::hasTable('pm_vendor_contacts')) {
            Schema::create('pm_vendor_contacts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->unique();
                $table->string('contact', 150)->default('');
                $table->string('phone', 80)->default('');
                $table->string('email', 190)->default('');
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('pm_settings')) {
            Schema::create('pm_settings', function (Blueprint $table) {
                $table->string('key', 80)->primary();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: pm_* tables are additive and never dropped by a rollback.
    }
};
