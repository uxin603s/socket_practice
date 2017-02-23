<?php
include_once __DIR__."/WS.php";

$client_socket=WS::client("127.0.0.1","1234");
$recevice="廣播";
socket_write($client_socket, $recevice, strlen($recevice));