<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Vendor contact book that pre-fills a Purchase Order "To" block. */
class VendorContact extends Model
{
    protected $table = 'pm_vendor_contacts';

    protected $guarded = [];
}
