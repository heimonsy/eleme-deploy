<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Eleme\Worker\Supervisor;
use Eleme\Worker\ElemeJobQueue;

class WorkerCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'worker:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'start to listen work queue';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $queue = $this->argument('queue');
        if (empty($queue)) $queue = 'default';

        $pid = getmypid();
        Log::info("New Worker Start, pid $pid, queue $queue");

        $handler = function ($signal) {
            if ($signal = SIGINT) {
                echo "You can't stop Worker here\n";
            } elseif ($signal = SIGHUP) {
                Log::info("RECV SIGHUP");
            }
        };
        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGHUP, $handler); // php-fpm stop 的时会发送这个信号

        $queue = new ElemeJobQueue($queue);
        $supervisor = new Supervisor($queue, $pid);
        $worker = $supervisor->getWorker();
        $worker->listen();

    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('queue', InputArgument::OPTIONAL, 'the queue to listen'),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
        );
    }

}
