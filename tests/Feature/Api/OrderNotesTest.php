<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Order\Models\Order;
use Tests\TestCase;

class OrderNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    private function mainHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Platform' => 'main',
        ];
    }

    public function test_notes_excludes_hesabfa_notes(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $order->addNote('پیام پشتیبانی', 'general', true);
        $order->addNote('فاکتور با موفقیت به حسابفا ارسال شد.', 'hesabfa', true);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->getJson("/api/orders/{$order->id}/notes");

        $response->assertOk()
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.type', 'general')
            ->assertJsonPath('notes.0.note', 'پیام پشتیبانی');
    }

    public function test_notes_returns_only_customer_notes(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $order->addNote('پیام پشتیبانی', 'general', true);
        $order->addNote('یادداشت داخلی', 'general', false);

        $response = $this->withHeaders($this->mainHeaders($user))
            ->getJson("/api/orders/{$order->id}/notes");

        $response->assertOk()
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.note', 'پیام پشتیبانی');
    }

    public function test_other_users_notes_are_not_accessible(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeaders($this->mainHeaders($other))
            ->getJson("/api/orders/{$order->id}/notes");

        $response->assertNotFound();
    }
}
