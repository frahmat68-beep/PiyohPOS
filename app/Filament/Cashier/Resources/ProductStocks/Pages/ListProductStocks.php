<?php

namespace App\Filament\Cashier\Resources\ProductStocks\Pages;

use App\Filament\Cashier\Resources\ProductStocks\ProductStockResource;
use Filament\Resources\Pages\ListRecords;

class ListProductStocks extends ListRecords
{
    protected static string $resource = ProductStockResource::class;
}
