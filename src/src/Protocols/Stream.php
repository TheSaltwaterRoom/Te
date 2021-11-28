<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/19 0019
 * Time: 下午 10:00
 */
namespace Te\Protocols;

class Stream implements Protocol
{
    //用来检测一条消息是否完整
    public function Len($data)
    {
      //pack/unpack
        if (strlen($data)<4){
            return false;
        }
        $tmp = unpack("NtotalLen",$data);
        //目前接收到数据包总长度还是小于指定的长度【消息不完整】
        if (strlen($data)<$tmp['totalLen']){
            return false;
        }
        return true;
    }

    public function encode($data='')
    {
        //封包|拆包【需要有一个字段来表示数据包的长度，一条消息的完整设计协议时必须能知道数据包的长度】
        //给大家演示打包好的这个二进制数据在内存长啥样？？？【php调试就很麻烦，php-fpm debug xdebug cli 】
        $totalLen = strlen($data)+6;
        //11|1|104 101 108 108 111 0
        $bin = pack("Nn",$totalLen,"1").$data;
        return [$totalLen,$bin];
    }

    public function decode($data='')
    {

        $cmd = substr($data,4,2);
        $msg = substr($data,6);
        return $msg;
    }

    //返回一条消息的总长度
    public function msgLen($data='')
    {
        $tmp = unpack("Nlength",$data);
        return $tmp['length'];
    }
}