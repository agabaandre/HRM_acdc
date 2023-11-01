<?php
use React\EventLoop\LoopInterface;
use React\EventLoop\Factory;

class Reactphp_lib
{
private $loop;

public function __construct()
{
$this->loop = Factory::create();
}

public function getLoop(): LoopInterface
{
return $this->loop;
}

public function run()
{
$this->loop->run();
}
}
