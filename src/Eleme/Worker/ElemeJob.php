<?php
namespace Eleme\Worker;


interface ElemeJob
{
    public function descriptYourself($message);
    public function fire(Worker $worker, $message);
}
