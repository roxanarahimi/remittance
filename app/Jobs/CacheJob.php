<?php

namespace App\Jobs;

use App\Http\Controllers\RemittanceController;
use http\Env\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): \Illuminate\Http\JsonResponse
    {
        $data = (new \App\Http\Controllers\RemittanceController)->readOnly1();
        return response()->json($data, 200);
    }
}
