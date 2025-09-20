<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
//use Illuminate\Queue\SerializesModels;

class ProcessTransactionChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    //, SerializesModels;

    protected $chunk;

    public function __construct(array $chunk)
    {
        $this->chunk = $chunk;
    }

public function handle()
{
    $validRows = [];

    foreach ($this->chunk as $index => $row) {
        $validator = validator($row, [
            'transaction_id'=>'required|string|max:50',
            'user_id'=>'required|integer',
            'account_number'=>'required|string|max:30',
            'transaction_date'=>'required|date',
            'transaction_type'=>'required|in:credit,debit',
            'amount'=>'required|numeric',
            'currency'=>'required|string|size:3',
            'status'=>'required|in:pending,completed,failed',
            'merchant_name'=>'nullable|string|max:100',
            'merchant_category'=>'nullable|string|max:50',
            'description'=>'nullable|string|max:255',
            'reference_number'=>'nullable|string|max:50',
            'country'=>'nullable|string|size:2',
            'city'=>'nullable|string|max:50',
            'ip_address'=>'nullable|ip',
            'device'=>'nullable|string|max:50',
            'channel'=>'nullable|in:online,branch,mobile',
            'fee'=>'nullable|numeric',
            'tax'=>'nullable|numeric',
            'balance_before'=>'nullable|numeric',
            'balance_after'=>'nullable|numeric',
            'processing_time_ms'=>'nullable|integer',
            'extra_field1'=>'nullable|string|max:100',
            'extra_field2'=>'nullable|string|max:100',
            'extra_field3'=>'nullable|string|max:100',
            'extra_field4'=>'nullable|string|max:100',
            'extra_field5'=>'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            \Log::warning("Transaction validation failed for row $index", $row);
            continue;
        }

        $validRows[] = $validator->validated();
    }

    if (!empty($validRows)) {
        try {
            Transaction::insert($validRows);
            \Log::info('Inserted '.count($validRows).' transactions successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to insert transactions: '.$e->getMessage(), $validRows);
        }
    }
}

}
