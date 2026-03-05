<?php

namespace AIHealth\Laravel\Commands;

use Illuminate\Console\Command;
use AIHealth\Laravel\Facades\AIHealth;
use Exception;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'aihealth:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test suite of events to verify the AIHealth connection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting AIHealth Connection Test...');

        // 1. Send Health Check
        $this->comment('1/3 Sending Health Check...');
        AIHealth::captureHealth(['test' => true]);

        // 2. Send Log
        $this->comment('2/3 Sending Test Log...');
        AIHealth::captureLog('info', 'AIHealth SDK Test Log: Connection Verified', ['test' => true]);

        // 3. Send Exception
        $this->comment('3/3 Sending Test Exception...');
        try {
            throw new Exception('AIHealth SDK Test Exception: Connection Verified');
        } catch (Exception $e) {
            AIHealth::captureException($e);
        }

        $this->info('📡 Flushing payloads to Health Monitor...');

        // Faceade/Client handles the flush if runningInConsole

        $this->newLine();
        $this->info('✅ Test suite sent!');
        $this->info('Please check your Health Monitor dashboard to confirm reception.');
        $this->comment('Note: If you see "Ingest Success: 201" in the error_log, the connection is perfect.');
    }
}
