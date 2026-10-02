@component('mail::message')
# {{ __('emails.review_requested.heading') }}

{{ __('emails.common.greeting', ['name' => $reviewerName]) }}

{{ __('emails.review_requested.intro', ['department' => '**'.$department.'**']) }}

- **{{ __('emails.common.trial_code') }}:** {{ $trial->trial_code }}
- **{{ __('emails.common.product') }}:** {{ $trial->product_name }}

@component('mail::button', ['url' => $reviewUrl])
{{ __('emails.review_requested.button') }}
@endcomponent

{{ __('emails.review_requested.outro') }}

{{ __('emails.common.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
