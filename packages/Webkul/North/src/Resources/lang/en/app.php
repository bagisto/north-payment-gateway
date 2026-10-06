<?php

return [
    'title' => 'North',
    'description' => 'Pay securely with your card via North.',

    'fields' => [
        'checkout-id' => 'Checkout ID',
        'profile-id' => 'Profile ID',
        'webhook-signing-secret' => 'Webhook Signing Secret',
    ],

    'response' => [
        'cart-not-found' => 'Cart not found or invalid.',
        'cart-processed' => 'This cart has already been processed.',
        'invalid-session' => 'Payment session is invalid.',
        'payment-cancelled' => 'Payment was cancelled.',
        'payment-failed' => 'Payment failed.',
        'provide-credentials' => 'Please provide valid North credentials.',
        'session-not-found' => 'Payment session could not be created.',
        'verification-failed' => 'Payment verification failed.',
    ],

    'errors' => [
        'invalid-configs' => 'North is not configured correctly.',
        'something-went-wrong' => 'Something went wrong. Please try again.',
    ],
];
