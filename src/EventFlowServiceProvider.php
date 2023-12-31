<?php

namespace Morscate\LaravelEventFlow;

use Aws\EventBridge\EventBridgeClient;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Morscate\LaravelEventFlow\Publishers\Broadcasters\CustomEventBroadcaster;
use Morscate\LaravelEventFlow\Publishers\Broadcasters\EventBridgeBroadcaster;
use Morscate\LaravelEventFlow\Subscribers\Handlers\SqsEventsHandler;
use Morscate\LaravelEventFlow\Subscribers\Queue\Connectors\SqsEventsConnector;

class EventFlowServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // @todo add config to determine if it will run on Lambda or we can just use the SqsSnsQueue
        $this->app->when(SqsEventsHandler::class)
            ->needs('$connection')
            ->giveConfig('event-flow.queue');

        $this->configure();

        $this->offerPublishing();

        $this->registerSqsEventsQueueConnector();

        $this->registerEventBridgeBroadcaster();

        $this->registerCustomEventBroadcaster();
    }

    /**
     * Setup the configuration.
     */
    protected function configure(): void
    {
        $source = realpath($raw = __DIR__.'/../config/event-flow.php') ?: $raw;

        $this->mergeConfigFrom($source, 'event-flow');
    }

    /**
     * Setup the resource publishing groups.
     */
    protected function offerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/event-flow.php' => config_path('event-flow.php'),
        ], 'event-flow-config');
    }

    /**
     * Register the SQS SNS connector for the Queue components.
     */
    protected function registerSqsEventsQueueConnector(): void
    {
        $this->app->resolving('queue', function (QueueManager $manager) {
            $manager->extend('sqs-events', function () {
                return new SqsEventsConnector;
            });
        });
    }

    /**
     * Register the EventBridge broadcaster for the Broadcast components.
     */
    protected function registerEventBridgeBroadcaster(): void
    {
        $this->app->resolving(BroadcastManager::class, function (BroadcastManager $manager) {
            $manager->extend('eventflow', function (Container $app, array $config) {
                return $this->createEventBridgeDriver($config);
            });
        });
    }

    /**
     * Register the EventBridge broadcaster for the Broadcast components.
     */
    protected function registerCustomEventBroadcaster(): void
    {
        $this->app->resolving(BroadcastManager::class, function (BroadcastManager $manager) {
            $manager->extend('eventflow-custom', function (Container $app, array $config) {
                return $this->createCustomEventDriver($config);
            });
        });
    }

    /**
     * Create an instance of the EventBridge driver for broadcasting.
     */
    public function createEventBridgeDriver(array $config): EventBridgeBroadcaster
    {
        $config = self::prepareConfigurationCredentials($config);

        return new EventBridgeBroadcaster(new EventBridgeClient(array_merge($config, ['version' => '2015-10-07'])));
    }

    /**
     * Create an instance of the EventBridge driver for broadcasting.
     */
    public function createCustomEventDriver(array $config): CustomEventBroadcaster
    {
//        $config = self::prepareConfigurationCredentials($config);

        return new CustomEventBroadcaster();
    }

    /**
     * Parse and prepare the AWS credentials needed by the AWS SDK library from the config.
     */
    public static function prepareConfigurationCredentials(array $config): array
    {
        if (static::configHasCredentials($config)) {
            $config['credentials'] = Arr::only($config, ['key', 'secret', 'token']);
        }

        return $config;
    }

    /**
     * Make sure some AWS credentials were provided to the configuration array.
     */
    private static function configHasCredentials(array $config): bool
    {
        return Arr::has($config, ['key', 'secret'])
            && Arr::get($config, 'key')
            && Arr::get($config, 'secret');
    }
}
