<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/25 0025
 * Time: 下午 6:56
 */
namespace Te\Event;

class Epoll implements Event
{
    public $_eventBase;
    public $_allEvents = [];
    public $_signalEvents = [];
    public $_timers = [];


    public function __construct()
    {
        $this->_eventBase = new \EventBase();
    }

    public function add($fd, $flag, $func, $arg=[])
    {

        // TODO: Implement add() method.
        switch ($flag){

            case self::EV_READ:
                //fd 必须设置为非阻塞方式，因为epoll内部是使用非阻塞的文件描述符把它添加内核事件表
                $event = new \Event($this->_eventBase,$fd,\Event::READ|\Event::PERSIST,$func,$arg);
                if (!$event||!$event->add()){
                    echo "read 事件添加失败\r\n";
                    print_r(error_get_last());
                    return false;
                }
                $this->_allEvents[(int)$fd][self::EV_READ] = $event;
                echo "read 事件添加成功\r\n";
                return true;

                break;
            case self::EV_WRITE:
                echo "write here";

                $event = new \Event($this->_eventBase,$fd,\Event::EV_WRITE|\Event::PERSIST,$func,$arg);
                echo "write here1";

                if (!$event||!$event->add()){
                    echo "write 事件添加失败\r\n";
                    return false;
                }
                echo "write 事件添加成功\r\n";
                $this->_allEvents[(int)$fd][self::EV_WRITE] = $event;
                return true;

                break;
            case self::EV_SIGNAL:
                $event = new \Event($this->_eventBase,$fd,\Event::SIGNAL,$func,$arg);
                if (!$event||$event->add()){
                    return false;
                }
                $this->_signalEvents[(int)$fd] = $event;
                return true;
                break;
        }
    }

    public function del($fd, $flag)
    {
        //[1][read] = event
        //[1][write] = event
        //
        switch ($flag){

            case self::EV_READ:
                if (isset($this->_allEvents[(int)$fd][self::EV_READ])){
                    $event = $this->_allEvents[(int)$fd][self::EV_READ];
                    $event->del();
                    unset($this->_allEvents[(int)$fd][self::EV_READ]);
                }
                if (empty($this->_allEvents[(int)$fd])){

                    unset($this->_allEvents[(int)$fd]);
                }
                return true;
                break;
            case self::EV_WRITE:
                if (isset($this->_allEvents[(int)$fd][self::EV_WRITE])){
                    $event = $this->_allEvents[(int)$fd][self::EV_WRITE];
                    $event->del();
                    unset($this->_allEvents[(int)$fd][self::EV_WRITE]);
                }
                if (empty($this->_allEvents[(int)$fd])){

                    unset($this->_allEvents[(int)$fd]);
                }
                return true;
                break;
            case self::EV_SIGNAL:

                break;
        }
    }

    public function loop()
    {
        // TODO: Implement loop() method.
        echo "执行事件循环了\r\n";
        return $this->_eventBase->loop();//while epoll_wait
    }

    public function clearSignalEvents()
    {
        // TODO: Implement clearSignalEvents() method.
    }

    public function clearTimer()
    {
        // TODO: Implement clearTimer() method.
    }
}