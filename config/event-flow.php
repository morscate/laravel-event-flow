<?php

return [
    'client' => env('EVENTFLOW_CLIENT'),

    'event_source' => env('EVENTFLOW_EVENT_SOURCE'),

    'event_domain' => env('EVENTFLOW_EVENT_DOMAIN'),

    'queue_connection' => env('EVENTFLOW_QUEUE_CONNECTION', 'sqs-events'),

    'queue' => env('EVENTFLOW_QUEUE'),
];
