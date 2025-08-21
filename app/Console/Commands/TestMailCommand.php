<?php

namespace App\Console\Commands;

use App\Jobs\TestMailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature = 'test:mail 
                            {--driver=log : Mail driver to use (smtp, ses, log, etc.)}
                            {--sync : Run job synchronously instead of queued}';

    protected $description = 'Test mail sending with minimal setup';

    public function handle(): int
    {
        $driver = $this->option('driver');
        $sync = $this->option('sync');
        $timestamp = now()->format('Y-m-d H:i:s');

        $this->info('🧪 Starting mail test...');
        $this->line("Driver: {$driver}");
        $this->line("Mode: " . ($sync ? 'Synchronous' : 'Queued'));
        $this->newLine();

        // Set driver if specified
        if ($driver) {
            config(['mail.default' => $driver]);
            $this->line("Mail driver set to: {$driver}");
        }

        try {
            // Test 1: Direct Mail::raw test
            $this->info('Test 1: Direct Mail::raw()...');
            $startTime = microtime(true);
            
            $this->line('  → Calling Mail::raw...');
            Mail::raw("Direct test email\nSent at: {$timestamp}\nDriver: {$driver}", function ($message) use ($timestamp) {
                $message->to('yaniv@anjijlimited.com')
                        ->subject("Direct Test - {$timestamp}");
            });
            $this->line('  → Mail::raw completed');
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->info("✅ Direct mail test completed in {$duration}ms");
            $this->newLine();

            // Test 2: Job-based test
            $this->info('Test 2: Job-based mail test...');
            $job = new TestMailJob($driver);
            
            if ($sync) {
                $this->line('  → Dispatching job synchronously...');
                dispatch_sync($job);
            } else {
                $this->line('  → Dispatching job to queue...');
                dispatch($job);
            }
            
            $this->info('✅ Job dispatched successfully');
            
            if (!$sync) {
                $this->line('Run "php artisan queue:work --once" to process the job');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Test failed:');
            $this->line($e->getMessage());
            
            Log::error('TestMailCommand: Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'driver' => $driver,
            ]);

            return Command::FAILURE;
        }
    }
}