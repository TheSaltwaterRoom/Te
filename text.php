<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/23 0023
 * Time: 下午 1:55
 */
//47.108.21.107
$data = "hello\nworld\nphp\n";//每条消息以\n来结束，表示一条完整的消息

$a = substr($data,0,strpos($data,"\n")+1);

echo $a;
$data = substr($data,strpos($data,"\n")+1);

$b = substr($data,0,strpos($data,"\n")+1);
echo "\r\n";
echo $b;