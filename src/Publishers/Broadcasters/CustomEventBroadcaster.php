<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Publishers\Broadcasters;

use App\Services\EventBridge\EventIngressClient;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @todo add different serializations to change the
 */
class CustomEventBroadcaster extends Broadcaster
{
    protected $client;

    protected ?string $source;

    protected ?string $domain;

//    public function __construct(EventBridgeClient $eventBridgeClient)
    public function __construct()
    {
        $this->client = config('event-flow.client') ?? EventIngressClient::class;

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
        $entries = $this->mapToEventBridgeEntries($channels, $event, $payload);

        $entries->each(function (array $entry) {
            app($this->client)->putEvent(
                $entry['Detail']['data'],
                $entry['DetailType'],
                $entry['Source'],
                $entry['EventBusName'],
            );
        });
    }

    protected function mapToEventBridgeEntries(array $channels, string $event, array $payload): Collection
    {
        // Remove the socket from the payload if it's there.
        Arr::forget($payload, 'socket');

        // What's left in the payload is just the model.
        $model = Arr::first($payload);

        // @todo dot separate studly cased event name

        return collect($channels)
            ->map(function ($channel) use ($event, $model) {
                return $this->mapToEventBridgeEntry($channel, $event, $model);
            });
    }

    protected function mapToEventBridgeEntry($channel, string $event, $model): array
    {
        $eventDetail = [
            'data' => $this->transformEventData($event, $model),
            'metadata' => $this->getEventMetadata($event),
        ];

        return [
            'Detail' => $eventDetail,
            'DetailType' => $event,
            'Source' => $this->source ?? '',
            'EventBusName' => $channel,
        ];
    }

    /**
     * Transform the model for the event.
     *
     * 1. A resource named by event name, e.g. OrderCreatedEventResource.
     * 2. A resource named by model name, e.g. OrderEventResource.
     * 3. The model itself.
     *
     * @todo add resource defined in event
     * @todo add resource defined in model itself
     * @todo test with multi word model and event names
     */
    protected function transformEventData(string $eventName, Model $model)
    {
        $eventResource = 'App\Events\Resources\\'.Str::studly(Str::of($eventName)->explode('.')->implode('_')).'EventResource';

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

    protected function getEventMetadata(string $eventName): array
    {
        $eventType = Str::of($eventName)->explode('.');

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

//    protected function getEventResource()
//    {
//
//    }
}
