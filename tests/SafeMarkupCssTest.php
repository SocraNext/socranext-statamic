<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Support\Connection;

class SafeMarkupCssTest extends TestCase
{
    private function authenticate(): void
    {
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('c', 64));
        $this->withHeader('x-socranext-token', str_repeat('c', 64));
    }

    public function test_child_selectors_and_media_ranges_are_preserved(): void
    {
        $this->authenticate();
        $css = '.socranext-qalist > h2 { color: #6d28d9; }'."\n"
            .'@media (width > 40rem) { .socranext-qalist > h2 { font-size: 2rem; } }';

        $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_css' => $css])
            ->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/socranext/v1/faq-custom')->assertOk()->assertJsonPath('custom_css', $css);
    }

    public function test_shared_platform_faq_css_can_be_saved_unchanged(): void
    {
        $this->authenticate();
        // Actual buildFaqStylerCss output from SocraNextRebuild 345245bf:
        // shared defaults, toggleBg #6d28d9, empty translation overrides.
        $css = file_get_contents(__DIR__.'/fixtures/shared-platform-faq-default.css');
        $this->assertStringContainsString('.socranext-qalist > h2', $css);

        $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_css' => $css])
            ->assertOk()->assertJsonPath('success', true);
        $this->getJson('/api/socranext/v1/faq-custom')->assertOk()->assertJsonPath('custom_css', $css);
    }

    public function test_html_breakout_and_executable_css_stay_rejected_without_changing_saved_styles(): void
    {
        $this->authenticate();
        $safe = '.socranext-qalist > h2 { color: #6d28d9; }';
        $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_css' => $safe])->assertOk();

        foreach ([
            '</style><script>alert(1)</script>',
            '</StYlE ><img src=x onerror=alert(1)>',
            '.x { width: expression(alert(1)); }',
            '.x { background: url(javascript:alert(1)); }',
            '.x { background: url(vbscript:msgbox(1)); }',
            '.x { -moz-binding: url(https://example.invalid/binding.xml); }',
            '.x { behavior: url(https://example.invalid/payload.htc); }',
        ] as $unsafe) {
            $this->postJson('/api/socranext/v1/store-faq-custom', ['custom_css' => $unsafe])
                ->assertStatus(422)->assertJsonValidationErrors('custom_css');
            $this->getJson('/api/socranext/v1/faq-custom')->assertOk()->assertJsonPath('custom_css', $safe);
        }
    }
}
