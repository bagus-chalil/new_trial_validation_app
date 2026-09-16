<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the report's drafter (TrialLineConfigurationReport::updated_by_user_id
 * — whoever last saved the report's content) whenever it's sent back for
 * revision. Regardless of which stage did the returning — Approved(PIE) or
 * Checked(PROD) — this always goes straight to the drafter, never back
 * through the sign-off chain itself. Fired from
 * ReturnTrialLineConfigurationReport, alongside the existing in-app
 * ActivityLog RETURN row.
 */
class TrialLineConfigurationReturnedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trial $trial,
        public string $drafterName,
        public ?string $returnedByStage,
        public string $reason,
        public string $reportUrl,
    ) {}

    public function build(): self
    {
        return $this->subject("Line Configuration Report Returned for Revision ({$this->trial->trial_code})")
            ->markdown('emails.trial-line-configuration-returned');
    }
}
