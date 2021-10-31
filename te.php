<?php

use Te\Server;
use Te\TcpConnection;

require_once "vendor/autoload.php";

$server = new Server('tcp://127.0.0.1:12345');

$server->on('connect', function (Server $server, TcpConnection $tcpConnection) {
    $connfd = $tcpConnection->getSocketFd();
    fprintf(STDOUT, "有客户端连接了{$connfd}\r\n");
});

$server->on('receive', function (Server $server, $msg, TcpConnection $tcpConnection) {
    fprintf(STDOUT, "rev from client: %s \r\n", $msg);
});

$server->listen();

$server->eventLoop();



