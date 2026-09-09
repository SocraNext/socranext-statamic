<?php

namespace SocraNext\Statamic\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use SocraNext\Statamic\Rendering\Renderer;
use SocraNext\Statamic\Support\{Connection, StateStore};

class ApiPayloadTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        // Register host routes before Statamic's native frontend catch-all.
        \Statamic\Statamic::pushWebRoutes(static function () {
            foreach (['contact', 'cp/socranext/payload-test', 'api/socranext/v10/payload-test', 'other/api/socranext/v1/payload-test', 'api/socranext/v1/form-payload-test'] as $path) {
                $route = Route::post($path, fn (Request $request) => response()->json($request->all()));
                // Statamic intentionally replaces Laravel trimming in its control panel.
                if (str_starts_with($path, 'cp/')) $route->middleware(\Statamic\Http\Middleware\CP\TrimStrings::class);
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = app(Kernel::class);
        $this->assertContains(TrimStrings::class, $kernel->getGlobalMiddleware());
        $this->assertContains(ConvertEmptyStringsToNull::class, $kernel->getGlobalMiddleware());

        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('a', 64));
        $this->withHeader('x-socranext-token', str_repeat('a', 64));
    }

    public function test_signed_javascript_and_empty_style_values_survive_global_normalizers(): void
    {
        $keys = sodium_crypto_sign_keypair();
        config(['socranext.code_signing_public_key' => base64_encode(sodium_crypto_sign_publickey($keys))]);
        $code = "\n  window.socraNextTest = 1;\n\n";
        $signature = base64_encode(sodium_crypto_sign_detached("socranext.code.v1\nexample.com\nfaq\n".$code, sodium_crypto_sign_secretkey($keys)));
        $this->postJson('/api/socranext/v1/store-faq-custom', [
            'custom_js' => $code, 'custom_js_sig' => $signature,
            'title_text' => 'Original title', 'title_text_i18n' => ['en' => 'Questions'],
        ])->assertOk()->assertJsonPath('success', true);
        $style = app(Renderer::class)->style('faq');
        $this->assertSame($code, $style['custom_js']);
        $this->assertStringContainsString($code, app(Renderer::class)->scripts('faq', $style));

        $cleared = ['custom_js' => '', 'custom_js_sig' => '', 'custom_css' => '', 'custom_html' => '', 'title_text' => '', 'title_text_i18n' => ['en' => '']];
        $this->postJson('/api/socranext/v1/store-faq-custom', $cleared)->assertOk();
        $stored = app(StateStore::class)->get('styles')['faq'];
        $this->assertCount(count($cleared), $stored);
        foreach ($cleared as $key => $value) $this->assertSame($value, $stored[$key]);
        $this->assertSame('', app(Renderer::class)->scripts('faq', app(Renderer::class)->style('faq')));
    }

    public function test_llms_files_retain_exact_json_text_and_support_explicit_empty_clearing(): void
    {
        $payload = ['llmsTxt' => "# Example\n\n  Indented text  \n", 'llmsFullTxt' => "\n# Full\r\n\r\n"];
        $this->postJson('/api/socranext/v1/llmstxt', $payload)->assertOk();
        $this->assertSame($payload, app(StateStore::class)->get('llms'));
        $this->get('/llms.txt')->assertOk()->assertContent($payload['llmsTxt']);
        $this->get('/llms-full.txt')->assertOk()->assertContent($payload['llmsFullTxt']);

        $this->postJson('/api/socranext/v1/llmstxt', ['llmsTxt' => '', 'llmsFullTxt' => ''])->assertOk();
        $this->assertSame(['llmsTxt' => '', 'llmsFullTxt' => ''], app(StateStore::class)->get('llms'));
        // Clearing removes the public document instead of serving an empty file.
        $this->get('/llms.txt')->assertNotFound();
    }

    public function test_subdirectory_api_preserves_text_but_website_cp_and_similar_paths_still_normalize(): void
    {
        $this->withServerVariables([
            'SCRIPT_NAME' => '/customer/index.php',
            'PHP_SELF' => '/customer/index.php',
            'SCRIPT_FILENAME' => '/var/www/public/index.php',
        ]);
        $text = "\n# Website in a subdirectory\n";
        $response = $this->postJson('https://example.com/customer/api/socranext/v1/llmstxt', ['llmsTxt' => $text])->assertOk();
        $this->assertSame('/customer', $response->baseRequest->getBaseUrl());
        $this->assertSame('/api/socranext/v1/llmstxt', $response->baseRequest->getPathInfo());
        $this->assertSame($text, app(StateStore::class)->get('llms')['llmsTxt']);

        foreach (['contact', 'cp/socranext/payload-test', 'api/socranext/v10/payload-test', 'other/api/socranext/v1/payload-test'] as $path) {
            $this->postJson('https://example.com/customer/'.$path, ['text' => "  Hello\n", 'empty' => '', 'nested' => ['empty' => '', 'text' => ' World ']])
                ->assertOk()->assertExactJson(['text' => 'Hello', 'empty' => null, 'nested' => ['empty' => null, 'text' => 'World']]);
        }

        // Browser forms are unaffected even when posted to the API namespace.
        $this->post('https://example.com/customer/api/socranext/v1/form-payload-test', ['text' => ' Hello ', 'empty' => ''])
            ->assertOk()->assertExactJson(['text' => 'Hello', 'empty' => null]);
    }
}
