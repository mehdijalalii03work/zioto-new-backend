<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\User;
use App\Services\ShahkarService;
use App\Services\SmsIrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shetabit\Multipay\Payment as ShetabitPayment;
use Tests\TestCase;

/**
 * End-to-end auth flow per platform: OTP login, shahkar registration and
 * the payment callback must all respect the platform separation.
 */
class TenantAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('auth.token', AuthenticateApiToken::class);
    }

    private function mockSms(): void
    {
        $this->mock(SmsIrService::class, function ($mock) {
            $mock->shouldReceive('sendVerificationCode')->andReturn(true);
        });
    }

    public function test_otp_login_on_nopay_creates_nopay_account_and_token(): void
    {
        $this->mockSms();

        // Send OTP with X-Platform: nopay
        $this->withHeaders(['X-Platform' => 'nopay'])
            ->postJson('/api/auth/send-otp', ['phone' => '09120000010'])
            ->assertOk();

        // Read the code from cache, then verify
        $code = Cache::get('otp:09120000010');

        // No user exists yet → requires_registration
        $response = $this->withHeaders(['X-Platform' => 'nopay'])
            ->postJson('/api/auth/verify-otp', ['phone' => '09120000010', 'code' => $code]);

        $response->assertOk();
        $response->assertJson(['requires_registration' => true]);

        // Register via shahkar (mock success)
        $this->mock(ShahkarService::class, function ($mock) {
            $mock->shouldReceive('verify')->andReturn(['success' => true, 'matched' => true]);
        });

        $registerToken = $response->json('token');

        $reg = $this->withHeaders(['X-Platform' => 'nopay'])
            ->postJson('/api/auth/shahkar-verify', [
                'token' => $registerToken,
                'first_name' => 'علی',
                'last_name' => 'نوپی',
                'national_code' => '0012345678',
                'birth_date' => '1370-01-15',
            ]);

        $reg->assertOk();
        $reg->assertJsonStructure(['token', 'user']);

        // The created user belongs to nopay and has the same phone
        $user = User::withoutTenantScope()->where('phone', '09120000010')->first();
        $this->assertNotNull($user);
        $this->assertSame('nopay', $user->platform);

        // The returned token works on nopay
        $this->withHeaders(['X-Platform' => 'nopay', 'Authorization' => 'Bearer '.$reg->json('token')])
            ->getJson('/api/cart')
            ->assertOk();
    }

    public function test_otp_login_on_main_creates_main_account(): void
    {
        $this->mockSms();

        $this->withHeaders(['X-Platform' => 'main'])
            ->postJson('/api/auth/send-otp', ['phone' => '09120000011'])
            ->assertOk();

        $code = Cache::get('otp:09120000011');

        $response = $this->withHeaders(['X-Platform' => 'main'])
            ->postJson('/api/auth/verify-otp', ['phone' => '09120000011', 'code' => $code]);

        $response->assertOk();
        $response->assertJson(['requires_registration' => true]);

        $this->mock(ShahkarService::class, function ($mock) {
            $mock->shouldReceive('verify')->andReturn(['success' => true, 'matched' => true]);
        });

        $reg = $this->withHeaders(['X-Platform' => 'main'])
            ->postJson('/api/auth/shahkar-verify', [
                'token' => $response->json('token'),
                'first_name' => 'مریم',
                'last_name' => 'اصلی',
                'national_code' => '0098765432',
                'birth_date' => '1375-05-20',
            ]);

        $reg->assertOk();

        $user = User::withoutTenantScope()->where('phone', '09120000011')->first();
        $this->assertNotNull($user);
        $this->assertSame('main', $user->platform);
    }

    #[RunInSeparateProcess]
    public function test_callback_resolves_payment_by_order_platform(): void
    {
        // Build a nopay order + pending payment directly (bypass gateway).
        $nopayUser = User::withoutTenantScope()->create([
            'name' => 'نوپی',
            'email' => null,
            'password' => bcrypt('secret'),
            'phone' => '09120000012',
            'phone_verified_at' => now(),
            'platform' => 'nopay',
        ]);

        $order = \Modules\Order\Models\Order::withoutTenantScope()->create([
            'user_id' => $nopayUser->id,
            'platform' => 'nopay',
            'order_number' => 'N-00099',
            'status' => 'pending',
            'total_amount' => 300000,
            'payment_method' => 'installment_nofee',
            'payment_status' => 'pending',
            'notes' => json_encode([]),
        ]);

        \Modules\Payment\Models\Payment::withoutTenantScope()->create([
            'user_id' => $nopayUser->id,
            'order_id' => $order->id,
            'platform' => 'nopay',
            'transaction_id' => 'TXN-NOPAY-001',
            'amount' => 300000,
            'payment_method' => 'installment_nofee',
            'gateway' => 'nopay',
            'status' => 'pending',
        ]);

        // Also create a main order with a DIFFERENT txn id to prove no cross-platform lookup
        $mainUser = User::withoutTenantScope()->create([
            'name' => 'اصلی',
            'email' => null,
            'password' => bcrypt('secret'),
            'phone' => '09120000013',
            'phone_verified_at' => now(),
            'platform' => 'main',
        ]);

        $mainOrder = \Modules\Order\Models\Order::withoutTenantScope()->create([
            'user_id' => $mainUser->id,
            'platform' => 'main',
            'order_number' => '00098',
            'status' => 'pending',
            'total_amount' => 300000,
            'payment_method' => 'online',
            'payment_status' => 'pending',
            'notes' => json_encode([]),
        ]);

        \Modules\Payment\Models\Payment::withoutTenantScope()->create([
            'user_id' => $mainUser->id,
            'order_id' => $mainOrder->id,
            'platform' => 'main',
            'transaction_id' => 'TXN-MAIN-001',
            'amount' => 300000,
            'payment_method' => 'online',
            'gateway' => 'parsian',
            'status' => 'pending',
        ]);

        // Mock the gateway verification so the test only asserts platform isolation.
        $receipt = \Mockery::mock(\Shetabit\Multipay\Receipt::class);
        $receipt->shouldReceive('getReferenceId')->andReturn('REF-NOPAY-1');
        $receipt->shouldReceive('getDetails')->andReturn([]);

        $mock = \Mockery::mock('overload:'.ShetabitPayment::class);
        $mock->shouldReceive('via')->andReturnSelf();
        $mock->shouldReceive('amount')->andReturnSelf();
        $mock->shouldReceive('transactionId')->andReturnSelf();
        $mock->shouldReceive('verify')->andReturn($receipt);

        // The nopay callback must find the nopay payment, not the main one.
        // It does NOT send X-Platform — it relies on the order's platform.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get("/api/payment/callback/{$order->id}/nopay?Token=TXN-NOPAY-001")
            ->assertRedirect();

        // Payment should now be paid (verify succeeded)
        $payment = \Modules\Payment\Models\Payment::withoutTenantScope()
            ->where('transaction_id', 'TXN-NOPAY-001')
            ->first();
        $this->assertNotNull($payment);
        $this->assertSame('paid', $payment->status);

        // The main payment must remain untouched
        $mainPayment = \Modules\Payment\Models\Payment::withoutTenantScope()
            ->where('transaction_id', 'TXN-MAIN-001')
            ->first();
        $this->assertSame('pending', $mainPayment->status);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}