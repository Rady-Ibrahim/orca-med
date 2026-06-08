<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAlias;
use App\Models\Sale;
use App\Services\SaleImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

class MergeDuplicateProducts extends Command
{
    protected $signature = 'products:merge-duplicates {company_id? : Company ID to scope merge} {--dry-run : Preview merges without applying}';

    protected $description = 'Merge duplicate products that share the same brand/dose/form fingerprint';

    public function handle(): int
    {
        $companyId = $this->argument('company_id');
        $dryRun = (bool) $this->option('dry-run');
        $importService = app(SaleImportService::class);
        $extract = (new ReflectionClass($importService))->getMethod('extractProductMetadata');
        $extract->setAccessible(true);

        $query = Product::query()->withCount('sales');
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $groups = [];
        foreach ($query->get() as $product) {
            $meta = $extract->invoke($importService, $product->name);
            $fingerprint = $meta['fingerprint'] ?? '';
            if ($fingerprint === '' || $fingerprint === '||') {
                continue;
            }
            $groups[$product->company_id][$fingerprint][] = $product;
        }

        $merged = 0;

        foreach ($groups as $scopedCompanyId => $fingerprints) {
            foreach ($fingerprints as $fingerprint => $products) {
                $parts = explode('|', $fingerprint, 3);
                if (($parts[1] ?? '') === '') {
                    continue;
                }

                if (count($products) < 2) {
                    continue;
                }

                usort($products, function (Product $a, Product $b) {
                    return [$b->sales_count, $b->id] <=> [$a->sales_count, $a->id];
                });

                $canonical = $products[0];
                $duplicates = array_slice($products, 1);

                $this->line("Fingerprint {$fingerprint}");
                $this->line("  Keep: [{$canonical->id}] {$canonical->name} ({$canonical->sales_count} sales)");

                foreach ($duplicates as $duplicate) {
                    $this->line("  Merge: [{$duplicate->id}] {$duplicate->name} ({$duplicate->sales_count} sales)");

                    if ($dryRun) {
                        continue;
                    }

                    DB::transaction(function () use ($canonical, $duplicate) {
                        Sale::where('product_id', $duplicate->id)->update(['product_id' => $canonical->id]);

                        ProductAlias::updateOrCreate(
                            ['alias_name' => $duplicate->name],
                            ['product_id' => $canonical->id]
                        );

                        $duplicate->delete();
                    });

                    $merged++;
                }
            }
        }

        $this->info($dryRun
            ? 'Dry run complete. Re-run without --dry-run to apply merges.'
            : "Merged {$merged} duplicate product(s).");

        return self::SUCCESS;
    }
}
