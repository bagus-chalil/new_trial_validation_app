<?php

namespace App\Mail;

use App\Models\Trial;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the assigned approver right after SaveDepartmentReview promotes a
 * trial to Ready for Approval (the last pending department review completed)
 * — alongside the existing in-app Notification. Carries a direct link to the
 * trial's Report Summary page (trials.report.show), where the Approve/Need
 * Revision/Reject action is embedded per the app's detail-then-act principle.
 */
class TrialApprovalRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Trial $trial,
        public string $approverName,
        public string $approvalUrl,
    ) {}

    public function build(): self
    {
        return $this->subject("Trial {$this->trial->trial_code} Waiting for Your Approval")
            ->markdown('emails.trial-approval-requested');
    }
}
