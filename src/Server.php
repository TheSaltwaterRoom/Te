<?php

namespace Te;

use Closure;
use Te\Protocols\Stream;

class Server
{
    public        $_mainSocket;
    public        $_local_socket;
    public static $_connections = [];

    public $_events = [];

    public $_protocol;

    public function __construct($local_socket)
    {
        $this->_local_socket = $local_socket;
        $this->_protocol    = new Stream();
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
        $option['socket']['backlog'] = 10;
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
        while (1) {
            $readFds[] = $this->_mainSocket;
            $writeFds  = [];
            $expFds    = [];

            if (!empty(self::$_connections)) {
                /**
                 * @var               $idx
                 * @var TcpConnection $tcpConnection
                 */
                foreach (static::$_connections as $idx => $tcpConnection) {
                    $sockfd     = $tcpConnection->getSocketFd();
                    $readFds[]  = $sockfd;
                    $writeFds[] = $sockfd;
                }
            }
            set_error_handler(function () {
            });
            //null 会阻塞
            $ret = stream_select($readFds, $writeFds, $expFds, null, null);
            restore_error_handler();
            if ($ret === false) {
                break;
            }

            if ($readFds) {
                foreach ($readFds as $fd) {
                    if ($fd == $this->_mainSocket) {
                        $this->accept();
                    } else {
                        /** @var TcpConnection $tcpConnection */
                        $tcpConnection = static::$_connections[(int)$fd];
                        $tcpConnection->recv4Socket();
                    }
                }
            }
        }
    }

    public function onClientLeave($socketFd)
    {
        if (isset(static::$_connections[(int)$socketFd])) {
            unset(static::$_connections[(int)$socketFd]);
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

            $this->runEventCallBack('connect', [$tcpConnection]);
        }
    }


}