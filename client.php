<?php

use Te\Client;

require_once "vendor/autoload.php";

$client = new Client('tcp://127.0.0.1:12345');

$client->on('connect', function (Client $client) {
    fprintf(STDOUT, "客户端已连接服务端了\r\n");
    $client->write2socket("hellox");
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
