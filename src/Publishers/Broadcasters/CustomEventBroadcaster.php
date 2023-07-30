<?php

declare(strict_types=1);

namespace Morscate\LaravelEventFlow\Publishers\Broadcasters;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @todo add different serializations to change the one use now is AWS event serialization
 */
class CustomEventBroadcaster extends Broadcaster
{
    protected mixed $client;

    protected ?string $source;

    protected ?string $domain;

    protected ?string $eventResource = null;

    public function __construct()
    {
        $this->client = config('event-flow.client');

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
        // Remove the socket from the payload if it's there.
        Arr::forget($payload, 'socket');

        // What's left in the payload is just the model.
        $model = Arr::first($payload);

        $this->eventResource = $this->getEventResource($event, $model);

        $data = $this->transformEventData($model);

        $metadata = $this->getEventMetadata($event);

        collect($channels)
            ->each(function ($channel) use ($event, $payload, $data, $metadata) {
                app($this->client)->putEvent(
                    $data,
                    $metadata,
                    $event,
                    $this->source ?? '',
                    $channel,
                );
            });
    }

    /**
     * Transform the model for the event.
     */
    protected function transformEventData(Model $model): array
    {
        if ($this->eventResource) {
            $data = new $this->eventResource($model);

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

        if ($this->eventResource::EVENT_VERSION) {
            $metadata['version'] = $this->eventResource::EVENT_VERSION;
        }

        return $metadata;
    }

    /**
     * Get the event resource.
     *
     * 1. A resource named by event name, e.g. OrderCreatedEventResource.
     * 2. A resource named by model name, e.g. OrderEventResource.
     * 3. The model itself.
     *
     * @note take into account that the broadcastAs can not be set in the event class.
     *
     * @todo add resource defined in event
     * @todo add resource defined in model itself
     * @todo test with multi word model and event names
     */
    protected function getEventResource(string $eventName, Model $model): ?string
    {
        $eventResourceClasses = [
            'App\Events\Resources\\'.Str::studly(Str::of($eventName)->explode('.')->implode('_')).'EventResource',
            'App\Events\Resources\\'.class_basename($model).'EventResource',
        ];

        foreach ($eventResourceClasses as $eventResourceClass) {
            if (class_exists($eventResourceClass)) {
                return $eventResourceClass;
            }
        }

        return null;
    }
}
