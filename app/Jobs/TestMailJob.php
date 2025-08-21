<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(
        public ?string $mailDriver = null
    ) {
        //
    }

    public function handle(): void
    {
        $startTime = microtime(true);
        $timestamp = now()->format('Y-m-d H:i:s');
        
        Log::info('TestMailJob: Starting', [
            'mail_driver' => $this->mailDriver,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
            'job_id' => $this->job?->getJobId(),
        ]);

        try {
            // Set mail driver if specified
            $originalDriver = null;
            if ($this->mailDriver) {
                $originalDriver = config('mail.default');
                config(['mail.default' => $this->mailDriver]);
                Log::info('TestMailJob: Mail driver changed', [
                    'from' => $originalDriver,
                    'to' => $this->mailDriver,
                ]);
            }

            // Critical point - send email
            Log::info('TestMailJob: About to send email');
            $sendStartTime = microtime(true);
            
            Mail::raw("Test email from job\nSent at: {$timestamp}\nDriver: " . config('mail.default'), function ($message) use ($timestamp) {
                $message->to('yaniv@anjijlimited.com')
                        ->subject("Job Test - {$timestamp}");
            });
            
            $sendEndTime = microtime(true);
            
            Log::info('TestMailJob: Email sent successfully', [
                'send_duration_ms' => round(($sendEndTime - $sendStartTime) * 1000, 2),
                'total_duration_ms' => round(($sendEndTime - $startTime) * 1000, 2),
            ]);

            // Restore original driver
            if ($originalDriver) {
                config(['mail.default' => $originalDriver]);
            }

        } catch (\Throwable $e) {
            Log::error('TestMailJob: Failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('TestMailJob: Job failed permanently', [
            'mail_driver' => $this->mailDriver,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}