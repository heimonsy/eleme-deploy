<?php
namespace Eleme\Worker;

use Symfony\Component\Process\Process;
use SSHProcess\SSHProtocolTrait;

class GitProcess extends Process
{
    use SSHProtocolTrait;

    public function __construct($cmd, $cwd = null, $identityfile = null, $passphrase = null, $timeout = 180)
    {
        if (!empty($passphrase)) {
            $command = base_path() . '/scripts/git.sh -i ' . $identityfile . ' ' . $cmd;
            $commandline = $this->expectWithPassphrase($command, $passphrase, $timeout);
        } elseif (!empty($identityfile)) {
            $command = base_path() . '/scripts/git.sh -i ' . $identityfile . ' ' . $cmd;
            $commandline = $this->expect($command, $timeout);
        } else {
            $command = $cmd;
            $commandline = $this->expect($cmd, $timeout);
        }
        parent::__construct($commandline, $cwd);
    }

}
