<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One signature box of a Check Tooling form: prni (Prepared by), rni
 * (Reviewed by), pi (PIE check), production (Production review). Signed in
 * this app with the user's own password (signature_method=password); imported
 * rows keep the old app's drawn signature image (signature_method=legacy) for
 * display only.
 *
 * @property int $id
 * @property int $tooling_id
 * @property string $role
 * @property bool $approved
 * @property int|null $signer_user_id
 * @property string $signer_name
 * @property Carbon|null $sign_date
 * @property Carbon|null $signed_at
 * @property string|null $note
 * @property string $signature_method
 * @property string|null $signature_image
 * @property string $signed_by_account
 */
class ToolingApproval extends Model
{
    protected $table = 'pm_tooling_approvals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'sign_date' => 'date',
            'signed_at' => 'datetime',
        ];
    }
}
