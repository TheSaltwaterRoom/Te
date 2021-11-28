<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/18 0018
 * Time: 下午 10:49
 */
namespace Te;

class TcpConnection
{
    public $_sockfd;
    public $_clientIp;//ip:port
    public $_server;
    public $_readBufferSize = 1024;

    public $_recvBufferSize = 1024*1000*10;//100kb  表示当前的连接接收缓冲区的大小
    public $_recvLen = 0;              //表示当前连接目前接收到的字节数大小
    public $_recvBufferFull = 0;       //表示当前连接接收的字节数是否超出缓冲区
    public $_recvBuffer='';

    public $_sendLen=0;
    public $_sendBuffer='';
    public $_sendBufferSize = 1024*1000;
    public $_sendBufferFull = 0;
    public $_heartTime=0;

    const HEART_TIME = 10;

    const STATUS_CLOSED = 10;
    const STATUS_CONNECTED = 11;

    public $_status;

    public function isConnected()
    {
        return $this->_status == self::STATUS_CONNECTED&&is_resource($this->_sockfd);
    }


    public function resetHeartTime()
    {
        $this->_heartTime = time();
    }

    public function checkHeartTime()
    {
        $now = time();
        if ($now-$this->_heartTime>=self::HEART_TIME){
            fprintf(STDOUT,"心跳时间已经超出:%d\n",$now-$this->_heartTime);
            return true;
        }
        return false;
    }
    //粘包
    public function __construct($sockfd,$clientIp,$server)
    {

        $this->_sockfd = $sockfd;
        stream_set_blocking($this->_sockfd,0);
        stream_set_write_buffer($this->_sockfd,0);
        stream_set_blocking($this->_sockfd,0);
        $this->_clientIp = $clientIp;
        $this->_server = $server;
        $this->_heartTime = time();
        $this->_status = self::STATUS_CONNECTED;


        Server::$_eventLoop->add($this->_sockfd,Event\Event::EV_READ,[$this,"recv4socket"]);


    }

    public function socketfd()
    {
        return $this->_sockfd;
    }

    public function recv4socket()
    {
        if ($this->_recvLen<$this->_recvBufferSize){
            $data = fread($this->_sockfd,$this->_readBufferSize);
            if ($data===''||$data===false){
                if (feof($this->_sockfd)||!is_resource($this->_sockfd)){
                    $this->Close();
                }
            }else{
                //把接收到的数据放在接收缓冲区里
                $this->_recvBuffer.=$data;
                $this->_recvLen+=strlen($data);
                $this->_server->onRecv();
            }
        }else{
            $this->_recvBufferFull++;
            $this->_server->runEventCallBack("receiveBufferFull",[$this]);
        }
        if ($this->_recvLen>0){
            //Stream 字节流协议
            $this->handleMessage();
        }
    }

    public function handleMessage()
    {
        $server = $this->_server;

        if (is_object($server->_protocol)&&$server->_protocol!=null){
            while ($server->_protocol->Len($this->_recvBuffer)){
                $msgLen = $server->_protocol->msgLen($this->_recvBuffer);
                //截取一条消息
                $oneMsg = substr($this->_recvBuffer,0,$msgLen);
                $this->_recvBuffer = substr($this->_recvBuffer,$msgLen);
                $this->_recvLen-=$msgLen;
                $this->_recvBufferFull--;
                $this->_server->onMsg();
                $this->resetHeartTime();
                $message = $server->_protocol->decode($oneMsg);
                $server->runEventCallBack("receive",[$message,$this]);

            }
        }else{
            $server->runEventCallBack("receive",[$this->_recvBuffer,$this]);
            $this->_recvBuffer = '';
            $this->_recvLen=0;
            $this->_recvBufferFull=0;
            $this->_server->onMsg();
            $this->resetHeartTime();
        }

    }
    public function Close()
    {
        if (is_resource($this->_sockfd)){
            fclose($this->_sockfd);
        }

        /** @var Server $server */
        $server = $this->_server;
        $server->runEventCallBack("close",[$server,$this]);
        $server->removeClient($this->_sockfd);
        $this->_status = self::STATUS_CLOSED;
        $this->_sockfd=null;
    }

    public function send($data)
    {
        $len = strlen($data);

        $server = $this->_server;
        if ($this->_sendLen+$len<$this->_sendBufferSize){
            if (is_object($server->_protocol)&&$server->_protocol!=null){
                $bin = $this->_server->_protocol->encode($data);
                $this->_sendBuffer.=$bin[1];
                $this->_sendLen+=$bin[0];
            }else{
                $this->_sendBuffer.=$data;
                $this->_sendLen+=$len;
            }

            if ($this->_sendLen>=$this->_sendBufferSize){

                $this->_sendBufferFull++;
            }
        }

        //fwrite 在发送数据的时候【会存在以下几种情况，1只发送一半,2 能完整的发送  3对端关了】
        $writeLen = fwrite($this->_sockfd,$this->_sendBuffer,$this->_sendLen);
        if ($writeLen==$this->_sendLen){
            $this->_sendBuffer = '';
            $this->_sendLen=0;
            $this->_sendBufferFull=0;
            return true;
        }
        else if ($writeLen>0){

            $this->_sendBuffer = substr($this->_sendBuffer,$writeLen);
            $this->_sendLen-=$writeLen;
            $this->_sendBufferFull--;
            Server::$_eventLoop->add($this->_sockfd,Event\Event::EV_WRITE,[$this,"write2socket"]);
        }else{
            $this->Close();
        }
    }
    public function needWrite()
    {//fork
        return $this->_sendLen>0;
    }



    public function write2socket()
    {

        if ($this->needWrite()){

            $writeLen = fwrite($this->_sockfd,$this->_sendBuffer,$this->_sendLen);

            if ($writeLen==$this->_sendLen){
                $this->_sendBuffer = '';
                $this->_sendLen = 0;
                Server::$_eventLoop->del($this->_sockfd,Event\Event::EV_WRITE);
            }
            else if ($writeLen>0){

                $this->_sendBuffer = substr($this->_sendBuffer,$writeLen);
                $this->_sendLen-=$writeLen;
            }else{
                $this->Close();
            }
        }


    }
}