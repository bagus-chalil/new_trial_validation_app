<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a specific reviewer right after SubmitTrialForReview assigns them
 * to a department on a trial — one email per assigned reviewer, alongside
 * the existing in-app Notification. Carries a direct link to the trial's
 * Report Summary page (trials.report.show), where the review action is
 * embedded per the app's detail-then-act principle.
 */
class TrialReviewRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trial $trial,
        public string $reviewerName,
        public string $department,
        public string $reviewUrl,
    ) {}

    public function build(): self
    {
        return $this->subject("Trial {$this->trial->trial_code} Needs Your Review")
            ->markdown('emails.trial-review-requested');
    }
}
