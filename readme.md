# Send parcels using the Sendcloud Shipping API

[![tests](https://github.com/sander3/laravel-sendcloud/workflows/Laravel/badge.svg)](https://github.com/sander3/laravel-sendcloud/actions?query=workflow%3ALaravel)

## Requirements

- PHP >= 8.0
- Laravel >= 8.0

## Webhooks
You can setup webhooks by registering the route in the `RouteServiceProvider` just below the route groups that pull in the `routes/api` and `routes/web` files:
```
$this->routes(function () {
    // ...
    Route::sendcloudWebhooks();
});
```
This gives you a new endpoint in your application: `sendcloud/webhooks`. All webhooks sent to this endpoint will trigger an event, which you can listen to in your `EventServiceProvider`:
```
protected $listen = [
    'sendcloud_webhook.parcel_status_changed' => [
        UpdateParcelStatusFromWebhook::class,
    ],
];
```

## Security Vulnerabilities

If you discover a security vulnerability within this project, please send an e-mail to Sander de Vos via [sander@tutanota.de](mailto:sander@tutanota.de). All security vulnerabilities will be promptly addressed.
