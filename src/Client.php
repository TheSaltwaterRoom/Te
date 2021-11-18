<?php

namespace Te;

use Closure;
use Te\Protocols\Stream;

class Client
{
    public $_localSocket;
    public $_mainSocket;

    public $_readBufferSize = 1024 * 100;         //读取100kb

    public $_recvLen        = 0;                  //表示当前连接目前接收到的字节数大小
    public $_recvBuffer     = '';                 //它是一个接收缓冲区，可以接收多条消息【数据包】，数据像水一样粘在一起
    public $_recvBufferSize = 1024 * 100;         //100kb  表示当前的连接接收缓冲区的大小 1KB=1024B；1MB=1024KB=1024 x 1024B。其中1024=2^10。

    public $_sendLen        = 0;
    public $_sendBuffer     = '';
    public $_sendBufferSize = 1024 * 100;
    public $_sendBufferFull = 0;

    public $_events = [];

    public $_protocol;

    public $_sendNum    = 0;
    public $_sendMsgNum = 0;

    public function __construct($local_socket)
    {
        $this->_localSocket = $local_socket;
        $this->_protocol    = new Stream();
    }

    public function onSendWrite()
    {
        ++$this->_sendNum;
    }

    public function onSendMsg()
    {
        ++$this->_sendMsgNum;
    }

    public function on(string $eventName, Closure $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function runEventCallBack($eventName, $args = [])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        } else {
            fprintf(STDOUT, "%s not found event call\n", $eventName);
        }
    }

    public function onClose()
    {
        fclose($this->_mainSocket);
        $this->runEventCallBack('close');
    }

    public function write2socket()
    {
        if ($this->needWrite()) {
            $writeLen = fwrite($this->_mainSocket, $this->_sendBuffer, $this->_sendLen);
            $this->onSendWrite();

            if ($writeLen == $this->_sendLen) {
                $this->_sendLen        = 0;
                $this->_sendBuffer     = '';
                $this->_sendBufferFull = 0;

                return true;
            } elseif ($writeLen > 0) {
                $this->_sendLen    -= $writeLen;
                $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
            } else {
                $this->onClose();
            }
        }
    }

    public function send($data)
    {
        $len = strlen($data);

        if ($this->_sendLen + $len < $this->_sendBufferSize) {
            $bin = $this->_protocol->encode($data);

            $this->_sendLen    += $bin[0];
            $this->_sendBuffer .= $bin[1];


            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
            $this->onSendMsg();
        }
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function recv4Socket()
    {
        $data = fread($this->_mainSocket, $this->_readBufferSize);

        if ($data === '' || $data === false) {
            if (feof($this->_mainSocket) || !is_resource($this->_mainSocket)) {
                $this->onClose();
            }
        } else {
            $this->_recvLen    += strlen($data);
            $this->_recvBuffer .= $data;
        }

        if ($this->_recvLen > 0) {
            $this->handleMessage();
        }
    }

    public function getSocketFd()
    {
        return $this->_mainSocket;
    }

    public function start()
    {
        $this->_mainSocket = stream_socket_client($this->_localSocket, $errno, $errStr);

        if (is_resource($this->_mainSocket)) {
            $this->runEventCallBack('connect');
        } else {
            $this->runEventCallBack('error', [$errno, $errStr]);
            exit(0);
        }
    }

    public function eventLoop()
    {
        if (is_resource($this->_mainSocket)) {
            $readFds = [$this->_mainSocket];
            $writeFds = [$this->_mainSocket];
            $exptFds = [$this->_mainSocket];


            //null 会阻塞
            $ret = stream_select($readFds, $writeFds, $exptFds, null, null);
            if ($ret <= 0 || $ret === false) {
                return false;
            }

            if ($readFds) {
                $this->recv4socket();
            }

            if ($writeFds) {
                $this->write2socket();
            }

            return true;
        } else {
            return false;
        }
    }

    public function handleMessage(): void
    {
        while ($this->_protocol->Len($this->_recvBuffer)) {
            $msgLen = $this->_protocol->msgLen($this->_recvBuffer);
            //截取一条消息
            $oneMsg            = substr($this->_recvBuffer, 0, $msgLen);
            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
            $this->_recvLen    -= $msgLen;

            $message = $this->_protocol->decode($oneMsg);
            $this->runEventCallBack("receive", [$message]);
        }
    }

}