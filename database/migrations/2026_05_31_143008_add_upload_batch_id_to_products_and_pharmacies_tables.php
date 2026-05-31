<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('upload_batch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->foreignId('upload_batch_id')->nullable()->after('province_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['upload_batch_id']);
            $table->dropColumn('upload_batch_id');
        });

        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropForeign(['upload_batch_id']);
            $table->dropColumn('upload_batch_id');
        });
    }
};
