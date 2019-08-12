<?php

namespace lst\LaravelQueueRocketMQ;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use lst\LaravelQueueRocketMQ\Queue\Connectors\RocketMQConnector;

class LaravelQueueRocketMQServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rocketmq.php', 'queue.connections.rocketmq'
        );
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('rocketmq', function () {
            return new RocketMQConnector($this->app['events']);
        });
    }
}
