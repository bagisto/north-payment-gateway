<?php

use Webkul\Core\Models\CoreConfig;
use Webkul\North\Payment\North;

beforeEach(function () {
    $this->north = app(North::class);
});

it('returns the correct payment method code', function () {
    // Act
    $code = $this->north->getCode();

    // Assert
    expect($code)->toBe('north');
});

it('returns null for the redirect url', function () {
    // Act
    $url = $this->north->getRedirectUrl();

    // Assert
    expect($url)->toBeNull();
});

it('returns the sandbox api key when sandbox mode is enabled', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.sandbox',
        'value' => '1',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.api_test_key',
        'value' => 'test_key',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.api_key',
        'value' => 'live_key',
        'channel_code' => 'default',
    ]);

    // Act
    $apiKey = $this->north->getApiKey();

    // Assert
    expect($apiKey)->toBe('test_key');
});

it('returns the live api key when sandbox mode is disabled', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.sandbox',
        'value' => '0',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.api_test_key',
        'value' => 'test_key',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.api_key',
        'value' => 'live_key',
        'channel_code' => 'default',
    ]);

    // Act
    $apiKey = $this->north->getApiKey();

    // Assert
    expect($apiKey)->toBe('live_key');
});

it('has valid credentials when checkout id, profile id and api key are set', function () {
    // Arrange
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

    // Act
    $hasValidCredentials = $this->north->hasValidCredentials();

    // Assert
    expect($hasValidCredentials)->toBeTrue();
});

it('does not have valid credentials when profile id is missing', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.checkout_id',
        'value' => 'checkout_123',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.api_key',
        'value' => 'live_key',
        'channel_code' => 'default',
    ]);

    // Act
    $hasValidCredentials = $this->north->hasValidCredentials();

    // Assert
    expect($hasValidCredentials)->toBeFalse();
});

it('is not available when credentials are invalid', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.active',
        'value' => '1',
        'channel_code' => 'default',
    ]);

    // Act
    $isAvailable = $this->north->isAvailable();

    // Assert
    expect($isAvailable)->toBeFalse();
});

it('verifies a webhook signature correctly', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.north.webhook_signing_secret',
        'value' => 'whsec_test',
        'channel_code' => 'default',
    ]);

    $rawBody = '{"transaction":{"authCode":"abc123"}}';

    $validSignature = hash_hmac('sha256', $rawBody, 'whsec_test');

    // Act & Assert
    expect($this->north->verifyWebhookSignature($rawBody, $validSignature))->toBeTrue()
        ->and($this->north->verifyWebhookSignature($rawBody, 'tampered'))->toBeFalse();
});
