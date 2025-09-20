<?php
namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    protected $host;
    protected $port;
    protected $user;
    protected $pass;
    protected $vhost;

    public function __construct()
    {
        $this->host = env('RABBITMQ_HOST', '127.0.0.1');
        $this->port = env('RABBITMQ_PORT', 5672);
        $this->user = env('RABBITMQ_USER', 'guest');
        $this->pass = env('RABBITMQ_PASSWORD', 'guest');
        $this->vhost = env('RABBITMQ_VHOST', '/');
    }

    /**
     * Publish a chunk (array of rows) to the queue.
     */
    public function publishChunk(array $chunk, string $queue = null): void
    {
        $queue = $queue ?? env('RABBITMQ_QUEUE', 'transactions');

        $connection = new AMQPStreamConnection(
            $this->host, $this->port, $this->user, $this->pass, $this->vhost
        );

        $channel = $connection->channel();

        // durable queue
        $channel->queue_declare($queue, false, true, false, false);

        $payload = [
            'type' => 'transactions_chunk',
            'rows' => $chunk,
            'meta' => ['published_at' => date('c')],
        ];

        $msg = new AMQPMessage(json_encode($payload), [
            'content_type' => 'application/json',
            'delivery_mode' => 2 // persistent
        ]);

        // default exchange, routing key = queue name
        $channel->basic_publish($msg, '', $queue);

        $channel->close();
        $connection->close();
    }
}
