<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id','description','quantity','unit_price','total',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'float',
        'total'      => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
