<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ExportLog;
use App\Services\RabbitMQPublisher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class TransactionExportController extends Controller
{
    /**
     * Request an export job (producer)
     */
    public function requestExport(Request $request)
    {
        $request->validate([
            'filters'    => 'array|nullable',
            'sort_by'    => 'string|nullable',
            'sort_order' => 'in:asc,desc|nullable',
        ]);

        // Create Export Log
        $exportLog = ExportLog::create([
            'user_id' => 3, // Replace with auth()->id() in production
            'status'  => 'pending',
            'filters' => $request->filters ?? null,
        ]);

        // ✅ Wrap payload in a consistent format
        $payload = [
            'type' => 'transaction_export',
            'data' => [
                'export_id'  => $exportLog->id,
                'user_id'    => 3,
                'filters'    => $request->filters ?? [],
                'sort_by'    => $request->sort_by ?? 'transaction_date',
                'sort_order' => $request->sort_order ?? 'desc',
            ],
            'meta' => [
                'published_at' => now()->toIso8601String(),
            ],
        ];

        $publisher = new RabbitMQPublisher();
        $publisher->publishChunk($payload, env('RABBITMQ_EXPORT_QUEUE', 'transaction_exports'));

        return response()->json([
            'status'    => 'success',
            'message'   => 'Export request submitted successfully.',
            'export_id' => $exportLog->id,
            'status_url' => url("/api/export/status/{$exportLog->id}"),
            'download_url' => url("/api/export/download/{$exportLog->id}"),
        ]);
    }

    /**
     * Check export status (API endpoint)
     */
    public function checkExportStatus($exportId)
    {
        $exportLog = ExportLog::findOrFail($exportId);

        $response = [
            'export_id' => $exportLog->id,
            'user_id' => $exportLog->user_id,
            'status' => $exportLog->status,
            'created_at' => $exportLog->created_at->toISOString(),
            'updated_at' => $exportLog->updated_at->toISOString(),
            'processed_at' => $exportLog->processed_at?->toISOString(),
            'downloaded_at' => $exportLog->downloaded_at?->toISOString(),
            'is_downloaded' => !is_null($exportLog->downloaded_at),
            'file_size' => $exportLog->file_size,
            'file_size_formatted' => $this->formatBytes($exportLog->file_size),
            'filters' => $exportLog->filters,
        ];

        if ($exportLog->status === 'completed') {
            $response['download_available'] = !is_null($exportLog->file_name);
            $response['download_url'] = !is_null($exportLog->file_name) 
                ? url("/api/export/download/{$exportLog->id}")
                : null;
        }

        if ($exportLog->status === 'failed') {
            $response['error_message'] = $exportLog->error_message;
        }

        return response()->json($response);
    }

    /**
     * Download exported file with automatic cleanup
     */
    public function downloadExport($exportId)
    {
        $exportLog = ExportLog::findOrFail($exportId);

        if ($exportLog->status !== 'completed') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Export is not yet completed or failed. Current status: ' . $exportLog->status
            ], 400);
        }

        if (!Storage::exists($exportLog->file_name)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Export file not found.'
            ], 404);
        }

        // Get file details
        $filePath = Storage::path($exportLog->file_name);
        $fileName = 'transactions_export_' . $exportId . '.xlsx';
        
        // Create a temporary copy for download
        $tempPath = tempnam(sys_get_temp_dir(), 'export_') . '.xlsx';
        copy($filePath, $tempPath);
        
        // Delete the original file from storage
        Storage::delete($exportLog->file_name);
        
        // Update the export log with download timestamp
        $exportLog->update([
            'file_name' => null,
            'file_size' => null,
            'downloaded_at' => now()
        ]);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => filesize($tempPath),
        ])->deleteFileAfterSend(true);
    }

    /**
     * Get list of user's exports
     */
    public function listExports(Request $request)
    {
        $userId = 3; // Replace with auth()->id() in production
        
        $exports = ExportLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($export) {
                return [
                    'id' => $export->id,
                    'status' => $export->status,
                    'created_at' => $export->created_at->toISOString(),
                    'updated_at' => $export->updated_at->toISOString(),
                    'processed_at' => $export->processed_at?->toISOString(),
                    'downloaded_at' => $export->downloaded_at?->toISOString(),
                    'file_size' => $export->file_size,
                    'file_size_formatted' => $this->formatBytes($export->file_size),
                    'filters' => $export->filters,
                    'download_available' => $export->status === 'completed' && $export->file_name,
                    'download_url' => $export->status === 'completed' && $export->file_name 
                        ? url("/api/export/download/{$export->id}")
                        : null,
                    'error_message' => $export->error_message,
                ];
            });

        return response()->json([
            'exports' => $exports,
            'count' => $exports->count()
        ]);
    }

    /**
     * Clean up old export files (can be called manually or scheduled)
     */
    public function cleanupOldExports(Request $request)
    {
        $hours = $request->input('hours', 24); // Default: clean up files older than 24 hours
        
        $cutoff = now()->subHours($hours);
        
        $oldExports = ExportLog::where('created_at', '<', $cutoff)
            ->whereNotNull('file_name')
            ->get();

        $deletedCount = 0;
        
        foreach ($oldExports as $export) {
            if (Storage::exists($export->file_name)) {
                Storage::delete($export->file_name);
                $deletedCount++;
            }
            
            $export->update([
                'file_name' => null,
                'file_size' => null,
                'status' => 'completed' // Keep as completed, not expired (since expired is not in ENUM)
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Cleaned up {$deletedCount} old export files",
            'deleted_count' => $deletedCount,
            'cutoff_time' => $cutoff->toISOString()
        ]);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        if (!$bytes) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}