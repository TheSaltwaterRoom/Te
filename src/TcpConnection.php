<?php

namespace Te;

use Te\Protocols\Protocol;

class TcpConnection
{
    public $_socketFd;
    public $_clientIp;
    public $_server;
    public $_readBufferSize = 1024;

    public $_recvBufferSize = 1024 * 100;         //100kb 表示当前连接接收缓冲区的大小
    public $_recvLen        = 0;                  //表示当前连接目前接收到的字节数大小
    public $_recvBuffer     = '';                 //它是一个接收缓冲区，可以接收多条消息【数据包】，数据像水一样粘在一起
    public $_recvBufferBull = 0;                  //表示当前连接接收的字节数是否超出缓冲区

    public $_sendLen        = 0;
    public $_sendBuffer     = '';
    public $_sendBufferSize = 1024 * 1000;
    public $_sendBufferFull = 0;

    public function __construct($socketFd, $clientIp, Server $_server)
    {
        $this->_socketFd = $socketFd;
        $this->_clientIp = $clientIp;
        $this->_server   = $_server;
    }

    public function getSocketFd()
    {
        return $this->_socketFd;
    }

    public function recv4Socket()
    {
        if ($this->_recvLen < $this->_recvBufferSize) {
            $data = fread($this->_socketFd, $this->_readBufferSize);

            if ($data === '' || $data === false) {
                if (feof($this->_socketFd) || !is_resource($this->_socketFd)) {
                    $this->onClose();
                }
            } else {
                $this->_recvLen    += strlen($data);
                $this->_recvBuffer .= $data;
                $this->_server->onRecv();
            }
        } else {
            $this->_recvBufferBull++;
            $this->_server->runEventCallBack('recvBufferBull');
        }

        if ($this->_recvLen > 0) {
            $this->handleMessage();
        }
    }

    public function handleMessage(): void
    {
        $server = $this->_server;
        if (is_object($server->_protocol) && !is_null($server->_protocol)) {
            while ($server->_protocol->len($this->_recvBuffer)) {
                $msgLen            = $server->_protocol->msgLen($this->_recvBuffer);
                $oneMsg            = substr($this->_recvBuffer, 0, $msgLen);
                $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);
                $this->_recvLen    -= $msgLen;
                $server->onMsg();
                $msg = $server->_protocol->decode($oneMsg);
                $server->runEventCallBack('receive', [$msg, $this]);
            }
        } else {
            $server->runEventCallBack('receive', [$this->_recvBuffer, $this]);
            $this->_recvBuffer = '';
            $this->_recvLen    = 0;
        }
    }

    public function send($data)
    {
        $len = strlen($data);

        $server = $this->_server;

        if ($this->_sendLen + $len < $this->_sendBufferSize) {
            if (is_object($server->_protocol) && !is_null($server->_protocol)) {
                $bin = $this->_server->_protocol->encode($data);

                $this->_sendLen    += $bin[0];
                $this->_sendBuffer .= $bin[1];
            } else {
                $this->_sendLen    += $len;
                $this->_sendBuffer .= $data;
            }

            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
        }
    }

    public function onClose()
    {
        if (is_resource($this->_socketFd)) {
            fclose($this->_socketFd);
        }
        $server = $this->_server;
        $server->runEventCallBack('close', [$this]);
        $server->onClientLeave($this->_socketFd);
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function write2socket()
    {
        if ($this->needWrite()) {
            $writeLen = fwrite($this->_socketFd, $this->_sendBuffer, $this->_sendLen);

            if ($this->_sendLen == $writeLen) {
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
}