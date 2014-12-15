<?php
namespace Eleme\Worker\Report;

use \Config;

class WorkerReport implements WorkerReportInterface
{
    const REPORT_KEY = "L:ELEME:WORKER:REPORT:";
    const PING_KEY = 'Z:ELEME:WORKER:PING';
    const WORKING_KEY = 'H:ELEME:WORKER:';
    const OFFDUTY_KEY = 'K:ELEME:WORKER:OFF:DUTY:';

    private $redis;
    private $pid;
    private $timeout;
    private $workingKey;
    private $reportingKey;

    public function __construct($pid, $queueName)
    {
        $this->pid = $pid;
        $this->redis = app('redis')->connection();
        $this->timeout = Config::get('worker.timeout.worker');
        $this->workingKey = self::WORKING_KEY . $pid;
        $this->reportingKey = self::REPORT_KEY . $pid;

        $this->redis->hset($this->workingKey, 'queue', $queueName);
    }

    public function report($info)
    {
        //这个功能暂时不实现
        //$this->redis->rpush($this->reportingKey, $info);
        $this->redis->zadd(self::PING_KEY, time() + $this->timeout, $this->pid);
    }

    public function reportJob($info)
    {
        $this->redis->hset($this->workingKey, 'job', $info);
    }

    public function clear()
    {
        $this->redis->del(self::OFFDUTY_KEY . $this->pid);
        $this->redis->del($this->workingKey);
        $this->redis->zrem(self::PING_KEY, $this->pid);
    }

    public function offDuty()
    {
        return $this->redis->get(self::OFFDUTY_KEY . $this->pid);
    }

    public function isWorking()
    {
        $expireTime = (int)$this->redis->zscore(self::PING_KEY, $this->pid);
        return $expireTime > time();
    }

    public static function clearAllPids()
    {
        self::clearPidsByScore('-inf', 'inf');
    }

    public static function sendOffDutySignal($pid)
    {
        $redis = app('redis')->connection();
        $score = $redis->zscore(self::PING_KEY, $pid);
        if ($score === null) {
            return false;
        }
        $key = self::OFFDUTY_KEY . $pid;
        return $redis->setex($key, 60 * 60, 1);
    }

    public static function clearNoResponsePids()
    {
        self::clearPidsByScore('-inf', time());;
    }

    private static function clearPidsByScore($start, $end)
    {
        $redis = app('redis')->connection();
        $list = $redis->zrangebyscore(WorkerReport::PING_KEY, $start, $end);
        foreach ($list as $pid) {
            $key = self::WORKING_KEY . $pid;
            $redis->del($key, self::OFFDUTY_KEY . $pid);
        }
        $redis->zremrangebyscore(self::PING_KEY, $start, $end);
    }

    public function clearReport()
    {
        $this->redis->del($this->reportingKey);
    }

    public static function workerList()
    {
        $redis = app('redis')->connection();
        $list = $redis->zrangebyscore(WorkerReport::PING_KEY, '-inf', 'inf', 'WITHSCORES');
        $workers = array();
        foreach ($list as $m) {
            $key = self::WORKING_KEY . $m[0];
            $workers[] = array(
                'pid' => $m[0],
                'status' => $m[1] > time() ? 'NORMAL' : 'NO RESPONSE',
                'job' => $redis->hget($key, 'job'),
                'queue' => $redis->hget($key, 'queue')
            );
        }
        usort($workers, function ($a, $b) {
            return intval($a['pid']) > intval($b['pid']);
        });
        return $workers;
    }
}
