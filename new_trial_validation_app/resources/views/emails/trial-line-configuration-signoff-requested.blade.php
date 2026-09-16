@component('mail::message')
# Line Configuration Report: {{ $fieldLabel }} Sign-Off

Hi {{ $assigneeName }},

You've been named **{{ $fieldLabel }}** on the **Line Configuration Report** for the trial below — this is the production-line setup form attached to the trial, not the trial's own review/approval decision. No action is needed on the overall trial itself, only a sign-off on this specific report.

- **Trial Code:** {{ $trial->trial_code }}
- **Product:** {{ $trial->product_name }}

@component('mail::button', ['url' => $reportUrl])
Open Line Configuration Report
@endcomponent

Please click the button above, scroll to the **Line Configuration Report** section, then use its **{{ $fieldLabel }}** button to confirm — the date will be recorded automatically.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
