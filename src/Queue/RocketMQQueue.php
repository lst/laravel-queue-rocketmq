<?php

namespace lst\LaravelQueueRocketMQ\Queue;

use RuntimeException;
use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use MQ\Model\TopicMessage;
use MQ\MQClient;
use Psr\Log\LoggerInterface;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use lst\LaravelQueueRocketMQ\Queue\Jobs\RocketMQJob;

class RocketMQQueue extends Queue implements QueueContract
{
    protected $sleepOnError;

    protected $instanceId;
    protected $queueName;
    protected $queueOptions;
    protected $group;
    protected $queueDelayName;

    protected $producer;
    protected $producerDelay;
    protected $consumer;
    protected $consumerDelay;

    /**
     * @var MQClient
     */
    protected $client;
    protected $correlationId;

    public function __construct(MQClient $client, array $config)
    {
        $this->client = $client;

        $this->queueName = $config['queue'] ?? $config['options']['queue']['name'];
        $this->group = $config['group'] ?? $config['options']['group']['name'];
        $this->queueDelayName = $config['queue_delay'] ?? $config['options']['queue_delay']['name'];
        $this->queueOptions = $config['options']['queue'];
        $this->queueOptions['arguments'] = isset($this->queueOptions['arguments']) ?
            json_decode($this->queueOptions['arguments'], true) : [];

        $this->sleepOnError = $config['sleep_on_error'] ?? 5;
    }

    /** {@inheritdoc} */
    public function size($queueName = null): int
    {
        return 2;
    }

    /** {@inheritdoc} */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, []);
    }

    /** {@inheritdoc} */
    public function pushRaw($payload, $queueName = null, array $options = [])
    {
        try {

            $message = new TopicMessage($payload);

            if (isset($options['priority'])) {
                $message->putProperty('priority', $options['priority']);
            }

            if (isset($options['attempts'])) {
                $message->putProperty(RocketMQJob::ATTEMPT_COUNT_HEADERS_KEY, $options['attempts']);
            }

            if (isset($options['delay']) && $options['delay'] > 0) {
                $message->setStartDeliverTime(time() * 1000 + $options['delay'] * 1000);
            }

            $producer = $this->client->getProducer($this->instanceId, $queueName);
            $result = $producer->publishMessage($message);
            $this->setCorrelationId($result->getMessageId());

            return $result->getMessageId();
        } catch (\Exception $exception) {
            $this->reportConnectionError('pushRaw', $exception);

            return;
        }
    }

    /** {@inheritdoc} */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, ['delay' => $this->secondsUntil($delay)]);
    }

    /**
     * Release a reserved job back onto the queue.
     *
     * @param  \DateTimeInterface|\DateInterval|int $delay
     * @param  string|object $job
     * @param  mixed $data
     * @param  string $queue
     * @param  int $attempts
     * @return mixed
     */
    public function release($delay, $job, $data, $queue, $attempts = 0)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, [
            'delay' => $this->secondsUntil($delay),
            'attempts' => $attempts,
        ]);
    }

    /** {@inheritdoc} */
    public function pop($queueName = null)
    {
        try {

            $consumer = $this->client->getConsumer($this->instanceId, $queueName, $this->group);

            if ($messages = $consumer->consumeMessage(1)) {
                return new RocketMQJob($this->container, $this, $consumer, $messages[0]);
            }
        } catch (\Throwable $exception) {
            $this->reportConnectionError('pop', $exception);

            return;
        }
    }

    /**
     * Retrieves the correlation id, or a unique id.
     *
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId ?: uniqid('', true);
    }

    /**
     * Sets the correlation id for a message to be published.
     *
     * @param string $id
     *
     * @return void
     */
    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    /**
     * @return MQClient
     */
    public function getClient(): MQClient
    {
        return $this->client;
    }

    protected function getQueueName($queueName = null)
    {
        return $queueName ?: $this->queueName;
    }

    protected function createPayloadArray($job, $queue, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'id' => $this->getRandomId(),
        ]);
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    protected function getRandomId(): string
    {
        return Str::random(32);
    }

    /**
     * @param string $action
     * @param \Throwable $e
     * @throws \Exception
     */
    protected function reportConnectionError($action, \Throwable $e)
    {
        /** @var LoggerInterface $logger */
        $logger = $this->container['log'];

        $logger->error('Rocketmq error while attempting '.$action.': '.$e->getMessage());

        // If it's set to false, throw an error rather than waiting
        if ($this->sleepOnError === false) {
            throw new RuntimeException('Error writing data to the connection with RocketMQ', null, $e);
        }

        // Sleep so that we don't flood the log file
        sleep($this->sleepOnError);
    }
}
