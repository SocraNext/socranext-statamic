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
        <div><p class="sncp-eyebrow">{{ __('socranext::cp.eyebrow') }}</p><h1><img class="sncp-logo sncp-logo-light" src="{{ asset('vendor/socranext/brand/socranext-logo.svg') }}" alt="SocraNext"><img class="sncp-logo sncp-logo-dark" src="{{ asset('vendor/socranext/brand/socranext-logo-on-dark.svg') }}" alt="SocraNext"></h1><p class="sncp-lead">{{ __('socranext::cp.intro') }}</p></div>
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
        <section class="sncp-status"><span class="sncp-status-icon {{ $ready ? 'is-good' : '' }}" aria-hidden="true">{{ $ready ? '✓' : '↗' }}</span><div><h2>{{ __('socranext::cp.website_output') }} <span class="sncp-pill {{ $ready ? 'is-good' : '' }}">{{ __('socranext::cp.'.($ready ? 'checked' : ($connected ? 'review_needed' : 'automatic_setup'))) }}</span></h2><p>{{ __('socranext::cp.'.($ready ? ($automatic ? 'output_detail' : 'manual_output_detail') : ($connected ? 'review_detail' : 'automatic_setup_detail'))) }}</p></div></section>
    </div>

    <details class="sncp-panel sncp-settings">
        <summary>{{ __('socranext::cp.technical_details') }}</summary>
        <div class="sncp-details-body">
            <p>{{ __('socranext::cp.'.($automatic ? 'automatic_details' : 'manual_details')) }}</p>
            <ul class="sncp-checks">@foreach($setupChecks as $key => $ok)<li><span>{{ __('socranext::cp.'.$key) }}</span><strong class="{{ $ok ? 'is-good' : 'sncp-needs-attention' }}">{{ __('socranext::cp.'.($ok ? 'pass' : 'todo')) }}</strong></li>@endforeach</ul>
            @if($siteNames)<p class="sncp-language-list">{{ implode(' · ', $siteNames) }}</p>@endif
            @if(!$automatic)
                <form method="post" action="{{ cp_route('socranext.readiness') }}" class="sncp-readiness-form">@csrf<input type="hidden" name="frontend_ready" value="0"><label class="sncp-checkbox"><input type="checkbox" name="frontend_ready" value="1" @checked($frontendChecked)><span>{{ __('socranext::cp.frontend_label') }}</span></label><button class="sncp-button sncp-button-secondary" type="submit">{{ __('socranext::cp.save') }}</button></form>
            @endif
            <a class="sncp-text-link" href="https://github.com/SocraNext/socranext-statamic#installation" target="_blank" rel="noopener noreferrer">{{ __('socranext::cp.guide') }} ↗</a>
        </div>
    </details>

    @if($connected)
        <details class="sncp-panel sncp-settings"><summary>{{ __('socranext::cp.connection_settings') }}</summary><div class="sncp-settings-body">
            <div><div><h3>{{ __('socranext::cp.reconnect_title') }}</h3><p>{{ __('socranext::cp.reconnect_detail') }}</p></div><form method="post" action="{{ cp_route('socranext.connect') }}">@csrf<button class="sncp-button sncp-button-secondary" type="submit">{{ __('socranext::cp.reconnect') }}</button></form></div>
            <div><div><h3>{{ __('socranext::cp.disconnect_title') }}</h3><p>{{ __('socranext::cp.disconnect_detail') }}</p></div><form method="post" action="{{ cp_route('socranext.disconnect') }}">@csrf<button class="sncp-button sncp-button-danger" type="submit">{{ __('socranext::cp.disconnect_title') }}</button></form></div>
        </div></details>
    @endif
    <footer class="sncp-footer"><p><strong>{{ __('socranext::cp.free') }}</strong> <span aria-hidden="true">·</span> {{ __('socranext::cp.subscription') }}</p><span>SocraNext {{ $version }}</span></footer>
</div>
@endsection
