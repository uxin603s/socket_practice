<?php
session_start();
// $_SESSION['name']="王麒";
?>
<!DOCTYPE html>
<html>
<head>
<script src="js/jquery.js"></script>
<script>

$(document).ready(function(){
	var ws = new WebSocket("ws://114.33.17.163:1234");
	ws.onopen = function(){
		console.log("握手成功");
	};
	ws.onerror = function(){
		console.log("error");
	};
	ws.onmessage = function(e) {
		var res=JSON.parse(e.data);
		console.log(res)
		$("#show").append("<div>"+res.message+"</div>")
	}
	$("#text").keyup(function(e){
		if(e.keyCode==13){
			var text=$("#text").val().trim();
			if(ws.readyState==3){
				alert("已斷線")
				// location.reload();
				// ws = new WebSocket("ws://114.33.17.163:1234");
				// ws.onopen = function(){
					// console.log("握手成功");
					// ws.send(text);
				// };
				// ws.onmessage = function(e) {
				  // console.log(JSON.parse(e.data));
				// }
				// ws.onerror = function(e){
					// console.log(e);
				// };
			}else{
				ws.send(text);
			}
			$("#text").val("");
		}
	})
})
</script>
</head>
<body>
<textarea id="text"></textarea>

<div id="show">
</div>

</body>
</html>