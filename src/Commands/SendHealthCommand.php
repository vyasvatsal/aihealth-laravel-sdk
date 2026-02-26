<?php

namespace AIHealth\Laravel\Commands;

use Illuminate\Console\Command;
use AIHealth\Laravel\Client;

class SendHealthCommand extends Command
{
    protected $signature = 'aihealth:health';

    protected $description = 'Send system health metrics to AIHealth Monitor';

    public function handle(Client $client)
    {
        $this->info('Capturing and sending health metrics...');

        $client->captureHealth();

        $this->info('Health metrics sent successfully!');
    }
}
