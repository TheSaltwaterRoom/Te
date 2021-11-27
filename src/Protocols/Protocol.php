<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/19 0019
 * Time: 下午 10:02
 */
namespace Te\Protocols;

interface Protocol
{
    public function Len($data);

    public function encode($data='');
    public function decode($data='');
    public function msgLen($data='');
}