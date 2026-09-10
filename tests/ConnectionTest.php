<?php

namespace SocraNext\Statamic\Tests;

use SocraNext\Statamic\Support\Connection;
use SocraNext\Statamic\Support\StateStore;

class ConnectionTest extends TestCase
{
    use \Statamic\Testing\Concerns\FakesRoles;
    public function test_handshake_is_single_use_and_only_hashes_are_persisted(): void
    {
        $connection = app(Connection::class);
        $state = $connection->begin();
        $token = str_repeat('a', 64);
        $this->postJson('/api/socranext/v1/connect/token', compact('state', 'token'))->assertOk()->assertJson(['ok' => true]);
        $this->postJson('/api/socranext/v1/connect/token', ['state' => $state, 'token' => str_repeat('b', 64)])->assertForbidden();
        $this->assertTrue($connection->accepts($token));
        $stored = file_get_contents(config('socranext.state_path'));
        $this->assertStringNotContainsString($token, $stored);
        $this->assertStringNotContainsString($state, $stored);
        $this->getJson('/api/socranext/v1/status')->assertUnauthorized();
        $this->withHeader('x-socranext-token', $token)->getJson('/api/socranext/v1/status')->assertOk()->assertJsonPath('cms_type', 'statamic');
    }

    public function test_expired_or_wrong_state_cannot_replace_a_connection(): void
    {
        $connection = app(Connection::class);
        $state = $connection->begin();
        $this->assertFalse($connection->receive(str_repeat('e', 64), str_repeat('a', 64)));
        app(StateStore::class)->put('connect_state', ['hash' => hash('sha256', $state), 'expires' => time()-1]);
        $this->assertFalse($connection->receive($state, str_repeat('a', 64)));
        $this->assertFalse($connection->connected());
    }

    public function test_conflicting_credentials_reject_and_disconnect_keeps_content(): void
    {
        $connection = app(Connection::class);
        $connection->receive($connection->begin(), str_repeat('a', 64));
        app(StateStore::class)->put('faqs', ['42' => ['questions' => [['question' => 'Q', 'answer' => 'A']]]]);
        $this->withHeaders(['x-socranext-token' => str_repeat('a',64), 'Authorization' => 'Bearer '.str_repeat('b',64)])->getJson('/api/socranext/v1/status')->assertUnauthorized();
        $connection->disconnect();
        $this->assertFalse($connection->accepts(str_repeat('a',64)));
        $this->assertArrayHasKey('42', app(StateStore::class)->get('faqs'));
    }

    public function test_state_transaction_rolls_back_when_callback_fails(): void
    {
        $store = app(StateStore::class);
        $store->put('sequence', 7);
        try { $store->transaction(function (&$data) { $data['sequence'] = 8; throw new \RuntimeException('fail'); }); } catch (\RuntimeException) {}
        $this->assertSame(7, $store->get('sequence'));
    }

    public function test_control_panel_requires_login(): void
    {
        $this->get('/cp/socranext')->assertRedirect();
    }

    public function test_control_panel_requires_configure_permission(): void
    {
        $user = \Statamic\Facades\User::make()->id('editor')->email('editor@example.com');
        $this->setTestRoles(['editor' => ['access cp']]);
        $user->assignRole('editor');
        $this->actingAs($user)->getJson('/cp/socranext')->assertForbidden();
    }

    public function test_control_panel_can_be_configured_by_a_native_admin(): void
    {
        config(['socranext.frontend.mode' => 'manual']);
        $user = \Statamic\Facades\User::make()->id('admin')->email('admin@example.com')->set('super', true);
        $this->actingAs($user)->get('/cp/socranext')->assertOk()->assertSee('Connect with SocraNext');
        $this->actingAs($user)->post('/cp/socranext/readiness', ['frontend_ready' => 1])->assertRedirect();
        $this->assertTrue(app(\SocraNext\Statamic\Support\Readiness::class)->ready());
    }

    public function test_disconnect_revokes_an_existing_credential_through_cp(): void
    {
        $connection = app(Connection::class);
        $token = str_repeat('c', 64);
        $connection->receive($connection->begin(), $token);
        $admin = \Statamic\Facades\User::make()->id('admin')->email('admin@example.com')->set('super', true);
        $this->actingAs($admin)->post('/cp/socranext/disconnect')->assertRedirect();
        $this->assertFalse($connection->accepts($token));
    }

    public function test_cp_connect_posts_the_contract_and_requires_a_completed_callback(): void
    {
        $connection = app(Connection::class);
        $token = str_repeat('d', 64);
        \Illuminate\Support\Facades\Http::fake(function ($request) use ($connection, $token) {
            $this->assertSame('https://backend.socranext.ai/connect-site', $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertSame('https://example.com', $request['site']);
            $this->assertSame('statamic', $request['cms']);
            $this->assertTrue($connection->receive($request['state'], $token));
            return \Illuminate\Support\Facades\Http::response(['success' => true]);
        });
        $admin = \Statamic\Facades\User::make()->id('admin')->email('admin@example.com')->set('super', true);
        $this->actingAs($admin)->post('/cp/socranext/connect')->assertRedirect()->assertSessionHas('socranext_message', 'SocraNext is connected.');
        $this->assertTrue($connection->accepts($token));
        $this->assertFalse($connection->pending());
    }

    public function test_success_response_alone_cannot_make_a_failed_reconnect_look_successful(): void
    {
        // An old credential alone cannot make a failed reconnect look successful.
        $connection = app(Connection::class);
        $token = str_repeat('d', 64);
        $connection->receive($connection->begin(), $token);
        $admin = \Statamic\Facades\User::make()->id('admin')->email('admin@example.com')->set('super', true);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['success' => true])]);
        $this->actingAs($admin)->post('/cp/socranext/connect')->assertRedirect()->assertSessionHasErrors('connection');
        $this->assertTrue($connection->accepts($token));
    }
}
