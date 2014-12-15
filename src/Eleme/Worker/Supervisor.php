<?php
namespace Eleme\Worker;

use Eleme\Worker\Report\WorkerReport;
use Eleme\Worker\Report\WorkerReportInterface;

class Supervisor
{
    private static $repoters = array();
    private $worker = null;

    private $queue;
    public $pid;
    public $workerReporter;

    public function __construct(ElemeJobQueue $queue, $workPid)
    {
        $this->pid = $workPid;
        $this->queue = $queue;
        $this->workerReporter = new WorkerReport($workPid, $queue->queueName());
        self::registerRepoter('WorkerReport', $this->workerReporter);
    }

    public function newJob()
    {
        return $this->queue->pop();
    }

    public function removeJobFromReserved($job)
    {
        return $this->queue->removeJobFromReserved($job);
    }

    public static function registerRepoter($repoterName, WorkerReportInterface $reporter)
    {
        self::$repoters[$repoterName] = $reporter;
    }

    public static function unregisterRepoter($repoterName)
    {
        unset(self::$repoters[$repoterName]);
    }

    public function report($info)
    {
        foreach (self::$repoters as $repoter) {
            $repoter->report($info);
        }
    }

    public static function push($jobClass, $message, $queue = 'default')
    {
        return (new ElemeJobQueue($queue))->push($jobClass, $message);
    }

    public function getWorker()
    {
        if ($this->worker === null) {
            $this->worker = new Worker($this);
        }
        return $this->worker;
    }

    public function clear()
    {
        $this->workerReporter->clear();
    }

    public function canIOffDuty()
    {
        return $this->workerReporter->offDuty();
    }

    public function offDuty()
    {
        $this->workerReporter->clear();
    }

    public function release($job, $time = 30)
    {
        $this->queue->delay($job, $time);
        $this->queue->removeJobFromReserved($job);
    }
}
