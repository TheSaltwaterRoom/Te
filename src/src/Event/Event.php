<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/25 0025
 * Time: 下午 6:39
 */
namespace Te\Event;

interface Event
{

    const EV_READ=10;
    const EV_WRITE=11;


    const EV_SIGNAL=12;

    const EV_TIMER = 13;
    const EV_TIMER_ONCE = 14;

    //监听socket 连接socket
    public function add($fd,$flag,$func,$arg);
    public function del($fd,$flag);

    public function loop();
    public function clearTimer();
    public function clearSignalEvents();
}