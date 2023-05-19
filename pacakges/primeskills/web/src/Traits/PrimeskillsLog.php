<?php

namespace Primeskills\Web\Traits;

use Symfony\Component\Console\Output\ConsoleOutput;

trait PrimeskillsLog
{

    /**
     * @return Commands
     */
    public function write(): Commands
    {
        return new Commands(new ConsoleOutput());
    }
}
