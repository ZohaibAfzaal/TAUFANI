<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-test-user')]
#[Description('Command description')]
class CreateTestUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
