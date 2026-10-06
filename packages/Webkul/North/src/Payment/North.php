<?php

namespace Webkul\North\Payment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Webkul\Payment\Payment\Payment;

class North extends Payment
{
    /**
     * Base URL for North's Embedded Checkout API.
     */
    protected const API_BASE_URL = 'https://checkout.north.com';

    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'north';

    /**
     * Get redirect URL for North payment.
     *
     * @return string|null
     */
    public function getRedirectUrl()
    {
        return null;
    }

    /**
     * Check if payment method is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return parent::isAvailable() && $this->hasValidCredentials();
    }

    /**
     * Get payment method image.
     *
     * @return string
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : bagisto_asset('images/north.png', 'shop');
    }

    /**
     * Get North checkout id.
     *
     * @return string|null
     */
    public function getCheckoutId()
    {
        return $this->getConfigData('checkout_id');
    }

    /**
     * Get North profile id.
     *
     * @return string|null
     */
    public function getProfileId()
    {
        return $this->getConfigData('profile_id');
    }

    /**
     * Get North private API key for the active environment.
     *
     * @return string|null
     */
    public function getApiKey()
    {
        $isSandbox = $this->getConfigData('sandbox');

        return $isSandbox
            ? $this->getConfigData('api_test_key')
            : $this->getConfigData('api_key');
    }

    /**
     * Check if required credentials are configured.
     *
     * @return bool
     */
    public function hasValidCredentials()
    {
        return $this->getCheckoutId()
            && $this->getProfileId()
            && $this->getApiKey();
    }

    /**
     * Create a North Embedded Checkout session for the given amount.
     *
     * @param  float  $amount
     * @return array
     */
    public function createSession($amount)
    {
        $response = Http::withToken($this->getApiKey())
            ->retry(2, 500, $this->retryOnTransientFailure())
            ->post(self::API_BASE_URL.'/api/sessions', [
                'checkoutId' => $this->getCheckoutId(),
                'profileId' => $this->getProfileId(),
                'amount' => $amount,
            ])
            ->throw();

        return $response->json();
    }

    /**
     * Fetch a session's authoritative status from North.
     *
     * @param  string  $sessionToken
     * @return array
     */
    public function verifySession($sessionToken)
    {
        $response = Http::withToken($this->getApiKey())
            ->retry(2, 500, $this->retryOnTransientFailure())
            ->withHeaders([
                'SessionToken' => $sessionToken,
                'checkoutId' => $this->getCheckoutId(),
            ])
            ->get(self::API_BASE_URL.'/api/sessions/status')
            ->throw();

        return $response->json();
    }

    /**
     * Verify a webhook payload's HMAC-SHA256 signature.
     *
     * @param  string  $rawBody
     * @param  string|null  $signature
     * @return bool
     */
    public function verifyWebhookSignature($rawBody, $signature)
    {
        $secret = $this->getConfigData('webhook_signing_secret');

        if (
            ! $secret
            || ! $signature
        ) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
    }

    /**
     * Retry only on a dropped connection or a 5xx from North's own infrastructure,
     * never on a 4xx, which is a real request problem a retry cannot fix.
     *
     * @return \Closure
     */
    protected function retryOnTransientFailure()
    {
        return function ($exception) {
            return $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError());
        };
    }
}
