<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('wholesale');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('province_id')->constrained()->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->timestamp('sensitive_unlock_expires_at')->nullable()->after('remember_token');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('sensitive_view_password')->nullable()->after('is_active');
        });

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            $table->string('license_number')->nullable()->unique()->after('name');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('upload_batch_id')->constrained()->nullOnDelete();
        });

        Schema::table('upload_batches', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('uploaded_by')->constrained()->nullOnDelete();
        });

        Schema::create('analytics_product_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_quantity')->default(0);
            $table->unsignedInteger('sale_count')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('analytics_product_province_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_quantity')->default(0);
            $table->unsignedInteger('sale_count')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['product_id', 'province_id']);
        });

        Schema::create('analytics_product_pharmacy_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total_quantity')->default(0);
            $table->unsignedInteger('sale_count')->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['product_id', 'pharmacy_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_product_pharmacy_rollups');
        Schema::dropIfExists('analytics_product_province_rollups');
        Schema::dropIfExists('analytics_product_rollups');

        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropUnique(['license_number']);
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('license_number');
        });
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('sensitive_view_password');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('sensitive_unlock_expires_at');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::dropIfExists('warehouses');
    }
};
