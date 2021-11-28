<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/19 0019
 * Time: 下午 9:38
 */
namespace Te;

use Te\Protocols\Stream;

class Client
{
    public $_mainSocket;
    public $_events = [];
    public $_readBufferSize = 102400;
    public $_recvBufferSize = 1024*100;//100kb  表示当前的连接接收缓冲区的大小
    public $_recvLen = 0;              //表示当前连接目前接收到的字节数大小

    public $_sendLen=0;
    public $_sendBuffer='';
    public $_sendBufferSize = 1024*100;
    public $_sendBufferFull = 0;

    //它是一个接收缓冲区，可以接收多条消息【数据包】，数据像水一样粘在一起
    public $_recvBuffer='';

    public $_protocol;
    public $_local_socket;

     public $_sendNum = 0;
 public $_sendMsgNum=0;
    const STATUS_CLOSED = 10;
    const STATUS_CONNECTED = 11;

    public $_status;

    public function onSendWrite()
    {
        ++$this->_sendNum;
    }
    public function onSendMsg()
    {
        ++$this->_sendMsgNum;
    }
    public function on($eventName,$eventCall){
        $this->_events[$eventName] = $eventCall;
    }

    public function socketfd()
    {
        return $this->_mainSocket;

    }
    public function runEventCallBack($eventName,$args=[])
    {
        if (isset($this->_events[$eventName])&&is_callable($this->_events[$eventName])){
            $this->_events[$eventName]($this,...$args);//
        }else{
            fprintf(STDOUT,"not found %s event call\n",$eventName);
        }
    }


    public function __construct($local_socket)
    {
        $this->_local_socket = $local_socket;
        //connect
        $this->_protocol = new Stream();
    }

    public function onClose()
    {
        fclose($this->_mainSocket);
        $this->runEventCallBack("close",[$this]);
        $this->_status = self::STATUS_CLOSED;
        $this->_mainSocket = null;
    }

    public function isConnected()
    {
        return $this->_status == self::STATUS_CONNECTED&&is_resource($this->_mainSocket);
    }

    public function write2socket()
    {
        //fprintf(STDOUT,"接收进程的sendLen:%d,sendBufferLen:%d\r\n",$this->_sendLen,strlen($this->_sendBuffer));

        if ($this->needWrite()&&$this->isConnected()){
            //fprintf(STDOUT,"write2socket\r\n");

            $writeLen = fwrite($this->_mainSocket,$this->_sendBuffer,$this->_sendLen);
            //print_r($writeLen);
            $this->onSendWrite();
            if ($writeLen==$this->_sendLen){
                $this->_sendBuffer = '';
                $this->_sendLen = 0;
                return true;
            }
            else if ($writeLen>0){

                $this->_sendBuffer = substr($this->_sendBuffer,$writeLen);
                $this->_sendLen-=$writeLen;
            }else{
                $this->onClose();
            }
        }

        //fprintf(STDOUT,"我写了:%d字节\n",$writeLen);

    }

    public function send($data)
    {
        $len = strlen($data);

        if ($this->_sendLen+$len<$this->_sendBufferSize){

            $bin = $this->_protocol->encode($data);
            $this->_sendBuffer.=$bin[1];
            $this->_sendLen+=$bin[0];
            //cow 这个概念  多进程编程
            //fprintf(STDOUT,"send\r\n");
            if ($this->_sendLen>=$this->_sendBufferSize){

                $this->_sendBufferFull++;
            }
            $this->onSendMsg();
        }else{
            $this->runEventCallBack("receiveBufferFull",[$this]);
        }
    }

    public function needWrite()
    {//fork
        return $this->_sendLen>0;
    }

    public function recv4socket()
    {
        if ($this->isConnected()){
            $data = fread($this->_mainSocket,$this->_readBufferSize);
            if ($data===''||$data===false){
                if (feof($this->_mainSocket)||!is_resource($this->_mainSocket)){
                    $this->onClose();
                }
            }else{
                $this->_recvBuffer.=$data;
                $this->_recvLen+=strlen($data);
            }
            if ($this->_recvLen>0){
                $this->handleMessage();
            }
        }

    }

    public function handleMessage()
    {
        while ($this->_protocol->Len($this->_recvBuffer)){

            $msgLen = $this->_protocol->msgLen($this->_recvBuffer);
            //截取一条消息
            $oneMsg = substr($this->_recvBuffer,0,$msgLen);
            $this->_recvBuffer = substr($this->_recvBuffer,$msgLen);
            $this->_recvLen-=$msgLen;

            $message = $this->_protocol->decode($oneMsg);
            $this->runEventCallBack("receive",[$message]);
        }
    }
    public function Start()
    {
        $this->_mainSocket = stream_socket_client($this->_local_socket,$errno,$errstr);

        if (is_resource($this->_mainSocket)){

            $this->runEventCallBack("connect",[$this]);
            //$this->eventLoop();
            $this->_status = self::STATUS_CONNECTED;

        }else{

            $this->runEventCallBack("error",[$this,$errno,$errstr]);
            exit(0);
        }

    }
    public function eventLoop()
    {

        //while (1){
        if (is_resource($this->_mainSocket)) {
            $readFds = [$this->_mainSocket];
            //if ($this->needWrite()){
                $writeFds = [$this->_mainSocket];
            //}else{
            //    $writeFds = [];
            //}

            $exptFds = [$this->_mainSocket];

            $ret = stream_select($readFds, $writeFds, $exptFds, NULL, NULL);

            if ($ret <= 0 || $ret === FALSE) {

                return false;
            }

            if ($readFds) {

                $this->recv4socket();
            }
            if ($writeFds){

                $this->write2socket();
            }

            return true;
        }else{
            return false;
        }
    }
}