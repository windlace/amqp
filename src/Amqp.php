<?php

namespace Cast\Amqp;

use Illuminate\Support\Arr;
use PhpAmqpLib\Wire\AMQPTable;

class Amqp
{

    const EXCHANGE_PREFIX = 'exchange.';
    const QUEUE_PREFIX    = 'queue.';

    /**
     * @var string
     */
    public $host;
    /**
     * @var int
     */
    public $port;
    /**
     * @var string
     */
    public $user;
    /**
     * @var string
     */
    public $password;
    /**
     * @var string connection class
     */
    public $amqp_connection_class = \PhpAmqpLib\Connection\AMQPLazyConnection::class;
    /**
     * @var string
     */
    public $vhost = '/';
    /**
     * @var bool
     */
    public $insist = false;
    /**
     * @var string
     */
    public $login_method = 'AMQPLAIN';
    /**
     * @var mixed
     */
    public $login_response = null;
    /**
     * @var string
     */
    public $locale = 'en_US';
    /**
     * @var float
     */
    public $connection_timeout = 3.0;
    /**
     * @var float
     */
    public $read_write_timeout = 3.0;
    /**
     * @var null|mixed
     */
    public $context = null;
    /**
     * @var bool
     */
    public $keepalive = false;
    /**
     * @var float
     */
    public $heartbeat = 0;
    /**
     * @var null|AbstractConnection
     */
    protected $amqp_connection = null;

    public function __construct(
        $host     = null,
        $port     = null,
        $user     = null,
        $password = null,
        $vhost    = null,
        $insist   = false,
        $login_method = 'AMQPLAIN',
        $login_response = null,
        $locale = 'en_US',
        $connection_timeout = 60,
        $read_write_timeout = 60,
        $context = null,
        $keepalive = true,
        $heartbeat = 30
    )
    {

        $this->host               = config('queue.connections.rabbitmq.host',     $host);
        $this->port               = config('queue.connections.rabbitmq.port',     $port);
        $this->user               = config('queue.connections.rabbitmq.login',    $user);
        $this->password           = config('queue.connections.rabbitmq.password', $password);
        $this->vhost              = config('queue.connections.rabbitmq.vhost',    $vhost);
        $this->insist             = $insist;
        $this->login_method       = $login_method;
        $this->login_response     = $login_response;
        $this->locale             = $locale;
        $this->connection_timeout = $connection_timeout;
        $this->read_write_timeout = $read_write_timeout;
        $this->context            = $context;
        $this->keepalive          = $keepalive;
        $this->heartbeat          = $heartbeat;


        foreach (['host', 'user', 'port'] as $configParam)
        {
            if (empty($this->{$configParam})) {
                throw new \Exception("{$configParam} cannot be empty");
            }
        }
        if (!class_exists($this->amqp_connection_class))
        {
            throw new \Exception("connection class does not exist");
        }
    }

    /**
     * @return null|AbstractConnection
     */
    protected function getConnection()
    {
        if ($this->amqp_connection == null) {
            $this->amqp_connection = new $this->amqp_connection_class(
                $this->host,
                $this->port,
                $this->user,
                $this->password,
                $this->vhost,
                $this->insist,
                $this->login_method,
                $this->login_response,
                $this->locale,
                $this->connection_timeout,
                $this->read_write_timeout,
                $this->context,
                $this->keepalive,
                $this->heartbeat
            );
        }
        return $this->amqp_connection;
    }

    public static function listen($paramsKey, $callback)
    {
        $mqParams = self::getParam($paramsKey);

        $amqp = new self;
        $connection = $amqp->getConnection();

        $consumer = new Consumer($connection);

        $exchangeOptions = [
            'name' => static::EXCHANGE_PREFIX . $mqParams['exchangeName'],
            'type' => $mqParams['exchangeType']
        ];

        if($mqParams['exchangeType'] == 'x-delayed-message'){
            $exchangeOptions = array_merge($exchangeOptions,
                ['arguments' => new AMQPTable(["x-delayed-type" => 'direct'])]
            );
        }

        $consumer->setExchangeOptions($exchangeOptions);

        $consumer->setQueueOptions(['name' => static::QUEUE_PREFIX . $mqParams['queueName']]);

        if (isset($mqParams['dlxExchangeName'])) {
            $consumer->setDlxExchangeOptions(['name' => static::EXCHANGE_PREFIX . $mqParams['dlxExchangeName'], 'type' => $mqParams['dlxExchangeType']]);
            $consumer->setDlxQueueOptions(['name' => static::QUEUE_PREFIX . $mqParams['dlxQueueName']]);
            $consumer->setQueueOptions(['arguments' => new AMQPTable([
                "x-dead-letter-exchange" => static::EXCHANGE_PREFIX . $mqParams['dlxExchangeName'],
            ])]);
        }

        $consumer->setRoutingKey($mqParams['routingKey']);
        $consumer->setCallback($callback);
        $consumer->consume();
    }

    public static function publish($paramsKey, $msgBody, $delay = 0)
    {
        $mqParams = self::getParam($paramsKey);

        $amqp = new self;
        $connection = $amqp->getConnection();

        $producer = new Producer($connection);

        $exchangeOptions = [
            'name' => static::EXCHANGE_PREFIX.$mqParams['exchangeName'],
            'type' => $mqParams['exchangeType']
        ];

        if($mqParams['exchangeType'] == 'x-delayed-message'){
            $exchangeOptions = array_merge($exchangeOptions,
                ['arguments' => new AMQPTable(["x-delayed-type" => 'direct'])]
            );
        }

        $producer->setExchangeOptions($exchangeOptions);

        if ($delay > 0 && ($mqParams['exchangeType'] == 'x-delayed-message')) {
            $producer->setParameter('application_headers', new AMQPTable([
                "x-delay" => $delay,
            ]));
        }

        $producer->publish($msgBody, $mqParams['routingKey']);
    }

    public static function config()
    {
        return config('amqp.config');
    }

    public static function getParam($key, $default = NULL)
    {
        return Arr::get(self::config(), $key, $default);
    }

    public static function getChannel(){
        $amqp = new self;
        return $amqp->getConnection()->channel();
    }
}