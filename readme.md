### Install

```bash
composer require cast/amqp
```

### Setup event listener:
```php
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        // ...
        LogEvent::class => [
            AmqpLogEventListener::class
        ],
    ];
```
