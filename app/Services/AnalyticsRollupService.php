<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AnalyticsRollupService
{
    /**
     * @param  array<int>  $productIds
     */
    public function rebuildForProductIds(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $now = now()->toDateTimeString();

        DB::statement("
            INSERT INTO analytics_product_rollups (product_id, total_quantity, sale_count, updated_at)
            SELECT product_id, SUM(quantity), COUNT(*), ?
            FROM sales WHERE product_id IN ($placeholders) GROUP BY product_id
            ON DUPLICATE KEY UPDATE
                total_quantity = VALUES(total_quantity),
                sale_count = VALUES(sale_count),
                updated_at = VALUES(updated_at)
        ", array_merge([$now], $productIds));

        DB::statement("
            INSERT INTO analytics_product_province_rollups (product_id, province_id, total_quantity, sale_count, updated_at)
            SELECT product_id, province_id, SUM(quantity), COUNT(*), ?
            FROM sales WHERE product_id IN ($placeholders) GROUP BY product_id, province_id
            ON DUPLICATE KEY UPDATE
                total_quantity = VALUES(total_quantity),
                sale_count = VALUES(sale_count),
                updated_at = VALUES(updated_at)
        ", array_merge([$now], $productIds));

        DB::statement("
            INSERT INTO analytics_product_pharmacy_rollups (product_id, pharmacy_id, province_id, total_quantity, sale_count, updated_at)
            SELECT product_id, pharmacy_id, province_id, SUM(quantity), COUNT(*), ?
            FROM sales WHERE product_id IN ($placeholders) GROUP BY product_id, pharmacy_id, province_id
            ON DUPLICATE KEY UPDATE
                total_quantity = VALUES(total_quantity),
                sale_count = VALUES(sale_count),
                updated_at = VALUES(updated_at)
        ", array_merge([$now], $productIds));
    }

    public function rebuildAll(): void
    {
        $ids = DB::table('sales')->distinct()->pluck('product_id')->all();
        $this->rebuildForProductIds($ids);
    }
}
