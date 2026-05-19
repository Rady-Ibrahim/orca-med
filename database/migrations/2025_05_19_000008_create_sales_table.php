<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upload_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->date('sold_at');
            $table->string('import_hash')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sold_at']);
            $table->index(['province_id', 'sold_at']);
            $table->unique('import_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
