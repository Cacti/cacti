<?php

class Net_Ping
{
	var $udp_socket;
	var $request;
	var $request_len;
	var $reply;
	var $errstr;
	var $time;
	var $timer_start_time;

	function Net_Ping()
	{
		$this->udp_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_block($this->udp_socket);
	}

	function close_socket()
	{
		socket_close($this->udp_socket);
	}

	function start_time()
	{
		$this->timer_start_time = microtime();
	}

	function get_time($acc=2)
	{
		// format start time
		$start_time = explode (" ", $this->timer_start_time);
		$start_time = $start_time[1] + $start_time[0];
		// get and format end time
		$end_time = explode (" ", microtime());
		$end_time = $end_time[1] + $end_time[0];
		return number_format ($end_time - $start_time, $acc);
	}

	function build_packet()
	{
		$data  = "cacti-monitoring-system"; // the actual test data

		// now lets build the actual icmp packet
		$this->request = chr(0) . chr(1) . chr(0) . $data . chr(0);
		$this->request_len = strlen($this->request);
	}

	function ping($dst_addr,$timeout=5,$percision=3)
	{
		// lets catch dumb people
		if ((int)$timeout <= 0) $timeout=5;
		if ((int)$percision <= 0) $percision=3;

		// set the timeout
		socket_set_option($this->udp_socket,
			SOL_SOCKET,  // socket level
			SO_RCVTIMEO, // timeout option
			array(
				"sec"=>$timeout, // Timeout in seconds
				"usec"=>0  // I assume timeout in microseconds
			));

		if ($dst_addr) {
			if (@socket_connect($this->udp_socket, $dst_addr, 2336)) {
				// do nothing
			}else {
				$this->errstr = "Cannot connect to $dst_addr";
				return FALSE;
			}

			$this->build_packet();
			$this->start_time();

			socket_connect($this->udp_socket, $dst_addr, 33439);
			socket_write($this->udp_socket, $this->request, $this->request_len);

			$code = @socket_recv($this->udp_socket, &$this->reply, 256, 0);

			if (($code) || (empty($code))) {
				$this->time = $this->get_time($percision);
				if (($this->time*1000) <= $timeout)
					return $this->time;
				else
					return FALSE;
			} else {
				$this->errstr = "Timed out";
				return FALSE;
			}
		}else {
			$this->errstr = "Destination address not specified";
			return FALSE;
		}
	}
}

//$ping = new Net_Ping;
//$i = 0;
//$j = 0;
//while (1) {
//	$ping->ping("66.165.156.173", 5, 4);
//	$ping->ping("192.168.0.2", 5000, 4);
//
//	$i++;
//	if ($i == 100) {
//		$j++;
//		$k = $j * 100;
//		echo "Number of Hosts Polled->" . $k . "\n";
//		$i = 0;
//	}

//	if ($ping->time)
//	  echo "Time: ".$ping->time."\n";
//	else
//	  echo $ping->errstr."\n";
//}
//$ping->close_socket();

?>