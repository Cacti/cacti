<?php

class Net_Ping
{
	var $icmp_socket;
	var $request;
	var $request_len;
	var $reply;
	var $errstr;
	var $time;
	var $timer_start_time;

	function Net_Ping()
	{
		$this->icmp_socket = socket_create(AF_INET, SOCK_RAW, 1);
		socket_set_block($this->icmp_socket);
	}

	function close_socket()
	{
		socket_close($this->icmp_socket);
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
		$data = "cacti-monitoring-system"; // the actual test data
		$type = "\x08"; // 8 echo message; 0 echo reply message
		$code = "\x00"; // always 0 for this program
		$chksm = "\xCE\x96"; // generate checksum for icmp request
		$id = "\x40\x00"; // we will have to work with this later
		$sqn = "\x00\x00"; // we will have to work with this later

		// now lets build the actual icmp packet
		$this->request = $type.$code.$chksm.$id.$sqn.$data;
		$this->request_len = strlen($this->request);
	}

	function ping($dst_addr,$timeout=5,$percision=3)
	{
		// lets catch dumb people
		if ((int)$timeout <= 0) $timeout=5;
		if ((int)$percision <= 0) $percision=3;

		// set the timeout
		socket_set_option($this->icmp_socket,
			SOL_SOCKET,  // socket level
			SO_RCVTIMEO, // timeout option
			array(
				"sec"=>$timeout, // Timeout in seconds
				"usec"=>0  // I assume timeout in microseconds
			)
		);

		if ($dst_addr) {
			if (@socket_connect($this->icmp_socket, $dst_addr, NULL)) {
				// do nothing
			} else {
				$this->errstr = "Cannot connect to $dst_addr";
				return FALSE;
			}

			$this->build_packet();
			$this->start_time();
			socket_write($this->icmp_socket, $this->request, $this->request_len);

			if (@socket_recv($this->icmp_socket, &$this->reply, 256, 0)) {
				$this->time = $this->get_time($percision);
				return $this->time;
			} else {
				$this->errstr = "Timed out";
				return FALSE;
			}
		} else {
			$this->errstr = "Destination address not specified";
			return FALSE;
		}
	}
}

//$ping = new Net_Ping;
//$i = 0;
//$j = 0;
//while (1) {
//	$ping->ping("192.168.0.1", 5, 4);
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
