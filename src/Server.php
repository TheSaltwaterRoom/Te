<?php

namespace Te;

use Closure;

class Server
{
    public        $_mainSocket;
    public        $_local_socket;
    public static $_connections = [];

    public $_events = [];

    public $_protocol = null;
    public $_protocol_layout;

    static public $_clientNum = 0;//统计客户端连接数量
    static public $_recvNum   = 0;//执行recv/fread调用次数
    static public $_msgNum    = 0;//接收了多少条消息

    public $_startTime = 0;

    public $_protocols = [
        'stream' => 'Te\Protocols\Stream',
        "text"   => "",
        "ws"     => "",
        "http"   => "",
        "mqtt"   => "",
    ];

    public function __construct($local_socket)
    {
        [$protocols, $ip, $port] = explode(':', $local_socket);
        if (isset($this->_protocols[$protocols])) {
            $this->_protocol = new $this->_protocols[$protocols]();
        }
        $this->_startTime = time();

        $this->_local_socket = 'tcp:' . $ip . ':' . $port;
    }

    public function onClientJoin()
    {
        ++static::$_clientNum;
    }

    public function onClientLeave($socketFd)
    {
        if (isset(static::$_connections[(int)$socketFd])) {
            unset(static::$_connections[(int)$socketFd]);
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
        $nowTime          = time();
        $diffTime         = $nowTime - $this->_startTime;
        $this->_startTime = $nowTime;
        if ($diffTime >= 1) {
            fprintf(
                STDOUT,
                "time:<%s>--socket<%d>--<clientNum:%d>--<recvNum:%d>--<msgNum:%d>\r\n",
                $diffTime,
                (int)$this->_mainSocket,
                static::$_clientNum,
                static::$_recvNum,
                static::$_msgNum
            );

            static::$_recvNum = 0;
            static::$_msgNum  = 0;
        }
    }

    public function on(string $eventName, Closure $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function start()
    {
        $this->listen();
        $this->eventLoop();
    }

    public function listen()
    {
        $flags                       = STREAM_SERVER_LISTEN | STREAM_SERVER_BIND;
        $option['socket']['backlog'] = 102400;//epoll有用，select 【1024】
        $context                     = stream_context_create($option);
        //监听队列
        $this->_mainSocket = stream_socket_server($this->_local_socket, $errno, $errStr, $flags, $context);

        if (!is_resource($this->_mainSocket)) {
            fprintf(STDOUT, "server create fail %s\n", $errStr);
            exit(0);
        }

        fprintf(STDOUT, "listen on:%s\r\n", $this->_local_socket);
    }


    public function eventLoop()
    {
        $readFds[] = $this->_mainSocket;


        while (1) {
            $reads    = $readFds;
            $writeFds = [];
            $expFds   = [];
            $this->statistics();

            if (!empty(self::$_connections)) {
                /**
                 * @var               $idx
                 * @var TcpConnection $tcpConnection
                 */
                foreach (static::$_connections as $idx => $tcpConnection) {
                    $sockfd = $tcpConnection->getSocketFd();
                    if (is_resource($sockfd)) {
                        $reads[]    = $sockfd;
                        $writeFds[] = $sockfd;
                    }
                }
            }
            set_error_handler(function () {
            });
            //null 会阻塞
            $ret = stream_select($reads, $writeFds, $expFds, null, null);
            restore_error_handler();
            if ($ret === false) {
                break;
            }

            if ($reads) {
                foreach ($reads as $fd) {
                    if ($fd == $this->_mainSocket) {
                        $this->accept();
                    } else {
                        if (isset(static::$_connections[(int)$fd])) {
                            /** @var TcpConnection $tcpConnection */
                            $tcpConnection = static::$_connections[(int)$fd];
                            $tcpConnection->recv4Socket();
                        }
                    }
                }
            }
        }
    }

    public function runEventCallBack($eventName, $args = [])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function accept()
    {
        $connfd = stream_socket_accept($this->_mainSocket, -1, $peername);

        if (is_resource($this->_mainSocket)) {
            $tcpConnection                      = new TcpConnection($connfd, $peername, $this);
            static::$_connections[(int)$connfd] = $tcpConnection;
            $this->onClientJoin();
            $this->runEventCallBack('connect', [$tcpConnection]);
        }
    }


}