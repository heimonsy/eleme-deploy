<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ScavengerCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'deploy:scavenger';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear old commit';

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
        //
        $sc = new Scavenger;
        $sc->clear();
        $this->info("清理成功");
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
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
