<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Subscribers\Queue\Connectors;

use Aws\Sqs\SqsClient;
use Illuminate\Queue\Connectors\SqsConnector;
use Illuminate\Support\Arr;
use Morscate\LaravelEventFlow\EventFlowServiceProvider;
use Morscate\LaravelEventFlow\Subscribers\Queue\SqsSnsQueue;

class SqsSnsConnector extends SqsConnector
{
    /**
     * Establish a queue connection.
     *
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        $config = $this->getDefaultConfiguration($config);

        return new SqsSnsQueue(
            new SqsClient(EventFlowServiceProvider::prepareConfigurationCredentials($config)),
            $config['queue'],
            Arr::get($config, 'prefix', ''),
            Arr::get($config, 'suffix', ''),
        );
    }
}
