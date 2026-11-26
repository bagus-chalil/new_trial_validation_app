<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\FgMapping;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;

/** A person confirms that an official FG code really is this products row. */
class VerifyFgMapping
{
    public function __invoke(FgMapping $mapping, Product $product, User $user): FgMapping
    {
        $mapping->product_id = $product->id;
        $mapping->verified_at = Carbon::now();
        $mapping->verified_by_user_id = $user->id;
        $mapping->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => 'UPDATE',
            'module' => 'FG_MAPPING',
            'record_id' => (string) $mapping->id,
            'record_label' => $mapping->fg_code,
            'old_data' => null,
            'new_data' => json_encode(['product_id' => $product->id, 'product_name' => $product->product_name]),
        ]);

        return $mapping;
    }
}
