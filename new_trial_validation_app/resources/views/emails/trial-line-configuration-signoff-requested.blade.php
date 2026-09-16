@component('mail::message')
# {{ $fieldLabel }} Needed

Hi {{ $assigneeName }},

You have been assigned as **{{ $fieldLabel }}** on the Line Configuration Report for the following trial.

- **Trial Code:** {{ $trial->trial_code }}
- **Product:** {{ $trial->product_name }}

@component('mail::button', ['url' => $reportUrl])
Open Trial & Approve
@endcomponent

Please click the button above, then use the {{ $fieldLabel }} button on the Line Configuration Report section to confirm — the date will be recorded automatically.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
