<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SocraNext — Statamic</title>
<style>body{font:16px/1.6 system-ui,sans-serif;color:#182222;background:#f6f8f8;margin:0}main{max-width:760px;margin:48px auto;padding:32px;background:white}h1{font-size:32px}h2{font-size:21px;margin-top:30px}a{color:#126c64}button{font:inherit;border:0;background:#126c64;color:white;padding:10px 18px;cursor:pointer}section{border-top:1px solid #dce3e3;margin-top:24px;padding-top:8px}.message{padding:12px;background:#eff8f5}.error{padding:12px;background:#fff0ed}code{font-size:14px;overflow-wrap:anywhere}</style>
</head><body><main>
<a href="{{ cp_route('dashboard') }}">← Statamic</a><h1>SocraNext</h1>
<p>Connect this website to your SocraNext workspace. An active SocraNext subscription is required.</p>
@if(session('socranext_message'))<p class="message" role="status">{{ session('socranext_message') }}</p>@endif
@foreach($errors->all() as $error)<p class="error" role="alert">{{ $error }}</p>@endforeach
<section><h2>{{ $connected ? 'Connected' : 'Connect your website' }}</h2>
<p>Website: <strong>{{ config('socranext.site_url') }}</strong></p>
<form method="post" action="{{ cp_route('socranext.connect') }}">@csrf<button>{{ $connected ? 'Reconnect SocraNext' : 'Connect with SocraNext' }}</button></form>
<p><a href="{{ config('socranext.platform_url') }}" target="_blank" rel="noopener">Open SocraNext</a></p>
</section>
<section><h2>Website setup</h2><p>Have your website developer configure the collections, assets and templates, then check a published article and FAQ on the public website. Connection and website readiness are separate.</p>
<p>Configuration: <code>config/socranext.php</code>. Run <code>php please socranext:install</code> to prepare the managed article collection.</p>
<form method="post" action="{{ cp_route('socranext.readiness') }}">@csrf<input type="hidden" name="frontend_ready" value="0"><label><input type="checkbox" name="frontend_ready" value="1" @checked($ready)> Public article and FAQ output has been checked</label><p><button>Save website status</button></p></form>
</section>
@if($connected)<section><h2>Disconnect</h2><p>This revokes the connection and keeps your published content.</p><form method="post" action="{{ cp_route('socranext.disconnect') }}">@csrf<button>Disconnect</button></form></section>@endif
<p><small>SocraNext {{ $version }} · Development preview</small></p>
</main></body></html>
