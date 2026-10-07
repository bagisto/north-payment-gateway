<?php

namespace Webkul\North\Tests;

use Tests\TestCase;
use Webkul\Core\Helpers\CacheGeneration;
use Webkul\Core\Models\CoreConfig;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Payment\Tests\Concerns\ProvidePaymentHelpers;

class NorthTestCase extends TestCase
{
    use ProvidePaymentHelpers;

    /**
     * Clear any pre-existing North configuration so credential tests are deterministic.
     */
    protected function setUp(): void
    {
        parent::setUp();

        CoreConfig::query()->where('code', 'like', 'sales.payment_methods.north.%')->delete();

        CacheGeneration::bump(CoreConfigRepository::class);
    }
}
