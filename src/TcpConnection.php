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
                    $this->close();
                }
            } else {
                $this->_recvLen    += strlen($data);
                $this->_recvBuffer .= $data;
            }
        } else {
            $this->_recvBufferBull++;
        }

        if ($this->_recvLen > 0) {
            $this->handleMessage();
        }
    }

    public function send($data)
    {
        $len = strlen($data);

        if ($this->_sendLen + $len < $this->_sendBufferSize) {
            $bin = $this->_server->_protocol->encode($data);

            $this->_sendLen    += $bin[0];
            $this->_sendBuffer .= $bin[1];

            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
        }

        $writeLen = fwrite($this->_socketFd, $this->_sendBuffer, $this->_sendLen);

        if ($this->_sendLen == $writeLen) {
            return;
        } elseif ($writeLen > 0) {
            $this->_sendLen    -= $writeLen;
            $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
        } else {
            $this->close();
        }
    }

    public function close()
    {
        if (is_resource($this->_socketFd)) {
            fclose($this->_socketFd);
        }
        /** @var Server $server */
        $server = $this->_server;
        $server->runEventCallBack('close', [$this]);
        $server->onClientLeave($this->_socketFd);
    }

    public function write2socket($data)
    {
        /** @var Server $server */
        $server = $this->_server;

        $bin      = $server->_protocol->encode($data);
        $writeLen = fwrite($this->_socketFd, $bin[1], $bin[0]);
        fprintf(STDOUT, "tcpConnection 我写了 %d 字节", $writeLen);
    }

    /**
     */
    public function handleMessage(): void
    {
        /** @var Server $server */
        $server = $this->_server;
        while ($server->_protocol->len($this->_recvBuffer)) {
            $msgLen            = $server->_protocol->msgLen($this->_recvBuffer);
            $oneMsg            = substr($this->_recvBuffer, 0, $msgLen);
            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);

            $msg = $server->_protocol->decode($oneMsg);
            $server->runEventCallBack('receive', [$msg, $this]);
        }
    }
}