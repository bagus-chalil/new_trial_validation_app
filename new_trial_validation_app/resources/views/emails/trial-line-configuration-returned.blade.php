@php($report = '**'.__('line_config.title').'**')
@component('mail::message')
# {{ __('emails.line_config_returned.heading') }}

{{ __('emails.common.greeting', ['name' => $drafterName]) }}

{{ $returnedByStage
    ? __('emails.line_config_returned.intro_by', ['report' => $report, 'stage' => $returnedByStage])
    : __('emails.line_config_returned.intro', ['report' => $report]) }}

- **{{ __('emails.common.trial_code') }}:** {{ $trial->trial_code }}
- **{{ __('emails.common.product') }}:** {{ $trial->product_name }}

**{{ __('emails.line_config_returned.reason') }}**
{{ $reason }}

@component('mail::button', ['url' => $reportUrl])
{{ __('emails.line_config_returned.button') }}
@endcomponent

{{ __('emails.line_config_returned.outro') }}

{{ __('emails.common.thanks') }}<br>
{{ config('app.name') }}
@endcomponent
