<?php

namespace Cast\Amqp\LogEvent;

class LogEvent extends Event
{
    /**
     * @var array
     */
    public $data;

    /**
     * Create a new event instance.
     *
     * @param array $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }
}
