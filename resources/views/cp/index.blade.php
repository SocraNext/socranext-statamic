@extends('statamic::layout')
@php use function Statamic\trans as __; @endphp
@section('title', 'SocraNext')

@push('head')
<style>@include('socranext::cp.styles')
@include('socranext::cp.space-styles')</style>
@endpush

@section('content')
<div class="sncp">
    <header class="sncp-header">
        <div><p class="sncp-eyebrow">{{ __('socranext::cp.eyebrow') }}</p><h1><img class="sncp-logo sncp-logo-light" src="{{ asset('vendor/socranext/brand/socranext-logo.svg') }}" alt="SocraNext"><img class="sncp-logo sncp-logo-dark" src="{{ asset('vendor/socranext/brand/socranext-logo-on-dark.svg') }}" alt="SocraNext"> <span class="sncp-preview">{{ __('socranext::cp.preview') }}</span></h1><p class="sncp-lead">{{ __('socranext::cp.intro') }}</p></div>
        @if($websiteUrl)<a class="sncp-button sncp-button-secondary" href="{{ $websiteUrl }}" target="_blank" rel="noopener noreferrer">{{ __('socranext::cp.visit') }} <span aria-hidden="true">↗</span></a>@endif
    </header>
    @if(session('socranext_message'))<div class="sncp-notice" role="status">{{ session('socranext_message') }}</div>@endif
    @foreach($errors->all() as $error)<div class="sncp-notice sncp-notice-error" role="alert">{{ $error }}</div>@endforeach

    <section class="sncp-hero sncp-space" aria-labelledby="sncp-heading">
        @include('socranext::cp.space')
        <div class="sncp-hero-copy">
            <span class="sncp-connection"><span class="sncp-dot {{ $connected ? 'is-connected' : '' }}" aria-hidden="true"></span>{{ __('socranext::cp.'.($connected ? 'connected' : 'not_connected')) }} <span aria-hidden="true">·</span> {{ $websiteLabel }}</span>
            <h2 id="sncp-heading">{{ __('socranext::cp.'.($connected ? 'connected_title' : 'connect_title')) }}</h2>
            <p>{{ __('socranext::cp.'.($connected ? 'connected_intro' : 'connect_intro')) }}</p>
            <div class="sncp-actions">
                @if($connected)
                    <a class="sncp-button sncp-button-primary" href="{{ config('socranext.platform_url') }}" target="_blank" rel="noopener noreferrer">{{ __('socranext::cp.open') }} <span aria-hidden="true">↗</span></a>
                @else
                    <form method="post" action="{{ cp_route('socranext.connect') }}">@csrf<button class="sncp-button sncp-button-primary" type="submit">{{ __('socranext::cp.connect') }} <span aria-hidden="true">→</span></button></form>
                    <a class="sncp-hero-link" href="{{ config('socranext.platform_url') }}" target="_blank" rel="noopener noreferrer">{{ __('socranext::cp.open') }} ↗</a>
                @endif
            </div>
        </div>
        <div class="sncp-visual" aria-hidden="true"><img src="{{ asset('vendor/socranext/brand/socranext-mark.svg') }}" alt=""><span>SocraNext × Statamic</span></div>
    </section>

    <div class="sncp-status-grid">
        <section class="sncp-status"><span class="sncp-status-icon {{ $connected ? 'is-good' : '' }}" aria-hidden="true">{{ $connected ? '✓' : '↗' }}</span><div><h2>{{ __('socranext::cp.connection') }} <span class="sncp-pill {{ $connected ? 'is-good' : '' }}">{{ __('socranext::cp.'.($connected ? 'connected' : 'not_connected')) }}</span></h2><p>{{ __('socranext::cp.'.($connected ? 'connected_detail' : 'connect_detail')) }}</p></div></section>
        <section class="sncp-status"><span class="sncp-status-icon {{ $ready ? 'is-good' : '' }}" aria-hidden="true">{{ $ready ? '✓' : '3' }}</span><div><h2>{{ __('socranext::cp.website_output') }} <span class="sncp-pill {{ $ready ? 'is-good' : '' }}">{{ __('socranext::cp.'.($ready ? 'checked' : 'review_needed')) }}</span></h2><p>{{ __('socranext::cp.'.($ready ? 'output_detail' : 'review_detail')) }}</p></div></section>
    </div>

    <section class="sncp-panel" aria-labelledby="sncp-setup-title">
        <div class="sncp-panel-heading"><h2 id="sncp-setup-title">{{ __('socranext::cp.setup') }}</h2><p>{{ __('socranext::cp.setup_intro') }}</p></div>
        <ol class="sncp-steps">
            <li><span class="sncp-step-number {{ $connected ? 'is-good' : '' }}" aria-hidden="true">{{ $connected ? '✓' : '1' }}</span><div class="sncp-step-copy"><h3>{{ __('socranext::cp.step_connect') }}</h3><p>{{ __('socranext::cp.step_connect_detail') }}</p><span class="sncp-domain">{{ $websiteUrl ?: $websiteLabel }}</span></div></li>
            <li><span class="sncp-step-number {{ $setupComplete ? 'is-good' : '' }}" aria-hidden="true">{{ $setupComplete ? '✓' : '2' }}</span><div class="sncp-step-copy"><h3>{{ __('socranext::cp.step_prepare') }}</h3><p>{{ __('socranext::cp.step_prepare_detail') }}</p><p class="sncp-check-summary {{ $setupComplete ? 'is-good' : '' }}">{{ __('socranext::cp.'.($setupComplete ? 'setup_checked' : 'setup_pending')) }}</p>
                <details class="sncp-details"><summary>{{ __('socranext::cp.technical_details') }}</summary><div class="sncp-details-body">
                    <ul class="sncp-checks">@foreach($setupChecks as $key => $ok)<li><span>{{ __('socranext::cp.'.$key) }}</span><strong class="{{ $ok ? 'is-good' : 'sncp-needs-attention' }}">{{ __('socranext::cp.'.($ok ? 'pass' : 'todo')) }}</strong></li>@endforeach</ul>
                    @if($siteNames)<p class="sncp-language-list">{{ implode(' · ', $siteNames) }}</p>@endif
                    <p>{{ __('socranext::cp.developer_help') }}</p><pre><code>php please socranext:install</code></pre><p>{{ __('socranext::cp.faq_help') }}</p>
                    <pre v-pre><code>@{{ socranext:faq }}
@{{ socranext:metadata }}</code></pre>
                    <p>{{ __('socranext::cp.diagnostics') }}</p><pre><code>php please socranext:doctor --json</code></pre>
                    <a class="sncp-text-link" href="https://github.com/SocraNext/socranext-statamic#installation-for-a-development-or-pilot-site" target="_blank" rel="noopener noreferrer">{{ __('socranext::cp.guide') }} ↗</a>
                </div></details>
            </div></li>
            <li><span class="sncp-step-number {{ $ready ? 'is-good' : '' }}" aria-hidden="true">{{ $ready ? '✓' : '3' }}</span><div class="sncp-step-copy"><h3>{{ __('socranext::cp.step_check') }}</h3><p>{{ __('socranext::cp.step_check_detail') }}</p>
                <form method="post" action="{{ cp_route('socranext.readiness') }}" class="sncp-readiness-form">@csrf<input type="hidden" name="frontend_ready" value="0"><label class="sncp-checkbox"><input type="checkbox" name="frontend_ready" value="1" @checked($frontendChecked)><span>{{ __('socranext::cp.frontend_label') }}</span></label><button class="sncp-button sncp-button-secondary" type="submit">{{ __('socranext::cp.save') }}</button></form>
            </div></li>
        </ol>
    </section>

    @if($connected)
        <details class="sncp-panel sncp-settings"><summary>{{ __('socranext::cp.connection_settings') }}</summary><div class="sncp-settings-body">
            <div><div><h3>{{ __('socranext::cp.reconnect_title') }}</h3><p>{{ __('socranext::cp.reconnect_detail') }}</p></div><form method="post" action="{{ cp_route('socranext.connect') }}">@csrf<button class="sncp-button sncp-button-secondary" type="submit">{{ __('socranext::cp.reconnect') }}</button></form></div>
            <div><div><h3>{{ __('socranext::cp.disconnect_title') }}</h3><p>{{ __('socranext::cp.disconnect_detail') }}</p></div><form method="post" action="{{ cp_route('socranext.disconnect') }}">@csrf<button class="sncp-button sncp-button-danger" type="submit">{{ __('socranext::cp.disconnect_title') }}</button></form></div>
        </div></details>
    @endif
    <footer class="sncp-footer"><p><strong>{{ __('socranext::cp.free') }}</strong> <span aria-hidden="true">·</span> {{ __('socranext::cp.subscription') }}</p><span>SocraNext {{ $version }}</span></footer>
</div>
@endsection
