<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Subscribers\Queue\Jobs;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class SnsEventDispatcherJob extends SqsJob implements JobContract
{
    /**
     * {@inheritDoc}
     */
    public function fire()
    {
        if ($this->isRawPayload()) {
            if ($this->container->bound('log')) {
                Log::error('SqsEventsQueue: Invalid SNS payload. '.
                    'Make sure your JSON is a valid JSON object and raw '.
                    'message delivery is disabled for your SQS subscription.', $this->job);
            }

            return;
        }

        if ($eventName = $this->resolveName()) {
            $this->resolve(Dispatcher::class)->dispatch($eventName, [
                'payload' => json_decode($this->snsMessage(), true),
                'subject' => $this->snsSubject(),
            ]);
        }
    }

    public function getCorrelationId(): ?string
    {
        return Arr::get($this->decodedSnsMessage(), 'detail.metadata.correlation_id');
    }

    public function decodedSnsMessage(): array
    {
        return json_decode($this->snsMessage(), true);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->snsSubject() ?: $this->snsTopicArn();
    }

    /**
     * {@inheritDoc}
     */
    public function resolveName()
    {
        return $this->getName();
    }

    /**
     * Verifies that the SNS message sent to the queue can be processed.
     *
     * @return bool
     */
    private function isRawPayload()
    {
        return is_null($this->payload()['Type'] ?? null);
    }

    /**
     * Get the job SNS Topic identifier it was sent from.
     *
     * @return string
     */
    public function snsTopicArn()
    {
        return $this->payload()['TopicArn'] ?? '';
    }

    /**
     * Get the job SNS subject.
     *
     * @return string
     */
    public function snsSubject()
    {
        return $this->payload()['Subject'] ?? '';
    }

    /**
     * Get the job SNS message.
     *
     * @return string
     */
    public function snsMessage()
    {
        return $this->payload()['Message'] ?? '[]';
    }

    /**
     * Get the job message type. If a raw SNS message was used, this will be missing.
     *
     * @return string|null
     */
    public function snsMessageType()
    {
        return $this->payload()['Type'] ?? null;
    }
}
