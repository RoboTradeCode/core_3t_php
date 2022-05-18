<?php

namespace Src;

class DiscreteTime
{

    public function proof(): bool
    {

        $var = str_split(time());

        $var = end($var);

        if (!isset($this->previous) || (in_array($var, [1, 3, 5, 7, 9]) && $this->previous != $var)) {

            $this->previous = $var;

            return true;

        }

        return false;

    }

}