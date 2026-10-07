<?php

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Webkul\Core\Models\CoreConfig;
use Webkul\North\Payment\North;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderTransaction;

beforeEach(function () {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.active',
        'value' => '1',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.checkout_id',
        'value' => 'checkout_123',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.profile_id',
        'value' => 'profile_123',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.api_key',
        'value' => 'live_key',
        'channel_code' => 'default',
    ]);
});

it('rejects session creation when credentials are invalid', function () {
    // Arrange
    CoreConfig::query()
        ->where('code', 'sales.payment_methods.north.api_key')
        ->update(['value' => '']);

    // Act
    $response = $this->post(route('shop.north.create-session'));

    // Assert
    $response->assertStatus(400);

    $response->assertJsonFragment(['message' => trans('north::app.response.provide-credentials')]);
});

it('creates a session token for the active cart', function () {
    // Arrange
    $this->createCartWithItems('north');

    $northMock = $this->mock(North::class)->makePartial();

    $northMock->shouldReceive('createSession')->once()->andReturn(['token' => 'sess_abc123']);

    $this->app->instance(North::class, $northMock);

    // Act
    $response = $this->post(route('shop.north.create-session'));

    // Assert
    $response->assertOk();

    $response->assertJson(['token' => 'sess_abc123']);
});

it('rejects verification when the session is not approved', function () {
    // Arrange
    $cart = $this->createCartWithItems('north');

    $northMock = $this->mock(North::class)->makePartial();

    $northMock->shouldReceive('verifySession')->with('sess_abc123')->andReturn(['status' => 'Declined']);

    $this->app->instance(North::class, $northMock);

    // Act
    $response = $this->post(route('shop.north.verify'), ['session_token' => 'sess_abc123']);

    // Assert
    $response->assertStatus(400);

    expect(Order::where('customer_id', $cart->customer_id)->exists())->toBeFalse();
});

it('places the order when the session is approved', function () {
    // Arrange
    $cart = $this->createCartWithItems('north');

    $northMock = $this->mock(North::class)->makePartial();

    $northMock->shouldReceive('verifySession')
        ->with('sess_abc123')
        ->andReturn(['status' => 'Approved', 'authCode' => 'auth_789']);

    $this->app->instance(North::class, $northMock);

    // Act
    $response = $this->post(route('shop.north.verify'), ['session_token' => 'sess_abc123']);

    // Assert
    $response->assertOk();

    $response->assertJson(['success' => true]);

    $order = Order::where('customer_id', $cart->customer_id)->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('processing');

    $orderTransaction = OrderTransaction::where('transaction_id', 'auth_789')->first();

    expect($orderTransaction)->not->toBeNull()
        ->and($orderTransaction->order_id)->toBe($order->id);

    $invoice = Invoice::where('order_id', $order->id)->first();

    expect($invoice)->not->toBeNull();

    $cart->refresh();

    expect($cart->is_active)->toBe(0);
});
