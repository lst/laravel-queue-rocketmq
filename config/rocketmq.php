<?php

/**
 * This is an example of queue connection configuration.
 * It will be merged into config/queue.php.
 * You need to set proper values in `.env`.
 */
return [

    'driver' => 'rocketmq',

    /*
     * Set to "horizon" if you wish to use Laravel Horizon.
     */
    'worker' => env('ROCKETMQ_WORKER', 'default'),

    'dsn' => env('ROCKETMQ_DSN', null),


    'instance_id' => env('ROCKETMQ_INSTANCE_ID', '127.0.0.1'),
    'namesrv' => env('ROCKETMQ_NAMESRV', '127.0.0.1'),
    'port' => env('ROCKETMQ_PORT', 7890),

    'access_key' => env('ROCKETMQ_ACCESS_KEY', 'guest'),
    'secret_key' => env('ROCKETMQ_SECRET_KEY', 'guest'),

    'queue' => env('ROCKETMQ_QUEUE', 'default'),
    'group' => env('ROCKETMQ_GROUP', 'default'),
    'queue_delay' => env('ROCKETMQ_QUEUE_DELAY', 'default'),

    'sleep_on_error' => env('RABBITMQ_ERROR_SLEEP', 5),
];
