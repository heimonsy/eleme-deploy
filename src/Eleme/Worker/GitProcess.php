<?php
namespace Eleme\Worker;

use Symfony\Component\Process\Process;
use SSHProcess\SSHProtocolTrait;

class GitProcess extends Process
{
    use SSHProtocolTrait;

    public function __construct($cmd, $cwd = null)
    {
        $commandline = $this->expect($cmd, 600);
        parent::__construct($commandline, $cwd);
    }

}
