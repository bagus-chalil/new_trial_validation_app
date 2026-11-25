<?php

namespace App\Actions\Trials;

use App\Actions\Notifications\CreateNotification;
use App\Mail\TrialLineConfigurationSignOffRequestedMail;
use App\Models\LineConfigurationLane;
use App\Models\Trial;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Tells every user who may sign a Line Configuration Report stage (see
 * LineConfigurationLane::userMaySign()) that it is now their turn — an in-app
 * notification plus an e-mail each. Nobody is assigned on the form any more
 * (2026-11-25), so the whole eligible group is notified and whoever signs
 * first completes the stage. Never throws: notifications and mail must not
 * block the save/sign-off that triggered them.
 */
class NotifyLineConfigurationSigners
{
    /**
     * @param  'approved_pie'|'checked_prod'  $stageKey
     */
    public function __invoke(Trial $trial, string $stageKey): void
    {
        $label = LineConfigurationLane::label($stageKey, $stageKey === 'checked_prod' ? 'Acknowledged (PROD)' : 'Approved (PIE Leader)');

        try {
            $signers = LineConfigurationLane::signersQuery($stageKey)->get(['id', 'name', 'email']);
        } catch (Throwable) {
            return;
        }

        foreach ($signers as $signer) {
            (new CreateNotification)([
                'user_id' => (int) $signer->id,
                'trial_id' => $trial->id,
                'title' => 'Line Configuration Report Needs Your Sign-off',
                'message' => "Trial {$trial->trial_code} - {$trial->product_name}: Line Configuration Report menunggu tanda tangan Anda ({$label}).",
                'type' => 'approval',
            ]);

            $this->mail($trial, $signer, $label);
        }
    }

    private function mail(Trial $trial, User $signer, string $label): void
    {
        if (! $signer->email) {
            return;
        }

        try {
            Mail::to($signer->email)->send(new TrialLineConfigurationSignOffRequestedMail(
                trial: $trial,
                assigneeName: $signer->name ?: $signer->email,
                fieldLabel: $label,
                reportUrl: route('trials.report.show', $trial->id),
            ));
        } catch (Throwable) {
            // Email delivery must never block the main workflow.
        }
    }
}
