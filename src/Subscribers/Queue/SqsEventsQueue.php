<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Subscribers\Queue;

use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Log;
use Morscate\LaravelEventFlow\Subscribers\Queue\Jobs\SnsEventDispatcherJob;

class SqsEventsQueue extends SqsQueue
{
    /**
     * {@inheritDoc}
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        if ($this->container->bound('log')) {
            Log::error('Unsupported: sqs-events queue driver is read-only');
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        if ($this->container->bound('log')) {
            Log::error('Unsupported: sqs-events queue driver is read-only');
        }

        return null;
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue,
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (! is_null($response['Messages']) && count($response['Messages']) > 0) {
            return new SnsEventDispatcherJob(
                $this->container,
                $this->sqs,
                $response['Messages'][0],
                $this->connectionName,
                $queue
            );
        }
    }
}
