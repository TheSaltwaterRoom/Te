<?php

namespace Te;

class TcpConnection
{
    public $_socketFd;
    public $_clientIp;
    public $_server;
    public $_readBufferSize = 1024;

    public $_recvBufferSize = 1024 * 100;//100kb 表示当前连接接收缓冲区的大小
    public $_recvLen        = 0;         //表示当前连接目前接收到的字节数大小
    public $_recvBufferBull = 0;         //表示当前连接接收的字节数是否超出缓冲区

    public function __construct($socketFd, $clientIp, $_server)
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
        $data = fread($this->_socketFd, $this->_readBufferSize);

        if ($data === '' || $data === false) {
            if (feof($this->_socketFd) || !is_resource($this->_socketFd)) {
                $this->close();
            }
        }

        if ($data) {
            /** @var Server $server */
            $server = $this->_server;
            $server->runEventCallBack('receive', [$data, $this]);
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
        $len      = strlen($data);
        $writeLen = fwrite($this->_socketFd, $data, $len);
        fprintf(STDOUT, "我写了 %d 字节\n", $len);
    }
}