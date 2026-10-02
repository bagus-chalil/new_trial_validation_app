@component('mail::message')
# {{ __('emails.approval_requested.heading') }}

{{ __('emails.common.greeting', ['name' => $approverName]) }}

{{ __('emails.approval_requested.intro') }}

- **{{ __('emails.common.trial_code') }}:** {{ $trial->trial_code }}
- **{{ __('emails.common.product') }}:** {{ $trial->product_name }}

@component('mail::button', ['url' => $approvalUrl])
{{ __('emails.approval_requested.button') }}
@endcomponent

{{ __('emails.approval_requested.outro') }}

{{ __('emails.common.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
