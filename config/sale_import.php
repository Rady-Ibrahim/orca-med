<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expected Excel columns (header row, case-insensitive)
    |--------------------------------------------------------------------------
    */
    'columns' => [
        'outlet_name' => ['outlet_name', 'اسم_المخزن', 'اسم المخزن', 'اسم_الصيدلية', 'الصيدلية', 'المخزن', 'الاسم'],
        'product_name' => ['product_name', 'name', 'اسم_الصنف', 'اسم الصنف', 'الصنف'],
        'quantity' => ['quantity', 'qty', 'الكمية', 'كمية'],
        'sold_at' => ['sold_at', 'sale_date', 'date', 'تاريخ_البيع', 'التاريخ'],
        'unit_price' => ['unit_price', 'price', 'السعر'],
        'discount' => ['discount', 'الخصم', 'خصم 1'],
    ],

    'required_fields' => [
        'outlet_name',
        'product_name',
        'quantity',
    ],

    'chunk_size' => 500,

    'max_file_size_mb' => 10,

    'allowed_extensions' => ['xlsx', 'xls', 'csv'],

];
