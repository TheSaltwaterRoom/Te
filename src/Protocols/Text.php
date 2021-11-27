<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/23 0023
 * Time: 下午 1:53
 */
namespace Te\Protocols;
//data1\ndata2\ndata3\n
//$a = substr($data,strpos($data,"\n"))
class Text implements Protocol
{
    //用来检测一条消息是否完整
    public function Len($data)
    {
        if (strlen($data)){

            return strpos($data,"\n");
        }
        return false;
    }

    public function encode($data='')
    {
        $data = $data."\n";
        return [strlen($data),$data];
    }

    public function decode($data='')
    {

        return rtrim($data,"\n");
    }

    //返回一条消息的总长度
    public function msgLen($data='')
    {
        return strpos($data,"\n")+1;
    }
}