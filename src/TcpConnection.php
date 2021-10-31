<?php

namespace Te;

class TcpConnection
{
    public $_socketFd;
    public $_clientIp;
    public $_server;

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
        $data = fread($this->_socketFd, 1024);
        if ($data) {
            /** @var Server $server */
            $server = $this->_server;
            $server->runEventCallBack('receive', [$data, $this]);
        }
    }

    public function write2socket($data)
    {
        $len      = strlen($data);
        $writeLen = fwrite($this->_socketFd, $data, $len);
        fprintf(STDOUT, "我写了 %d 字节\n", $len);
    }
}