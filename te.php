<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/17 0017
 * Time: 下午 8:47
 */
require_once "vendor/autoload.php";
//tcp connect/receive/close
//udp packet / close
//stream/text

//http request
//ws open/message/close
//mqtt connect/subscribe/unsubscribe/publish/close

ini_set("memory_limit","2048M");


$server = new \Te\Server("stream://127.0.0.1:12345");

$server->on("connect",function (\Te\Server $server,\Te\TcpConnection $connection){

    fprintf(STDOUT,"有客户端连接了\r\n");
});
//fread
$server->on("receive",function (\Te\Server $server,$msg,\Te\TcpConnection $connection){

    //fprintf(STDOUT,"recv from client<%d>:%s\r\n",(int)$connection->socketfd(),$msg);
    $connection->send("i am server".time());
});
$server->on("receiveBufferFull",function (\Te\Server $server,\Te\TcpConnection $connection){

    fprintf(STDOUT,"接收缓冲区已经满了\r\n");
    //$connection->send("i am server");
});
$server->on("close",function (\Te\Server $server,$msg,\Te\TcpConnection $connection){
    fprintf(STDOUT,"客户端断开连接了\r\n");
});

$server->Start();



