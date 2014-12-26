<?php
namespace Eleme\Worker;
use Log;

class Worker
{
    private $supervisor;
    private $job;

    public function __construct($supervisor)
    {
        $this->supervisor = $supervisor;
        $this->supervisor->workerReporter->reportJob('STARTED');
    }

    public function listen()
    {
        $attempts = 0;
        $this->supervisor->workerReporter->reportJob('LISTENING');

        while (true) {
            if ($this->supervisor->canIOffDuty()) break;
            $this->report('PING');

            if ($this->job = $this->supervisor->newJob()) {
                Log::info("Worker [{$this->supervisor->pid}], recv job [ {$this->getJobId()} ]");
                $attempts = 0;
                try {
                    // 不允许输出, 当Command是通过http请求创建时，进程输出内容会使进程意外结束
                    ob_start();
                    $this->start($this->job);
                    ob_get_clean();
                    Log::info("Worker [{$this->supervisor->pid}], job done [ {$this->getJobId()} ]");
                } catch (\Exception $e) {
                    Log::info("Worker [{$this->supervisor->pid}], job err  [ {$this->getJobId()} ]");
                    Log::error($e);
                    $this->supervisor->workerReporter->reportJob('LISTENING');
                }
                $this->supervisor->removeJobFromReserved($this->job);
            } else {
                if ($attempts !== 0) {
                    sleep(3);
                }
                $attempts++ ;
            }
            pcntl_signal_dispatch();
        }
        Log::info("Worker [{$this->supervisor->pid}], off duty");
        $this->supervisor->offDuty();
    }

    public function getJobId()
    {
        return $this->job['id'];
    }

    public function start($job)
    {
        $class = 'Eleme\Worker\Job\\' . $job['jobClass'];
        if (!class_exists($class)) {
            throw new \Exception("job类 {$class} 不存在");
        }

        $reflection = new \ReflectionClass($class);
        if (!$reflection->isSubclassOf('Eleme\Worker\ElemeJob')) {
            throw new \Exception("job类 {$class} 没有继承ElemeJob");
        }

        $instance = $reflection->newInstance();
        $this->supervisor->workerReporter->reportJob($instance->descriptYourself($job['message']));
        $instance->fire($this, $job['message']);
        $this->supervisor->workerReporter->reportJob('LISTENING');
    }

    public function release($time = 30)
    {
        $this->supervisor->release($this->job, $time);
    }

    public function report($info)
    {
        $this->supervisor->report($info);
    }
}
