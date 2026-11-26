<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An issued IM (Instruksi Mutu) number (QA). Numbers are normalised
 * (UPPER CASE, single spaces) so they match IPC's startup_checks value.
 *
 * @property int $id
 * @property string $number
 * @property string $brand_code
 * @property string $product_part
 * @property int $version_no
 * @property int|null $replaces_id
 * @property string|null $replaces_number
 * @property string|null $revision_reason
 * @property Carbon|null $issue_date
 * @property int|null $product_id
 * @property list<string>|null $fg_codes
 * @property string $line_machine
 * @property string $customer
 * @property string $customer_ref
 * @property string $status
 * @property string|null $void_reason
 * @property string $issued_by_name
 */
class ImNumber extends Model
{
    public const STATUSES = ['Active', 'Superseded', 'Void'];

    protected $table = 'vd_im_numbers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'fg_codes' => 'array',
        ];
    }
}
