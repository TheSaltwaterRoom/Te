<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/17 0017
 * Time: 下午 8:46
 */
namespace Te;

use Te\Protocols\Stream;

class Server
{

    public $_mainSocket;
    public $_local_socket;
    static public $_connections = [];

    public $_events = [];

    public $_protocol = null;
    public $_protocol_layout;

static public $_clientNum=0;//统计客户端连接数量
static public $_recvNum=0;//执行recv/fread调用次数
static public $_msgNum=0;//接收了多少条消息

    public $_startTime=0;

    public $_protocols = [
        "stream"=>"Te\Protocols\Stream",
        "text"=>"Te\Protocols\Text",
        "ws"=>"",
        "http"=>"",
        "mqtt"=>""
    ];
    public function on($eventName,$eventCall){
        $this->_events[$eventName] = $eventCall;
    }

    public function __construct($local_socket)
    {
        list($protocol,$ip,$port) = explode(":",$local_socket);

        if (isset($this->_protocols[$protocol])){

            $this->_protocol = new $this->_protocols[$protocol]();
        }
        $this->_startTime = time();

        $this->_local_socket = "tcp:".$ip.":".$port;

    }

    public function onClientJoin()
    {
        ++static::$_clientNum;
    }


    public function removeClient($sockfd)
    {
        if (isset(static::$_connections[(int)$sockfd])){
            unset(static::$_connections[(int)$sockfd]);
            --static::$_clientNum;
        }
    }

    public function onRecv()
    {
        ++static::$_recvNum;
    }
    public function onMsg()
    {
        ++static::$_msgNum;

    }
    public function statistics()
    {

        $nowTime = time();
        $diffTime = $nowTime-$this->_startTime;
        $this->_startTime = $nowTime;
        if ($diffTime>=1){

            fprintf(STDOUT,"time:<%s>--socket<%d>--<clientNum:%d>--<recvNum:%d>--<msgNum:%d>\r\n",
                $diffTime,(int)$this->_mainSocket,static::$_clientNum,static::$_recvNum,static::$_msgNum);

            static::$_recvNum=0;
            static::$_msgNum=0;
        }
    }
    public function Listen()
    {
        $flag = STREAM_SERVER_LISTEN|STREAM_SERVER_BIND;//tcp
        $option['socket']['backlog'] = 102400;//epoll select[1024]
        $context= stream_context_create($option);

        $this->_mainSocket = stream_socket_server($this->_local_socket,$errno,$errstr,$flag,$context);
        stream_set_blocking($this->_mainSocket,0);
        if (!is_resource($this->_mainSocket)){

            fprintf(STDOUT,"server create fail:%s\n",$errstr);
            exit(0);
        }
        fprintf(STDOUT,"listen on:%s\n",$this->_local_socket);

    }

    public function Start()
    {
        $this->Listen();
        $this->eventLoop();
    }

    public function checkHeartTime()
    {

        foreach (static::$_connections as $idx=>$connection){

            if ($connection->checkHeartTime()){
                $connection->Close();
            }

        }
    }
    public function eventLoop()
    {
        $readFds[] = $this->_mainSocket;

        while (1){

            $reads = $readFds;
            $writes = [];
            $expts = [];


            $this->statistics();

            //$this->checkHeartTime();

            if (!empty(static::$_connections)){

                //也会导致重复
                foreach (static::$_connections as $idx=>$connection){
                    $sockfd = $connection->socketfd();
                    if (is_resource($sockfd)){
                        $reads[] = $sockfd;
                       // $writes[] = $sockfd;
                    }

                }
            }

            //print_r($reads);
            //print_r($writes);
            set_error_handler(function (){});
            $ret = stream_select($reads,$writes,$expts,0,100);
            restore_error_handler();

            if ($ret===FALSE){
                break;
            }

            if ($reads){
                foreach ($reads as $fd) {
                    if ($fd==$this->_mainSocket){
                        $this->Accept();
                    }else{
                        /** @var TcpConnection $connection */
                        if (isset(static::$_connections[(int)$fd])){
                            $connection = static::$_connections[(int)$fd];
                            if ($connection->isConnected()) {
                                $connection->recv4socket();
                            }
                        }

                    }

                }

            }
            if ($writes){
                foreach ($writes as $fd) {

                    if (isset(static::$_connections[(int)$fd])){
                        /** @var TcpConnection $connection */
                        $connection = static::$_connections[(int)$fd];
                        if ($connection->isConnected()){
                            $connection->write2socket();
                        }

                    }

                }

            }
        }

    }

    public function runEventCallBack($eventName,$args=[])
    {
        if (isset($this->_events[$eventName])&&is_callable($this->_events[$eventName])){
            $this->_events[$eventName]($this,...$args);
        }
    }
    public function Accept()
    {

        //从内核监听队列去获取连接
        //连接socket 我们要关注的是读写事件【就是数据收发】
        $connfd = stream_socket_accept($this->_mainSocket,-1,$peername);
        if (is_resource($this->_mainSocket)){
            $connection = new TcpConnection($connfd,$peername,$this);
            $this->onClientJoin();
            static::$_connections[(int)$connfd] = $connection;
            $this->runEventCallBack("connect",[$connection]);
        }
    }
}