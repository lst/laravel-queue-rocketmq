<?php

namespace lst\LaravelQueueRocketMQ\Queue\Connectors;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Queue\Connectors\ConnectorInterface;
use lst\LaravelQueueRocketMQ\Queue\RocketMQQueue;
use lst\LaravelQueueRocketMQ\Horizon\Listeners\RocketMQFailedEvent;
use lst\LaravelQueueRocketMQ\Horizon\RocketMQQueue as HorizonRocketMQQueue;
use MQ\MQClient;

class RocketMQConnector implements ConnectorInterface
{
    /**
     * @var Dispatcher
     */
    private $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return Queue
     * @throws \ReflectionException
     */
    public function connect(array $config): Queue
    {
        /** @var MQClient $client */
        $client = new MQClient(Arr::get($config, 'namesrv'), Arr::get($config, 'secret_key'),Arr::get($config, 'access_key'));
        $this->dispatcher->listen(WorkerStopping::class, function () use ($client) {
            //$client->close();
        });

        $worker = Arr::get($config, 'worker', 'default');

        if ($worker === 'default') {
            return new RocketMQQueue($client, $config);
        }

        if ($worker === 'horizon') {
            $this->dispatcher->listen(JobFailed::class, RocketMQFailedEvent::class);

            return new HorizonRocketMQQueue($client, $config);
        }

        if ($worker instanceof RocketMQQueue) {
            return new $worker($client, $config);
        }

        throw new InvalidArgumentException('Invalid worker.');
    }
}
