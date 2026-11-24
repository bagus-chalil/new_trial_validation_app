<?php

namespace App\Actions\Improvements;

use App\Models\Improvement;
use App\Models\User;
use Illuminate\Support\Carbon;

class DeleteImprovement
{
    public function __construct(private readonly LogImprovementActivity $log) {}

    public function __invoke(Improvement $improvement, User $user): void
    {
        $improvement->deleted_at = Carbon::now();
        $improvement->deleted_by = $user->id;
        $improvement->save();

        ($this->log)($user, 'DELETE', $improvement, ['title' => $improvement->title]);
    }
}
