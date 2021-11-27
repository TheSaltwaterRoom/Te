<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2020/11/19 0019
 * Time: 下午 9:42
 */

require_once "vendor/autoload.php";

$clientNum = $argv['1'];//命令行参数

$clients = [];
$startTime = time();

ini_set("memory_limit","2048M");

for ($i=0;$i<$clientNum;$i++){

    $clients[] = $client =  new \Te\Client("tcp://127.0.0.1:12345");
    $client->on("connect",function (\Te\Client $client){

        //$client->write2socket("hellox");
        fprintf(STDOUT,"socket<%d> connect success!\r\n",(int)$client->socketfd());

    });
//这里服务器返回数据的时候，我才继续往服务器发送数据
    $client->on("receive",function (\Te\Client $client,$msg){

        //fprintf(STDOUT,"recv from server:%s\n",$msg);
        //$client->write2socket("i am client 客户端");
    });


    $client->on("close",function (\Te\Client $client){

        fprintf(STDOUT,"服务器断开我的连接了\n");
    });
    $client->on("receiveBufferFull",function (\Te\Client $client){

        fprintf(STDOUT,"发送缓冲区已经满了\r\n");
        //$connection->send("i am server");
    });
    $client->on("error",function (\Te\Client $client,$errno,$errstr){

        fprintf(STDOUT,"errno:%d,errstr:%s\n",$errno,$errstr);
    });

    $client->Start();
}

//$pid = pcntl_fork();
////16945
//if ($pid==0){
//
//    while (1){
//        for ($i=0;$i<$clientNum;$i++){
//            $client = $clients[$i];
//            $client->send("hello,i am client");
//
//        }
//
//        sleep(1);
//
//    }
//    exit(0);
//}
//16944

while (1){

    $now = time();
    $diff = $now-$startTime;
    $startTime = $now;

    if ($diff>=1){
        $sendNum=0;
        $sendMsgNum=0;

        foreach ($clients as $client){

            $sendNum+=$client->_sendNum;
            $sendMsgNum+=$client->_sendMsgNum;
        }

        fprintf(STDOUT,"time:<%s>--<clientNum:%d>--<sendNum:%d>--<msgNum:%d>\r\n",
            $diff,$clientNum,$sendNum,$sendMsgNum);

        foreach ($clients as $client){

            $client->_sendNum = 0;
            $client->_sendMsgNum = 0;
        }


    }
    for ($i=0;$i<$clientNum;$i++) {
        $client = $clients[$i];


        //一直发
        for ($j=0;$j<1;$j++){
            $client->send("hello,i am client".time());
        }


        //一直等读事件产生
        if (!$client->eventLoop()) {
            break;
        }

        sleep(5);

    }
}
