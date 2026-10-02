<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to whoever the form's creator/editor assigns as Approved(PIE) or
 * Checked(PROD) on a trial's Line Configuration Report — one email per
 * assignment, whenever SaveTrialLineConfigurationReport detects the
 * assigned user id changed. Carries a direct link to the trial's Report
 * Summary page, where the actual Approve/Checked action lives (see
 * MarkTrialLineConfigurationReportSignOff) — same "email is just the
 * notification, the click happens in-app" pattern as
 * TrialReviewRequestedMail/TrialApprovalRequestedMail.
 */
class TrialLineConfigurationSignOffRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trial $trial,
        public string $assigneeName,
        public string $fieldLabel,
        public string $reportUrl,
    ) {
        // Captured now, while the triggering request's locale (SetLocale) is
        // active — the recipient's own language isn't stored anywhere, and a
        // queued render would otherwise fall back to the config default.
        $this->locale(app()->getLocale());
    }

    public function build(): self
    {
        return $this->subject(__('emails.line_config_signoff.subject', ['label' => $this->fieldLabel, 'code' => $this->trial->trial_code]))
            ->markdown('emails.trial-line-configuration-signoff-requested');
    }
}
