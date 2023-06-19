# Event Flow
Event Flow helps you to create event driven applications with Laravel.

The Event flow package consists of two parts: a publisher and a subscriber. Publishers publish events that happen within the publishing application. Subscribers listen to events that happened and process them.

## Prerequisites
1. This package needs to installed and configured in the publishing and subscribing Laravel applications.
2. At least one AWS SQS Queue - one queue per Laravel application subscribing
3. At least one AWS EventBridge Event Bus and AWS SNS Topic
4. An EventBridge rule that sends events from your Event Bus to your SNS Topic
5. An SQS subscription between your SNS Topic and your SQS Queue with "raw message delivery" disabled
6. The relevant Access policies configured, especially if you want to be able to publish messages directly from the AWS Console.

## Installation
This package is compatible with Laravel 9+. You can install the package via composer:

```
composer require "morscate/laravel-event-flow"
```

## Publish
To

## Subscribe
To
