<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL to change foreign key constraint
        // First drop any existing foreign key on upload_batch_id
        try {
            DB::statement('ALTER TABLE sales DROP FOREIGN KEY sales_upload_batch_id_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist or have different name, continue
        }
        
        // Add new cascade constraint
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_upload_batch_id_foreign FOREIGN KEY (upload_batch_id) REFERENCES upload_batches(id) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE sales DROP FOREIGN KEY sales_upload_batch_id_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist
        }
        
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_upload_batch_id_foreign FOREIGN KEY (upload_batch_id) REFERENCES upload_batches(id) ON DELETE SET NULL');
    }
};
