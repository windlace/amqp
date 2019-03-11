<?php

namespace Cast\Amqp;

use PhpAmqpLib\Message\AMQPMessage;
use Thumper\BaseConsumer;

class Consumer extends BaseConsumer
{
    use DeadLetterExchangeTrait {
        setUpConsumerTrait as protected setUpConsumerTraitFacade;
    }

    public function consume()
    {
        $this->setUpConsumerTraitFacade();

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    /**
     * @param AMQPMessage $message
     */
    public function processMessage(AMQPMessage $message)
    {
        call_user_func($this->callback, $message->body);
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * Setup consumer.
     */
    protected function setUpConsumer()
    {
        if (isset($this->exchangeOptions['name'])) {
            $this->channel
                ->exchange_declare(
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

            if (!empty($this->consumerOptions['qos'])) {
                $this->channel
                    ->basic_qos(
                        $this->consumerOptions['qos']['prefetch_size'],
                        $this->consumerOptions['qos']['prefetch_count'],
                        $this->consumerOptions['qos']['global']
                    );
            }
        }

        list($queueName, , ) = $this->channel
            ->queue_declare(
                $this->queueOptions['name'],
                $this->queueOptions['passive'],
                $this->queueOptions['durable'],
                $this->queueOptions['exclusive'],
                $this->queueOptions['auto_delete'],
                $this->queueOptions['nowait'],
                $this->queueOptions['arguments'],
                $this->queueOptions['ticket']
            );

        if (isset($this->exchangeOptions['name'])) {
            if (is_array($this->routingKey)) {
                foreach ($this->routingKey as $rk) {
                    $this->channel->queue_bind($queueName, $this->exchangeOptions['name'], $rk);
                }
            } else {
                $this->channel->queue_bind($queueName, $this->exchangeOptions['name'], $this->routingKey);
            }
        }

        $this->channel
            ->basic_consume(
                $queueName,
                $this->getConsumerTag(),
                false,
                false,
                false,
                false,
                array($this, 'processMessage')
            );
    }
}
