<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Subscribers\Queue\Connectors;

use Aws\Sqs\SqsClient;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;
use Morscate\LaravelEventFlow\EventFlowServiceProvider;
use Morscate\LaravelEventFlow\Subscribers\Queue\SqsEventsQueue;

class SqsEventsConnector extends SqsConnector
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        return new SqsEventsQueue(
            new SqsClient(EventFlowServiceProvider::prepareConfigurationCredentials($config)),
            config('event-flow.queue'),
            Arr::get($config, 'prefix', ''),
            Arr::get($config, 'suffix', ''),
        );
    }
}
