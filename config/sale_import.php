<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expected Excel columns (header row, case-insensitive)
    |--------------------------------------------------------------------------
    */
    'columns' => [
        'outlet_name' => ['outlet_name', 'اسم_المخزن', 'اسم المخزن', 'اسم_الصيدلية', 'الصيدلية', 'المخزن', 'اسم_النقطة', 'النقطة', 'اسم', 'الاسم'],
        'product_name' => ['product_name', 'اسم_الصنف', 'اسم الصنف', 'الصنف', 'أنف', 'صنف'],
        'quantity' => ['quantity', 'qty', 'الكمية', 'كمية'],
        'sold_at' => ['sold_at', 'sale_date', 'date', 'تاريخ_البيع', 'التاريخ', 'ريخ', 'تاريخ'],
        'unit_price' => ['unit_price', 'price', 'السعر', 'عر'],
        'discount' => ['discount', 'الخصم', 'خصم 1'],
    ],

    'required_fields' => [
        'product_name',
        'quantity',
    ],

    'chunk_size' => 500,

    'max_file_size_mb' => 10,

    'allowed_extensions' => ['xlsx', 'xls', 'csv'],

];
