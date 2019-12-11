<?php

namespace Cast\Amqp;

use PhpAmqpLib\Message\AMQPMessage;
use Thumper\BaseAmqp;

class Producer extends BaseAmqp
{
    use DeadLetterExchangeTrait;

    /**
     * @var bool
     */
    protected $exchangeReady = false;

    /**
     * @param string $messageBody
     * @param string $routingKey
     */
    public function publish($messageBody, $routingKey = '')
    {
        if (!$this->exchangeReady) {
            if (isset($this->exchangeOptions['name'])) {
                //declare a durable non autodelete exchange
                $this->channel->exchange_declare(
                    $this->exchangeOptions['name'],
                    $this->exchangeOptions['type'],
                    $this->exchangeOptions['passive'],
                    $this->exchangeOptions['durable'],
                    $this->exchangeOptions['auto_delete'],
                    $this->exchangeOptions['internal'],
                    $this->exchangeOptions['nowait'],
                    $this->exchangeOptions['arguments'],
                    $this->exchangeOptions['ticket']
                );
            }
            $this->exchangeReady = true;
        }

        $this->setParameter('delivery_mode', self::PERSISTENT);

        $message = new AMQPMessage(
            $messageBody,
            $this->getParameters()
        );
        $this->channel->basic_publish(
            $message,
            $this->exchangeOptions['name'],
            !empty($routingKey) ? $routingKey : $this->routingKey
        );
    }
}
