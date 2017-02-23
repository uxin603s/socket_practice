<?php
Class WS {	
	public static $ip="0.0.0.0";
	public static $port="1234";
	public static $socket_list=[];
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
	
    public static function WebServer(){
        $master_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)   ;
		socket_set_option($master_socket, SOL_SOCKET, SO_REUSEADDR, 1)  ;
        socket_bind($master_socket,static::$ip,static::$port);              
        socket_listen($master_socket, 100);
		
        while(1) {	
			$child_socket = socket_accept($master_socket);
			$receive=socket_read($child_socket,2048);
			if($data=json_decode($receive,1)){
				$PHPSESSID=$data['PHPSESSID'];
				$name=substr(md5($PHPSESSID),0,5);
				
				if($data['signal']==0){//0:普通1:進入2:離開
					$message="{$name}說:{$data['message']}";
				}else if($data['signal']==1){
					$message="{$name}進入了";
				}else if($data['signal']==2){
					
					$message="{$name}離開了";
					unset(static::$socket_list[$PHPSESSID]);
				}
				var_dump($message);
				$online_count=count(static::$socket_list);
				$send=json_encode(compact("message","online_count"));
				$send=static::frame($send);
				
				foreach(static::$socket_list as $key=>$val){
					if(file_exists("/proc/{$val['pid']}/fd/3")){
						socket_write($val['child_socket'],$send,strlen($send));
					}else{
						unset(static::$socket_list[$key]);
					}
				}
				continue;
			}else if($hash_key=static::doHandShake($receive)){//websocket握手
				$cookie=static::getCookie($receive);
				$PHPSESSID=$cookie['PHPSESSID'];
				$shead = "HTTP/1.1 101 Switching Protocols\r\n" .
					   "Upgrade: websocket\r\n" .
					   "Connection: Upgrade\r\n" .
					   "Sec-WebSocket-Accept: " . $hash_key . "\r\n" .
					   "\r\n";

				socket_write($child_socket,$shead, strlen($shead));
				
				if(isset(static::$socket_list[$PHPSESSID])){
					$val=static::$socket_list[$PHPSESSID];
					system("kill {$val['pid']}");
					unset(static::$socket_list[$PHPSESSID]);
				}else{
					$signal=1;
					$send=json_encode(compact(["PHPSESSID","signal"]));
					static::client($send);
				}
				static::fork($child_socket,$PHPSESSID);
			}else{
				continue;
			}
        }
    }
	
	public function fork($child_socket,$PHPSESSID){
		
		$pid = pcntl_fork();
		if($pid){			
			static::$socket_list[$PHPSESSID]=compact("child_socket","PHPSESSID","pid");
		}else{
			while(1){
				$receive = static::decode(socket_read($child_socket,8192));
				if(strlen($receive)==2 && ord($receive[0])==3 && ord($receive[1])==233){
					sleep(1);
					$signal=2;
					
					$send=json_encode(compact(["PHPSESSID","signal"]));
					static::client($send);
					socket_close($child_socket);
					exit;
				}
				else{
					$signal=0;
					$message=$receive;
					$send=json_encode(compact(["PHPSESSID","signal","message"]));
					static::client($send);
				}
			}
			
		}
	}


	public static function client($send){
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		socket_connect($socket,static::$ip,static::$port);
		socket_write($socket,$send,strlen($send));
		socket_close($socket);		
	}
}
