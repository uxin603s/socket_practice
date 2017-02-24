<?php
Class WS {	
	public static $ip="0.0.0.0";
	public static $port="1234";
	public static $socket_list=[];
	public static $exit_pid=[];
	public static function getCookie($req){
		$result=[];
		if (preg_match("/Cookie: (.*)\r\n/", $req, $match)) { 
			$cookie_str = $match[1];
			$cookies=explode(";",$cookie_str);
			$result=[];
			foreach($cookies as $key=>$cookie){
				$tmp=explode("=",$cookie);
				$result[$tmp[0]]=$tmp[1];
			}
		}
		return $result;
	}
	public static function frame($s) {
		$a = str_split($s, 125);
		if (count($a) == 1) {
			return "\x81" . chr(strlen($a[0])) . $a[0];
		}
		$ns = "";
		foreach ($a as $o) {
			$ns .= "\x81" . chr(strlen($o)) . $o;
		}
		return $ns;
	}
	public static function dohandshake($req){
		if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $req, $match)) { 
			$key = $match[1]; 
			$mask = "258EAFA5-E914-47DA-95CA-C5AB0DC85B11";
			return base64_encode(sha1($key .$mask, true));
		}
		return false;
	}
	public static function decode($text)  {
		$len = $masks = $data = $decoded = null;
		$len = ord($text[1]) & 127;

		if ($len === 126)  {
			$masks = substr($text, 4, 4);
			$data = substr($text, 8);
		} else if ($len === 127)  {
			$masks = substr($text, 10, 4);
			$data = substr($text, 14);
		} else  {
			$masks = substr($text, 2, 4);
			$data = substr($text, 6);
		}
		for ($index = 0; $index < strlen($data); $index++) {
			$decoded .= $data[$index] ^ $masks[$index % 4];
		}
		return $decoded;
	}
	public static function send_all($send){
		$send=static::frame(json_encode($send));
		foreach(static::$socket_list as $val){
			socket_write($val,$send,strlen($send));
		}
	}
    public static function WebServer(){
        $master_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_set_option($master_socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($master_socket,static::$ip,static::$port);              
        socket_listen($master_socket, 100);
		$server_socket=[$master_socket];
        while(1) {	
			$tmp_socket=$server_socket;
            $write = NULL;
            $except = NULL;
			
            socket_select($tmp_socket, $write, $except, NULL);
            foreach($tmp_socket as $socket){
				if($socket == $master_socket){
					$child_socket=socket_accept($master_socket);
					if ($child_socket < 0) {
						continue;
					}else{
						$server_socket[]=$child_socket;
					}
				}else{
					$bytes = socket_recv($socket,$receive,2048,0);
					
                    // if($bytes == 0) return;
					// var_dump($receive);
					$PHPSESSID=null;
					foreach(static::$socket_list as $key=>$val){
						if($val==$socket){
							$PHPSESSID=$key;
						}
					}
					if($PHPSESSID){
						$message=static::decode($receive);
						
						if(strlen($message)==2 && ord($message[0])==3 && ord($message[1])==233){
							unset($server_socket[array_search($socket,$server_socket)]);
							$pid = pcntl_fork();
							if($pid){
								static::$exit_pid[$PHPSESSID]=$pid;
							}else{
								sleep(1);
								unset(static::$socket_list[$PHPSESSID]);
								$online_count=count(static::$socket_list);
								$message="斷線";
								$send=static::frame(json_encode(compact("message","online_count")));
								
								foreach(static::$socket_list as $val){
									socket_write($val,$send,strlen($send));
								}
								exit;
							}
						}else{
							$send=compact("message","online_count");
							
							var_dump($send);
							static::send_all($send);
						}	
					}else if($hash_key=static::doHandShake($receive)){//websocket握手
						$cookie=static::getCookie($receive);
						$PHPSESSID=$cookie['PHPSESSID'];
						$send = "HTTP/1.1 101 Switching Protocols\r\n" .
							   "Upgrade: websocket\r\n" .
							   "Connection: Upgrade\r\n" .
							   "Sec-WebSocket-Accept: {$hash_key}\r\n" .
							   "\r\n";
						var_dump("websocket握手");
						socket_write($socket,$send, strlen($send));
						
						
						if(isset(static::$socket_list[$PHPSESSID])){
							socket_close(static::$socket_list[$PHPSESSID]);
							
							$pid=static::$exit_pid[$PHPSESSID];
							if(file_exists("/proc/{$pid}/fd/3")){
								system("kill {$pid}");//殺掉離線的socket
							}
							var_dump("已有連線過");
							
							static::$socket_list[$PHPSESSID]=$socket;
						}else{
							static::$socket_list[$PHPSESSID]=$socket;
							var_dump("第一次進來");
							
							$message="{$PHPSESSID}進來了";
							$online_count=count(static::$socket_list);
							$send=compact("message","online_count");
							
							static::send_all($send);
						}
					}
				}
			}
		}	
    }
}