<?php

use Te\Client;

require_once "vendor/autoload.php";

$clientNum = $argv[1];

$clientData = [];

$startTime = time();


for ($i = 0; $i < $clientNum; $i++) {
    $clientData[] = $client = new Client('tcp://127.0.0.1:12345');

    $client->on('connect', function (Client $client) {
        fprintf(STDOUT, "socket <%d> connect success!\r\n", (int)$client->getSocketFd());
//        $client->write2socket("hellox");
    });

    $client->on('receive', function (Client $client, $msg) {
//        fprintf(STDOUT, "rev from server: %s \r\n", $msg);
    });

    $client->on('error', function (Client $client, $errno, $errStr) {
        fprintf(STDOUT, "errno=%d,errStr=%s", $errno, $errStr);
    });

    $client->on('close', function (Client $client) {
        fprintf(STDOUT, "服务器断开我的连接了\n");
    });

    $client->start();
}

//$pid = pcntl_fork();
//
//if ($pid == 0) {
//    while (1) {
//        for ($i = 0; $i < $clientNum; $i++) {
//            /** @var Client $client */
//            $client = $clientData[$i];
//            if (is_resource($client->getSocketFd())) {
//                $client->send('hello,i am client');
//            }
//        }
//    }
//}


while (1) {
    $now       = time();
    $diff      = $now - $startTime;
    $startTime = $now;

    if ($diff >= 1) {
        $sendNum    = 0;
        $sendMsgNum = 0;

        foreach ($clientData as $client) {
            $sendNum    += $client->_sendNum;
            $sendMsgNum += $client->_sendMsgNum;
        }

        fprintf(
            STDOUT,
            "time:<%s>--<clientNum:%d>--<sendNum:%d>--<msgNum:%d>\r\n",
            $diff,
            $clientNum,
            $sendNum,
            $sendMsgNum
        );

        foreach ($clientData as $client) {
            $client->_sendNum    = 0;
            $client->_sendMsgNum = 0;
        }
    }

    for ($i = 0; $i < $clientNum; $i++) {
        /** @var Client $client */
        $client = $clientData[$i];

        //一直发
        for ($j = 0; $j < 5; $j++) {
            $client->send("hello,i am client" . time());
        }

        //一直等读事件产生
        if (!$client->eventLoop()) {
            break;
        }
    }
}

