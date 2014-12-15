<?php
namespace Eleme\Worker;

use \Config;

class ElemeJobQueue
{
    const QUEUE_KEY_PREFIX= 'L.ELEME:QUEUE:';
    const RESERVED_KEY_PREFIX = 'Z.ELEME:QUEUE:';
    const DELAY_KEY_PREFIX = 'Z.ELEME:QUEUE:';

    private $queueKey;
    private $reservedKey;
    private $reservedTimeout;
    private $delayKey;
    private $queueName;
    private $redis;

    public function __construct($queue)
    {
        $this->queueName = $queue;
        $this->queueKey = self::QUEUE_KEY_PREFIX . 'JOB:' . $queue;
        $this->reservedKey = self::RESERVED_KEY_PREFIX . 'RESERVED:' . $queue;
        $this->delayKey = self::DELAY_KEY_PREFIX . 'DELAY:'. $queue;
        $this->reservedTimeout  = Config::get('worker.timeout.reserved');
        $this->redis = app('redis')->connection();
    }

    public function queueName()
    {
        return $this->queueName;
    }

    public function pop()
    {
        $this->migrateAllDelayJobs();
        $this->migrateAllExpiredJobs();

        $job = $this->redis->rpop($this->queueKey);
        if ($job !== null) {
            $job = json_decode($job, true);
            $time = time() + $this->reservedTimeout;
            $job['time'] = $time;
            ksort($job);
            $this->redis->zadd($this->reservedKey, $time, json_encode($job));
        }

        return $job;
    }

    public function push($jobClass, $message)
    {
        $data = microtime() . substr(md5('BuildBranch' . '{"test":"test"}'), 0, 9);
        $id = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $job = array(
            'jobClass' => $jobClass,
            'message' => $message,
            'id' => $id
        );

        return $this->redis->lpush($this->queueKey, json_encode($job));
    }

    public function delay($job, $time = 30)
    {
        $time = $time + time();
        ksort($job);
        return $this->redis->zadd($this->delayKey, $time, json_encode($job));
    }

    public function removeJobFromReserved($job)
    {
        $time = $job['time'];
        ksort($job);
        return $this->redis->zrem($this->reservedKey, json_encode($job));
    }

    public function migrateAllExpiredJobs()
    {
        $options = ['cas' => true, 'watch' => $this->reservedKey, 'retry' => 5];
        $this->redis->transaction($options, function ($transaction) {
            $time = time();
            $this->migrateAndRepushJobs($transaction, $this->reservedKey, $this->queueKey, $time);
        });
    }

    public function migrateAllDelayJobs()
    {
        $options = ['cas' => true, 'watch' => $this->delayKey, 'retry' => 5];
        $this->redis->transaction($options, function ($transaction) {
            $time = time();
            $this->migrateAndRepushJobs($transaction, $this->delayKey, $this->queueKey, $time);
        });
    }

    public function migrateAndRepushJobs($transaction, $from, $to, $time)
    {
        $transaction->multi();
        $list = $this->redis->zrangebyscore($from, '-inf', $time);
        if (!empty($list)) {
            foreach ($list as $job) {
                $this->redis->lpush($to, $job);
            }
            $this->redis->zremrangebyscore($from, '-inf', $time);
        }
    }
}
