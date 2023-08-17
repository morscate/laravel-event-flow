<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Subscribers\Handlers;

use Aws\Sqs\SqsClient;
use Bref\Context\Context;
use Bref\Event\Sqs\SqsEvent;
use Bref\Event\Sqs\SqsHandler;
use Bref\Event\Sqs\SqsRecord;
use Bref\LaravelBridge\MaintenanceMode;
use Bref\LaravelBridge\Queue\Worker;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\QueueManager;
use Illuminate\Queue\SqsQueue;
use Illuminate\Queue\WorkerOptions;
use Morscate\LaravelEventFlow\Subscribers\Queue\Jobs\SnsEventDispatcherJob;
use RuntimeException;

/**
 * Handles SQS events.
 */
class SqsEventsHandler extends SqsHandler
{
    /**
     * The AWS SQS client.
     */
    protected SqsClient $sqs;

    /**
     * Number of seconds before Lambda invocation deadline to timeout the job.
     *
     * @var float
     */
    protected const JOB_TIMEOUT_SAFETY_MARGIN = 1.0;

    /**
     * The name of the SQS queue.
     */
    protected string $queueName;

    /**
     * Creates a new SQS queue handler instance.
     *
     * @return void
     */
    public function __construct(
        protected Container $container,
        protected Dispatcher $events,
        protected ExceptionHandler $exceptions,
        protected string $connection = 'sqs-events',
    ) {
        \Sentry\captureMessage('Handler construct');

        $queue = $container->make(QueueManager::class)
            ->connection('sqs-events');

        $this->queueName = $queue->getQueue(null);
        $this->sqs = $queue->getSqs();
    }

    /**
     * Handle Bref SQS event.
     */
    public function handleSqs(SqsEvent $event, Context $context): void
    {
        \Sentry\captureMessage('Handler handleSqs');

        $worker = $this->container->makeWith(Worker::class, [
            'isDownForMaintenance' => fn () => MaintenanceMode::active(),
        ]);

        foreach ($event->getRecords() as $sqsRecord) {
            $timeout = $this->calculateJobTimeout($context->getRemainingTimeInMillis());

            $worker->runSqsJob(
                $job = $this->marshalJob($sqsRecord),
                $this->connection,
                $this->gatherWorkerOptions($timeout),
            );

            if (! $job->hasFailed() && ! $job->isDeleted()) {
                $job->delete();
            }
        }
    }

    /**
     * Marshal the job with the given Bref SQS record.
     */
    protected function marshalJob(SqsRecord $sqsRecord): SnsEventDispatcherJob
    {
        \Sentry\captureMessage('Handler marshalJob' . json_encode($sqsRecord));

        $message = [
            'MessageId' => $sqsRecord->getMessageId(),
            'ReceiptHandle' => $sqsRecord->getReceiptHandle(),
            'Body' => $sqsRecord->getBody(),
            'Attributes' => $sqsRecord->toArray()['attributes'],
            'MessageAttributes' => $sqsRecord->getMessageAttributes(),
        ];

        \Sentry\captureMessage('Handler marshalJob' . json_encode($message));

        return new SnsEventDispatcherJob(
            $this->container,
            $this->sqs,
            $message,
            $this->connection,
            $this->queueName,
        );
    }

    /**
     * Gather all of the queue worker options as a single object.
     */
    protected function gatherWorkerOptions(int $timeout): WorkerOptions
    {
        $options = [
            0, // backoff
            512, // memory
            $timeout, // timeout
            0, // sleep
            3, // maxTries
            false, // force
            false, // stopWhenEmpty
            0, // maxJobs
            0, // maxTime
        ];

        if (property_exists(WorkerOptions::class, 'name')) {
            $options = array_merge(['default'], $options);
        }

        return new WorkerOptions(...$options);
    }

    /**
     * Calculate the timeout for a job
     */
    protected function calculateJobTimeout(int $remainingInvocationTimeInMs): int
    {
        return max((int) (($remainingInvocationTimeInMs - self::JOB_TIMEOUT_SAFETY_MARGIN) / 1000), 0);
    }
}
