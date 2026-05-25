<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('uploaded_by')->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('province_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('quantity');
            $table->decimal('discount', 10, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('upload_batches', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['province_id']);
            $table->dropColumn(['company_id', 'supplier_id', 'province_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'discount']);
        });
    }
};
