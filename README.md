# mq
消息队列

## 开源地址
https://github.com/JZhao1020/mq

##1.安装
```
composer require hao/mq
```

##2.1 定义一个生产者（延迟消息）
```
$delay = new \mq\RabbitMQ();

$ttl            = 1000 * 100;//订单100s后超时
$delayExName    = 'delay-order-exchange';//超时exchange
$delayQueueName = 'delay-order-queue';//超时queue
$queueName      = 'ttl-order-queue';//订单queue

$delay->createQueue($ttl, $delayExName, $delayQueueName, $queueName);
$data = [
    'order_no' => time(),
    'remark'   => 'this is a order test'
];
$delay->sendMessage(json_encode($data), $queueName);
```

##2.2 定义一个消费者
```
$delay = new \mq\RabbitMQ();
$delayQueueName = 'delay-order-queue';

$callback = function ($msg) {
    echo $msg->body . PHP_EOL;
    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

    //处理订单超时逻辑，给用户推送提醒等等。。。
    sleep(10);
};

/**
 * 消费已经超时的订单信息，进行处理
 */
$delay->setQos(1);
$delay->consumeMessage($delayQueueName, $callback);
```

##3.1 定义一个生产者
```
$delay = new \mq\RabbitMQ();
$exName    = 'order-exchange';//exchange
$queueName      = 'order-queue';//订单queue
$delay->createExchange($exName, $queueName);

$data = [
    'order_no' => time(),
    'remark'   => 'this is a order test'
];
$delay->sendMessage(json_encode($data), $queueName);
  
```

##3.2 定义一个消费者
```
$delay = new \mq\RabbitMQ();
$delayQueueName = 'order-queue';

$callback = function ($msg) {
    echo $msg->body . PHP_EOL;
    $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);

    //处理订单超时逻辑，给用户推送提醒等等。。。
    sleep(10);
};

/**
 * 消费已经超时的订单信息，进行处理
 */
$delay->setQos(1);
$delay->consumeMessage($delayQueueName, $callback);
  
```