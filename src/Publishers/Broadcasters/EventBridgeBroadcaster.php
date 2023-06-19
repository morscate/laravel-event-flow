<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Publishers\Broadcasters;

use Aws\EventBridge\EventBridgeClient;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @todo add different serializations to change the
 */
class EventBridgeBroadcaster extends Broadcaster
{
    protected EventBridgeClient $eventBridgeClient;

    protected ?string $source;

    protected ?string $domain;

    public function __construct(EventBridgeClient $eventBridgeClient)
    {
        $this->eventBridgeClient = $eventBridgeClient;

        $this->source = config('event-flow.event_source');

        $this->domain = config('event-flow.event_domain');
    }

    /**
     * {@inheritDoc}
     */
    public function auth($request)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function validAuthenticationResponse($request, $result)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $events = $this->mapToEventBridgeEntries($channels, $event, $payload);

        $this->eventBridgeClient->putEvents([
            'Entries' => $events,
        ]);
    }

    protected function mapToEventBridgeEntries(array $channels, string $event, array $payload): array
    {
        // Remove the socket from the payload if it's there.
        Arr::forget($payload, 'socket');

        // What's left in the payload is just the model.
        $model = Arr::first($payload);

        return collect($channels)
            ->map(function ($channel) use ($event, $model) {
                return $this->mapToEventBridgeEntry($channel, $event, $model);
            })
            ->all();
    }

    protected function mapToEventBridgeEntry($channel, string $event, Model $model): array
    {
        $eventDetail = [
            'data' => $this->transformEventData($event, $model),
            'metadata' => $this->getEventMetadata($event),
        ];

        return [
            'Detail' => json_encode($eventDetail),
            'DetailType' => $event,
            'EventBusName' => $channel,
            'Source' => $this->source ?? '',
        ];
    }

    /**
     * Transform the model for the event.
     *
     * 1. A resource named by event name, e.g. OrderCreatedEventResource.
     * 2. A resource named by model name, e.g. OrderEventResource.
     * 3. The model itself.
     *
     * @todo test with multi word model and event names
     */
    protected function transformEventData(string $event, Model $model)
    {
        $eventResource = 'App\Events\Resources\\'.Str::studly(Str::of($event)->explode('.')->implode('_')).'EventResource';

        if (class_exists($eventResource)) {
            $data = new $eventResource($model);

            return $data->toArray(null);
        }

        $eventResource = 'App\Events\Resources\\'.class_basename($model).'EventResource';

        if (class_exists($eventResource)) {
            $data = new $eventResource($model);

            return $data->toArray(null);
        }

        return $model->toArray();
    }

    protected function getEventMetadata(string $event): array
    {
        $eventType = Str::of($event)->explode('.');

        $metadata = [
            'correlation_id' => (string) Str::orderedUuid(),
            'type' => $eventType->first(),
            'status' => $eventType->last(),
        ];

        if ($this->source) {
            $metadata['service'] = $this->source;
        }

        if ($this->domain) {
            $metadata['domain'] = $this->domain;
        }

        return $metadata;
    }
}
