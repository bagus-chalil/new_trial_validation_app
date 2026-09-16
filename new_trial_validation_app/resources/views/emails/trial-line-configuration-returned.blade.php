@component('mail::message')
# Line Configuration Report Returned for Revision

Hi {{ $drafterName }},

The **Line Configuration Report** you filled in for the trial below has been sent back for revision{{ $returnedByStage ? " by {$returnedByStage}" : '' }} — this is only about that report, not the trial's own review/approval decision.

- **Trial Code:** {{ $trial->trial_code }}
- **Product:** {{ $trial->product_name }}

**Reason for return:**
{{ $reason }}

@component('mail::button', ['url' => $reportUrl])
Open Line Configuration Report
@endcomponent

Please revise the report and resubmit it for sign-off.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
