<?php

namespace Cast\Amqp;

trait DeadLetterExchangeTrait
{
    /**
     * @var array
     */
    protected $dlxExchangeOptions = array(
        'passive' => false,
        'durable' => true,
        'auto_delete' => false,
        'internal' => false,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null
    );

    /**
     * @var array
     */
    protected $dlxQueueOptions = array(
        'name' => '',
        'passive' => false,
        'durable' => true,
        'exclusive' => false,
        'auto_delete' => false,
        'nowait' => false,
        'arguments' => null,
        'ticket' => null
    );

    /**
     * Verifies exchange name meets the 0.9.1 protocol standard.
     *
     * letters, digits, hyphen, underscore, period, or colon
     *
     * @param string $exchangeName
     * @return bool
     */
    private function isValidExchangeName($exchangeName)
    {
        return preg_match('/^[A-Za-z0-9_\-\.\;]*$/', $exchangeName);
    }

    /**
     * @param array $options
     */
    public function setDlxExchangeOptions(array $options)
    {
        if (!isset($options['name']) || !$this->isValidExchangeName($options['name'])) {
            throw new \InvalidArgumentException(
                'You must provide a dlx exchange name'
            );
        }

        if (empty($options['type'])) {
            throw new \InvalidArgumentException(
                'You must provide a dlx exchange type'
            );
        }

        $this->dlxExchangeOptions = array_merge(
            $this->dlxExchangeOptions,
            $options
        );
    }

    /**
     * @param array $options
     */
    public function setDlxQueueOptions(array $options)
    {
        $this->dlxQueueOptions = array_merge(
            $this->dlxQueueOptions,
            $options
        );
    }

    /**
     * Setup consumer.
     */
    protected function setUpConsumerTrait()
    {
        if (isset($this->dlxExchangeOptions['name'])) {
            $this->channel
                ->exchange_declare(
                    $this->dlxExchangeOptions['name'],
                    $this->dlxExchangeOptions['type'],
                    $this->dlxExchangeOptions['passive'],
                    $this->dlxExchangeOptions['durable'],
                    $this->dlxExchangeOptions['auto_delete'],
                    $this->dlxExchangeOptions['internal'],
                    $this->dlxExchangeOptions['nowait'],
                    $this->dlxExchangeOptions['arguments'],
                    $this->dlxExchangeOptions['ticket']
                );

            if (!empty($this->consumerOptions['qos'])) {
                $this->channel
                    ->basic_qos(
                        $this->consumerOptions['qos']['prefetch_size'],
                        $this->consumerOptions['qos']['prefetch_count'],
                        $this->consumerOptions['qos']['global']
                    );
            }

            list($queueName, , ) = $this->channel
                ->queue_declare(
                    $this->dlxQueueOptions['name'],
                    $this->dlxQueueOptions['passive'],
                    $this->dlxQueueOptions['durable'],
                    $this->dlxQueueOptions['exclusive'],
                    $this->dlxQueueOptions['auto_delete'],
                    $this->dlxQueueOptions['nowait'],
                    $this->dlxQueueOptions['arguments'],
                    $this->dlxQueueOptions['ticket']
                );

            if (is_array($this->routingKey)) {
                foreach ($this->routingKey as $rk) {
                    $this->channel->queue_bind($queueName, $this->dlxExchangeOptions['name'], $rk);
                }
            } else {
                $this->channel->queue_bind($queueName, $this->dlxExchangeOptions['name'], $this->routingKey);
            }
        }

        $this->setUpConsumer();
    }
}
