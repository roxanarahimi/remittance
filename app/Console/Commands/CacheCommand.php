<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\CacheController;

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
        $result = CacheController::cacheInvoice();
        echo $result;
//        if ($result) {
//            echo '
//' . now()->format('Y-m-d h:i:s') . ' - UTC: cache created successfully!
//';
//        } else {
//            echo '
//' . now()->format('Y-m-d h:i:s') . ' - UTC: cache creation failed!
//';
//        }
    }
}
