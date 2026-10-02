@php($report = '**'.__('line_config.title').'**')
@component('mail::message')
# {{ __('emails.line_config_signoff.heading', ['label' => $fieldLabel]) }}

{{ __('emails.common.greeting', ['name' => $assigneeName]) }}

{{ __('emails.line_config_signoff.intro', ['label' => '**'.$fieldLabel.'**', 'report' => $report]) }}

- **{{ __('emails.common.trial_code') }}:** {{ $trial->trial_code }}
- **{{ __('emails.common.product') }}:** {{ $trial->product_name }}

@component('mail::button', ['url' => $reportUrl])
{{ __('emails.line_config_signoff.button') }}
@endcomponent

{{ __('emails.line_config_signoff.outro', ['label' => '**'.$fieldLabel.'**', 'report' => $report]) }}

{{ __('emails.common.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
