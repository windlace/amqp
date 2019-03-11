<?php

namespace Cast\Amqp\LogEvent;

use Illuminate\Queue\SerializesModels;

abstract class Event
{
    use SerializesModels;
}
