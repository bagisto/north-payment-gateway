<?php

namespace Webkul\North\Console\Commands;

use Illuminate\Console\Command;
use Webkul\North\Providers\NorthServiceProvider;

class InstallNorth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'north:install
        { --force : Overwrite any previously published North assets. }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the North payment gateway: publish its assets, link the storage directory and clear the caches.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('Step: Publishing North assets...');
        $this->publishAssets();

        $this->warn('Step: Linking storage directory...');
        $this->linkStorage();

        $this->warn('Step: Clearing cached bootstrap files...');
        $this->call('optimize:clear');

        $this->components->info('North payment gateway installed successfully.');

        $this->components->bulletList([
            'Configure the gateway at Configure → Sales → Payment Methods → North.',
            'Get the Checkout ID, Profile ID and Private API Key from North\'s Embedded Checkout dashboard.',
            'Point North\'s webhook URL to '.route('north.webhook').', then save the signing secret it gives you there.',
        ]);

        return self::SUCCESS;
    }

    /**
     * Publish the payment method logo to the public disk.
     */
    protected function publishAssets(): void
    {
        $this->call('vendor:publish', array_filter([
            '--provider' => NorthServiceProvider::class,
            '--tag' => 'public',
            '--force' => $this->option('force'),
        ]));
    }

    /**
     * Link the storage directory so the published logo is publicly reachable.
     */
    protected function linkStorage(): void
    {
        if (file_exists(public_path('storage'))) {
            $this->components->info('The [public/storage] link already exists.');

            return;
        }

        $this->call('storage:link');
    }
}
