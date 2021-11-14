<?php

namespace Te\Protocols;

interface Protocol
{
    public function len($data);

    public function encode($data = '');

    public function decode($data = '');

    public function msgLen($data = '');
}