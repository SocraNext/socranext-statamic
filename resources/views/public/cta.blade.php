@if(($settings['showCta'] ?? true) && (!empty($settings['ctaTitle']) || (!empty($settings['ctaButtonText']) && $safe->url((string) ($settings['ctaButtonUrl'] ?? '')))))
<aside class="sn-cta"><p class="sn-cta-title">{{ $settings['ctaTitle'] ?? '' }}</p><p class="sn-cta-text">{{ $settings['ctaText'] ?? '' }}</p>@if(!empty($settings['ctaButtonText']) && $safe->url((string) ($settings['ctaButtonUrl'] ?? '')))<a class="sn-cta-button" href="{{ $safe->url($settings['ctaButtonUrl']) }}">{{ $settings['ctaButtonText'] }}</a>@endif</aside>
@endif
