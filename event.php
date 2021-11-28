<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/23 0023
 * Time: 下午 10:04
 */
//定时时间
$eventBase = new \EventBase();
//定时器事件
$event = new \Event($eventBase,-1,\Event::TIMEOUT|\Event::PERSIST,function($fd,$what,$arg){

    echo "时间到了";
    print_r($fd);
    print_r($what);
    print_r($arg);
},['a'=>'b']);

$event->add(1);

$events[] = $event;

$eventBase->dispatch();



