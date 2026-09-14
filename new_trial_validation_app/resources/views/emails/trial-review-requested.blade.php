@component('mail::message')
# Trial Needs Your Review

Hi {{ $reviewerName }},

A trial has been submitted for review by the **{{ $department }}** department, and you have been assigned as the reviewer.

- **Trial Code:** {{ $trial->trial_code }}
- **Product:** {{ $trial->product_name }}

@component('mail::button', ['url' => $reviewUrl])
Open & Review Trial
@endcomponent

Please click the button above to view the trial details and submit your review.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
