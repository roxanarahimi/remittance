<?php

namespace App\Console\Commands;

use App\Http\Controllers\CacheController;
use Illuminate\Console\Command;

class CacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    public function handle()
    {

        CacheController::cacheInvoice();
        echo '
' . now()->format('Y-m-d h:i:s') . ' - UTC: cache created .....!
';
    }
}
