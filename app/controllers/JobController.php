<?php

use Eleme\Worker\Report\WorkerReport;
use Symfony\Component\Process\Process;


class JobController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $queues = Config::get('worker.queue');
        //WorkerReport::clearNoResponsePids();

        return View::make('job/index', array(
            'queues' => $queues,
        ));
    }

    public function newWorker()
    {
        $queue = Input::get('queue');
        $root = base_path();
        $cmd = 'php artisan worker:start ' . $queue;
        $p = new Symfony\Component\Process\Process($cmd, $root);
        $p->start();
        return Response::json(array('res' => 0, 'pid' => $p->getPid()));
    }

    public function process()
    {
        $workers = WorkerReport::workerList();

        return Response::json($workers);
    }

    public function clearNoResponse()
    {
        WorkerReport::clearNoResponsePids();
        return Response::json(array(
            'res' => 0
        ));
    }

    public function shutdownProcess()
    {
        $pid = Input::get('pid');
        if (!is_numeric($pid)) {
            return Response::json(array('res' => 1, 'info' => 'Pid Not A number'));
        }
        WorkerReport::sendOffDutySignal($pid);
        return Response::json(array('res' => 0, 'info' => 'ok'));
    }

}
