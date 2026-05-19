<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_batch_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('column')->nullable();
            $table->string('error_type');
            $table->text('message');
            $table->json('row_data')->nullable();
            $table->timestamps();

            $table->index('upload_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_batch_errors');
    }
};
