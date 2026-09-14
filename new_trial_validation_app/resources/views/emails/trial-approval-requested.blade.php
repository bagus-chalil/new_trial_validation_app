@component('mail::message')
# Trial Waiting for Your Approval

Hi {{ $approverName }},

All department reviewers have completed their review for the following trial, and it is now waiting for your approval.

- **Trial Code:** {{ $trial->trial_code }}
- **Product:** {{ $trial->product_name }}

@component('mail::button', ['url' => $approvalUrl])
Open & Approve Trial
@endcomponent

Please click the button above to view the trial details and submit your approval decision.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
