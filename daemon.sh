if [ $1 == "start" ]; then
	if [ -e pid.txt ]; then
		echo "run"
	else
		nohup php server.php 2>&1 &
		echo $! > pid.txt
		echo "start"
	fi
elif [ $1 == "stop" ]; then
	if [ -e pid.txt ]; then
		kill `cat pid.txt`
		rm -rf pid.txt
		echo "stop"
	else
		echo "not run"
	fi
fi
