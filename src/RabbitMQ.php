<?php


namespace mq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQ
{
    public $config = [
        'host'               => '127.0.0.1',    //ip
        'port'               => 5672,           //端口号
        'user'               => 'guest',        //用户
        'password'           => 'guest',        //密码
        'vhost'              => '/',            //虚拟host
        'insist'             => false,
        'login_method'       => 'AMQPLAIN',
        'login_response'     => null,
        'locale'             => 'en_US',
        'connection_timeout' => 60,
        'read_write_timeout' => 60,
        'context'            => null,
        'keepalive'          => true,
        'heartbeat'          => 30
    ];

    protected $connection;
    protected $channel;


    /**
     * RabbitMQ constructor.
     */
    public function __construct($config = [])
    {
        if($config) $this->setConfig($config);
        $host               = $this->config['host'];
        $port               = $this->config['port'];
        $user               = $this->config['user'];
        $password           = $this->config['password'];
        $vhost              = $this->config['vhost'];
        $insist             = $this->config['insist'];
        $login_method       = $this->config['login_method'];
        $login_response     = $this->config['login_response'];
        $locale             = $this->config['locale'];
        $connection_timeout = $this->config['connection_timeout'];
        $read_write_timeout = $this->config['read_write_timeout'];
        $context            = $this->config['context'];
        $keepalive          = $this->config['keepalive'];
        $heartbeat          = $this->config['heartbeat'];
        $this->connection   = new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password,
            $vhost,
            $insist,
            $login_method,
            $login_response,
            $locale,
            $connection_timeout,
            $read_write_timeout,
            $context,
            $keepalive,
            $heartbeat
        );
        $this->channel      = $this->connection->channel();
    }

    //重新设置MQ的链接配置
    public function setConfig($config)
    {
        if (!is_array($config)) {
            throw new \Exception('config不是一个数组');
        }
        foreach ($config as $key => $value) {
            $this->config[$key] = $value;
        }
    }

    /**
     * 创建延时队列
     * @param $ttl
     * @param $delayExName
     * @param $delayQueueName
     * @param $queueName
     */
    public function createQueue($ttl, $delayExName, $delayQueueName, $queueName)
    {
        $args = new AMQPTable([
            'x-dead-letter-exchange'    => $delayExName,
            'x-message-ttl'             => $ttl, //消息存活时间
            'x-dead-letter-routing-key' => $queueName
        ]);
        $this->channel->queue_declare($queueName, false, true, false, false, false, $args);
        //绑定死信queue
        $this->channel->exchange_declare($delayExName, AMQPExchangeType::DIRECT, false, true, false);
        $this->channel->queue_declare($delayQueueName, false, true, false, false);
        $this->channel->queue_bind($delayQueueName, $delayExName, $queueName, false);
    }

    /**
     * 创建实时队列
     * @param $exchangeName
     * @param $queueName
     */
    public function createExchange($exchangeName, $queueName)
    {
        //创建交换机$channel->exchange_declare($exhcange_name,$type,$passive,$durable,$auto_delete);
        //passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
        //durable：true、false true：服务器重启会保留下来Exchange。警告：仅设置此选项，不代表消息持久化。即不保证重启后消息还在
        //autoDelete:true、false.true:当已经没有消费者时，服务器是否可以删除该Exchange
        $this->channel->exchange_declare($exchangeName, 'direct', false, true, false);
        //passive: 消极处理， 判断是否存在队列，存在则返回，不存在直接抛出 PhpAmqpLib\Exception\AMQPProtocolChannelException 异常
        //durable：true、false true：在服务器重启时，能够存活
        //exclusive ：是否为当前连接的专用队列，在连接断开后，会自动删除该队列
        //autodelete：当没有任何消费者使用时，自动删除该队列
        //arguments: 自定义规则
        $this->channel->queue_declare($queueName, false, true, false, false, false);
    }

    /**
     * 生成信息
     * @param $message
     */
    public function sendMessage($message, $routeKey, $exchange = '', $properties = [])
    {
        $data = new AMQPMessage(
            $message, $properties
        );
        $this->channel->basic_publish($data, $exchange, $routeKey);
    }

    /*
     * 设置消费者预取消息数量
     * @param string|int $count 预取消息数量
     */
    public function setQos($count = 1){
        $this->channel->basic_qos(null, $count, null);
    }

    /**
     * 消费消息
     * @param $queueName
     * @param $callback
     * @throws \ErrorException
     */
    public function consumeMessage($queueName,$callback)
    {
        $this->channel->basic_consume($queueName, '', false, false, false, false, $callback);
        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * @throws \Exception
     */
    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}