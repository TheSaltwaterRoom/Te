<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/24 0024
 * Time: 下午 1:09
 */
//中断信号事件
// event 扩展它的实现是使用了libevent2.x 库的源码实现的
// libevent 它封装了三种事件，I/O事件，定时事件，中断信号事件【统称为事件源】
// 中断信号【多进程编程】信号编号，信号名字
// I/O 事件 其实就是指文件描述符上的事件【读写事件】，内核监听到事件发生之后，会通过文件描述符来通知应用程序

// 事件源【中断信号，定时，I/O】 也称为句柄，I/O就是指文件描述符，中断信号就是指信号，定时就是时间
// 事件多路分发器 它的实现是使用I/O复用函数来实现的，select epoll,poll,kqueue 它能监听大量的文件描述符上的事件
// 事件处理器/具体的事件处理器  其实就是事件回调函数
// 后面我们会给大家分析libevent框架库的工作原理【选看，因为需要c语言基础】
// 事件处理模式：reactor proactor [同步模式，异常模式】同步模式指的是监听的文件描述符上有就绪事件发生就返回
// 异步模式给的是完成事件【读写完成的事件】libevent 它是同步模式

$eventBase = new \EventBase();
//信号事件
$event = new \Event($eventBase,2,\Event::SIGNAL,function($fd,$what,$arg){

    echo "中断信号处理函数执行了";
    print_r($fd);
    print_r($what);
    print_r($arg);
},['a'=>'b']);

$event->add();

$events[] = $event;

$eventBase->dispatch();//内部会执行循环