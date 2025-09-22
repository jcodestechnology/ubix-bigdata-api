<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\Csv\Reader;
use App\Services\RabbitMQPublisher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TransactionUploadController extends Controller
{

public function upload(Request $request)
{
    $request->validate([
        'csv_file' => 'required|file|mimes:csv|max:102400',
    ]);

    try {
        $file = $request->file('csv_file');
        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset(0);
        
        $chunkSize = intval(env('CHUNK_SIZE', 1000));
        $totalRows = 0;
        $chunk = [];
        $publisher = new RabbitMQPublisher();

        foreach ($csv->getRecords() as $row) {
            $chunk[] = $row;
            $totalRows++;

            if (count($chunk) >= $chunkSize) {
                $publisher->publishChunk($chunk);
                $chunk = [];
                
                // Free memory periodically
                if ($totalRows % 10000 === 0) {
                    gc_collect_cycles();
                }
            }
        }

        if (!empty($chunk)) {
            $publisher->publishChunk($chunk);
        }

        return response()->json([
            'status' => 'success',
            'message' => "CSV uploaded successfully. Total rows: $totalRows.",
            'total_rows' => $totalRows,
        ], 202);

    } catch (\Exception $e) {
        Log::error('CSV upload failed: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to process CSV file.',
            'error' => $e->getMessage()
        ], 500);
    }
}

}