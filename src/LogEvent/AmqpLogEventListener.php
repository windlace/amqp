<?php

namespace Cast\Amqp\LogEvent;

use Cast\Amqp\Amqp;

class AmqpLogEventListener
{
    /**
     * Create the event listener.
     *
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  LogEvent  $event
     * @return void
     */
    public function handle(LogEvent $event)
    {
        Amqp::publish('logstash.debug', json_encode($event->data));
    }
}
