<?php

use Te\Client;

require_once "vendor/autoload.php";

$clientNum = $argv[1];

$clientData = [];

for ($i = 0; $i < $clientNum; $i++) {
    $clientData[] = $client = new Client('tcp://127.0.0.1:12345');

    $client->on('connect', function (Client $client) {
        fprintf(STDOUT, "socket <%d> connect success!\r\n", $client->getSocketFd());
//        $client->write2socket("hellox");
    });

    $client->on('receive', function (Client $client, $msg) {
        fprintf(STDOUT, "rev from server: %s \r\n", $msg);
        $client->write2socket("i am client");
    });

    $client->on('error', function (Client $client, $errno, $errStr) {
        fprintf(STDOUT, "errno=%d,errStr=%s", $errno, $errStr);
    });

    $client->on('close', function (Client $client) {
        fprintf(STDOUT, "服务器断开我的连接了\n");
    });

    $client->start();
}

$pid = pcntl_fork();

if ($pid == 0) {
    while (1) {
        for ($i = 0; $i < $clientNum; $i++) {
            /** @var Client $client */
            $client = $clientData[$i];
            if (is_resource($client->getSocketFd())) {
                $client->write2socket('hello,i am client');
            }
        }
    }
}


while (1) {
    for ($i = 0; $i < $clientNum; $i++) {
        /** @var Client $client */
        $client = $clientData[$i];
        if (!$client->eventLoop()) {
            break;
        }
    }
}

