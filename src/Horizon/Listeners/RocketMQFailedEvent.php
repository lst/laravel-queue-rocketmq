<?php

namespace lst\LaravelQueueRocketMQ\Horizon\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Horizon\Events\JobFailed as HorizonJobFailed;
use Illuminate\Queue\Events\JobFailed as LaravelJobFailed;
use lst\LaravelQueueRocketMQ\Queue\Jobs\RocketMQJob;

class RocketMQFailedEvent
{
    /**
     * The event dispatcher implementation.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    public $events;

    /**
     * Create a new listener instance.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher $events
     * @return void
     */
    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Queue\Events\JobFailed $event
     * @return void
     */
    public function handle(LaravelJobFailed $event) :void
    {
        if (! $event->job instanceof RocketMQJob) {
            return;
        }

        $this->events->dispatch((new HorizonJobFailed(
            $event->exception, $event->job, $event->job->getRawBody()
        ))->connection($event->connectionName)->queue($event->job->getQueue()));
    }
}
