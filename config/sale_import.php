<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expected Excel columns (header row, case-insensitive)
    |--------------------------------------------------------------------------
    */
    'columns' => [
        'product_code' => ['product_code', 'code', 'كود_الصنف', 'كود الصنف'],
        'quantity' => ['quantity', 'qty', 'الكمية', 'كمية'],
        'pharmacy_name' => ['pharmacy_name', 'pharmacy', 'اسم_الصيدلية', 'الصيدلية'],
        'supplier_name' => ['supplier_name', 'supplier', 'اسم_المورد', 'المورد'],
        'province_name' => ['province_name', 'province', 'المحافظة', 'محافظة'],
        'sold_at' => ['sold_at', 'sale_date', 'date', 'تاريخ_البيع', 'التاريخ'],
    ],

    'required_fields' => [
        'product_code',
        'quantity',
        'pharmacy_name',
        'province_name',
        'sold_at',
    ],

    'chunk_size' => 500,

    'max_file_size_mb' => 10,

    'allowed_extensions' => ['xlsx', 'xls', 'csv'],

];
