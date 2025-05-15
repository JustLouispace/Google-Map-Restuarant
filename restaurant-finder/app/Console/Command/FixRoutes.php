<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FixRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix route issues by clearing caches and reloading routes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing route cache...');
        Artisan::call('route:clear');
        $this->info(Artisan::output());

        $this->info('Clearing config cache...');
        Artisan::call('config:clear');
        $this->info(Artisan::output());

        $this->info('Clearing application cache...');
        Artisan::call('cache:clear');
        $this->info(Artisan::output());

        $this->info('Clearing compiled views...');
        Artisan::call('view:clear');
        $this->info(Artisan::output());

        $this->info('Optimizing...');
        Artisan::call('optimize:clear');
        $this->info(Artisan::output());

        $this->info('Routes fixed successfully!');
        
        // List all routes for debugging
        $this->info('Listing all routes:');
        Artisan::call('route:list');
        $this->info(Artisan::output());

        return Command::SUCCESS;
    }
}
