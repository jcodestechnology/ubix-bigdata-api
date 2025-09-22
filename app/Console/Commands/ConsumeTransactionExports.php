<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use App\Models\ExportLog;
use App\Models\Transaction;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Schema;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;

class ConsumeTransactionExports extends Command
{
    protected $signature = 'rabbitmq:consume-transaction-export';
    protected $description = 'Consume export jobs from RabbitMQ and generate Excel files';

    public function handle()
    {
        $queue = env('RABBITMQ_EXPORT_QUEUE', 'transaction_export');

        $connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', '127.0.0.1'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest'),
            env('RABBITMQ_VHOST', '/')
        );

        $channel = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);

        $this->info("Listening for export jobs on '{$queue}' ...");

        $callback = function (AMQPMessage $msg) {
            $payload = json_decode($msg->body, true);

            // ✅ Unwrap payload
            $data = null;
            if (isset($payload['export_id'])) {
                $data = $payload;
            } elseif (isset($payload['data']['export_id'])) {
                $data = $payload['data'];
            } elseif (isset($payload['rows']['data']['export_id'])) {
                $data = $payload['rows']['data'];
            }

            if (!$data || !isset($data['export_id'])) {
                \Log::error('Invalid export payload', ['body' => $msg->body]);
                $msg->ack();
                return;
            }

            $exportLog = ExportLog::find($data['export_id']);
            if (!$exportLog) {
                \Log::warning('ExportLog not found', ['export_id' => $data['export_id']]);
                $msg->ack();
                return;
            }

            try {
                // Mark as processing
                $exportLog->update([
                    'status' => 'processing'
                ]);

                // ✅ Use Spout for memory-efficient Excel writing
                $this->generateExportWithSpout($data, $exportLog);

                \Log::info('Export completed', [
                    'export_id' => $data['export_id'],
                    'file'      => $exportLog->file_name
                ]);

            } catch (\Exception $e) {
                \Log::error('Export failed: ' . $e->getMessage(), [
                    'export_id' => $data['export_id'],
                    'error' => $e->getTraceAsString()
                ]);
                
                $exportLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }

            $msg->ack();
        };

        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    /**
     * Memory-efficient export using Spout
     */
    private function generateExportWithSpout(array $data, ExportLog $exportLog): void
    {
        // Create directory if it doesn't exist
        $directory = Storage::path('exports');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = 'transactions_' . time() . '.xlsx';
        $filePath = Storage::path('exports/' . $fileName);

        // Create writer
        $writer = WriterEntityFactory::createXLSXWriter();
        $writer->openToFile($filePath);

        // Get column names
        $columns = Schema::getColumnListing('transactions');
        
        // Write header row
        $headerStyle = (new StyleBuilder())
            ->setFontBold()
            ->setFontSize(12)
            ->setBackgroundColor('DDDDDD')
            ->build();

        $headerRow = WriterEntityFactory::createRowFromArray($columns, $headerStyle);
        $writer->addRow($headerRow);

        // Build query with filters and sorting
        $query = Transaction::query();
        if (!empty($data['filters'])) {
            foreach ($data['filters'] as $col => $val) {
                $query->where($col, $val);
            }
        }
        $query->orderBy($data['sort_by'], $data['sort_order']);

        $chunkSize = 1000;
        $processed = 0;

        // Process in chunks to save memory
        $query->chunk($chunkSize, function ($transactions) use ($writer, $columns, &$processed) {
            foreach ($transactions as $transaction) {
                $rowData = [];
                foreach ($columns as $column) {
                    $rowData[] = $transaction->$column;
                }
                
                $row = WriterEntityFactory::createRowFromArray($rowData);
                $writer->addRow($row);
                
                $processed++;
                
                // Free memory every 100 rows
                if ($processed % 100 === 0) {
                    gc_collect_cycles();
                }
            }
        });

        $writer->close();

        // Get file size
        $fileSize = filesize($filePath);

        // Update export log with all new fields
        $exportLog->update([
            'file_name' => 'exports/' . $fileName,
            'file_size' => $fileSize,
            'status' => 'completed',
            'processed_at' => now()
        ]);
    }
}