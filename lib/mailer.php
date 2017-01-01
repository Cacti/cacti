<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* Mailer class for MIME email functionality */
class Mailer {

	var $Config = array();
	var $Message = array();
	var $Error = '';

	/* 
	 * Create mailer object
 	 *
	 * Create the mailer object
	 *
	 * @param config - Array of config value pairs.  config=>value
	 * @param headers - Array of header value pairs.  header=>value
	 * @return true 
	 */
	function Mailer($config = array(), $headers = array()) {

		/* Create configuration array */
		$this->Config['User'] = 'unknown';
		$this->Config['Hostname'] = 'localhost';
		$this->Config['AttachMaxSize'] = 10240000;
		$this->Config['MaxSize'] = 10240000;
		$this->Config['HeaderOrder'] = array('From','To','Cc','Bcc','Reply-To','Date','X-Mailer','User-Agent','Subject','Content-Type');
		$this->Config['HeaderMulti'] = array('To','Cc','Bcc');
		$this->Config['HeaderRequire'] = array('To','From','Subject','Content-Type','Date');
		$this->Config['Mail']['Type'] = 'PHP'; /* PHP or SMTP or DirectInject */
		$this->Config['Mail']['SMTP_Host'] = 'localhost';
		$this->Config['Mail']['SMTP_Port'] = '25';
		$this->Config['Mail']['SMTP_Username'] = '';
		$this->Config['Mail']['SMTP_Password'] = '';
		$this->Config['Mail']['DirectInject_Path'] = ''; /* Message envelope will be contructed and passed to this executable via stdin */
		$this->Config['Mail']['WordWrap'] = '76';
		$this->Config['Mail']['CharSet'] = 'UTF-8';

		/* Process mailer configuration parameters */
		if (sizeof($config) > 0) {
			foreach ($config as $key => $value) {
				if (($key == 'Type') && (($value == 'SMTP') || ($value == 'PHP') || ($value == 'DirectInject'))) {
					$this->Config['Mail']['Type'] = $value;
				}
				if ($key == 'SMTP_Host') {
					$this->Config['Mail']['SMTP_Host'] = $value;
				}
				if ($key == 'SMTP_Port') {
					$this->Config['Mail']['SMTP_Port'] = $value;
				}
				if ($key == 'SMTP_Username') {
					$this->Config['Mail']['SMTP_Username'] = $value;
				}
				if ($key == 'SMTP_Password') {
					$this->Config['Mail']['SMTP_Password'] = $value;
				}
				if ($key == 'DirectInject_Path') {
					$this->Config['Mail']['DirectInject_Path'] = $value;
				}
				if ($key == 'WordWrap') {
					$this->Config['Mail']['WordWrap'] = $value;
				}
				if ($key == 'CharSet') {
					$this->Config['Mail']['CharSet'] = $value;
				}
			}
		}

		/* Find hostname */
		if (isset($_SERVER['SERVER_NAME'])) {
			$this->Config['Hostname'] = $_SERVER['SERVER_NAME'];
		} else {
			if (isset($_ENV['HOSTNAME'])) {
				$this->Config['Hostname'] = $_ENV['HOSTNAME'];
			}
		}

		/* Find Username */
		if (isset($_ENV['USER'])) {
			$this->Config['User'] = $_ENV['USER'];
		}

		/* Setup global headers */
		$this->Message['headers'] = array();
		$this->Message['headers']['X-Mailer'] = 'Cacti-Mailer-Class';
		$this->Message['headers']['User-Agent'] = 'Cacti-Mailer-Class';
		$this->Message['headers']['Content-Type'] = 'text/plain';
		$this->Message['headers']['MIME-Version'] = '1.0';
		$this->Message['headers']['From'] = $this->Config['User'] . '@' .  $this->Config['Hostname'];

		/* Add headers */
		foreach ($headers as $header => $value) {
			$this->header_set($header,$value, true);
		}

		return true;

	}


	/* 
	 * Close mailer object
 	 *
	 * Closed the mailer object
	 *
	 * @return true 
	 */
	function close() {
		
		$this->Config = array();
		$this->Message = array();
		$this->Error = '';
		return true;

	}


	/* 
	 * Send email
 	 *
	 * Contructed and send the email
	 *
	 * @param message - string value of message body or array of text=>'', html=>''
	 * @param headers - array of header values, Subject required to be set
	 * @return true = success, false = error use error to get error string
	 */
	function send($body, $headers = array()) {

		$body_text = '';
		$body_html = '';

		/* Check input */
		if (! isset($body)) {
			$this->Error = 'No message body argument defined';
			return false;
		}
		if (is_array($body)) {
			if (isset($body['text'])) {
				$body_text = wordwrap($body['text'], $this->Config['Mail']['WordWrap']);
			} else {
				$this->Error = 'No text part of body defined';
				return false;
			}
			if (isset($body['html'])) {
				$body_html = wordwrap($body['html'], $this->Config['Mail']['WordWrap']);
			} else {
				$this->Error = 'No html part of body defined';
				return false;
			}
		} else {
			$body_text = wordwrap($body, $this->Config['Mail']['WordWrap']);
		}
		if (empty($body)) {
			$this->Error = 'Empty body not allowed';
			return false;
		}
		
		/* Set headers - Replace globals */
		if (isset($headers)) {
			if (is_array($headers)) {
				foreach ($headers as $key => $value) {
					$this->header_set($key, $value, true);
				}
			} else {
				$this->Error = 'Headers must be defined in an array';
				return false;
			}
		}

		/* Add date header */
		$this->header_set('Date', $this->_get_email_date(), true);


		/* Check that we have minimal headers */
		foreach ($this->Config['HeaderRequire'] as $value) {
			if (! isset($this->Message['headers'][$value])) {
				$this->Error = "Required header \'" . $value . "\' is not defined";
				return false;
			}
		}

		/* Determine attachment types */
		$attachment_inline = 0;
		$attachment_attach = 0;
		if (isset($this->Message['attachments'])) {
			foreach ($this->Message['attachments'] as $value) {
				if ($value['contentdisposition'] == 'attachment') {
					$attachment_attach++;
				}
				if ($value['contentdisposition'] == 'inline') {
					$attachment_inline++;
				}
			}
		}

		/* Determine message content-type */
		$content_type = $this->Message['headers']['Content-Type'];
		if (empty($body_html)) {
			if ($attachment_attach > 0) {
				$this->_boundary_set();
				$content_type = "multipart/mixed; type=\"text/plain\";\n\tboundary=\"" . $this->Message['boundary'] . "\"";
			}
		} else {
			$this->_boundary_set();
			$content_type = "multipart/alternative; type=\"text/html\";\n\tboundary=\"" . $this->Message['boundary'] . "\"";
			if ($attachment_attach > 0) {
				$content_type = "multipart/mixed; type=\"text/html\";\n\tboundary=\"" . $this->Message['boundary'] . "\"";
			}

		}

		/* Contruct message body */
		$this->header_set('Content-Type', $content_type, true);
		if ($content_type == 'text/plain') {
			$this->Message['body'] = $body_text;
		} else {
			/* Open MIME */
			$this->Message['body'] = "This is a multi-part message in MIME format\n\n";
			$this->Message['body'] .= '--' . $this->Message['boundary'] . "\n";
			
			/* Open MIME ALT */
			if (($attachment_attach > 0) && (! empty($body_html))) {
				$this->Message['body'] .= "Content-Type: multipart/alternative;\n\tboundary=\"" . $this->Message['boundary']  . "_ALT\"\n\n";
				$this->Message['body'] .= '--' . $this->Message['boundary'] . "_ALT\n";
			}

			/* Message part - text/plain */
			$this->Message['body'] .= 'Content-Type: text/plain;';
			$this->Message['body'] .= " charset=\"" . $this->Config['Mail']['CharSet'] . "\"\n";
			$this->Message['body'] .= "Content-Transfer-Encoding: 8-bit\n";
			$this->Message['body'] .= "\n" . $body_text . "\n\n";

			/* Open MIME HTML */
			if ((! empty($body_html)) && ($attachment_attach > 0)) {
				$this->Message['body'] .= '--' . $this->Message['boundary'] . "_ALT\n";
			} else {
				if ($attachment_attach == 0) {
					$this->Message['body'] .= '--' . $this->Message['boundary'] . "\n";
				}
			}
			if ($attachment_inline > 0) {
				$this->Message['body'] .= "Content-Type: multipart/related;\n\tboundary=\"" . $this->Message['boundary'] . "_HTML\"\n\n";
				$this->Message['body'] .= '--' . $this->Message['boundary'] . "_HTML\n";
			}

			if (! empty($body_html)) {
				/* Message part - text/html */
				$this->Message['body'] .= 'Content-Type: text/html;';
				$this->Message['body'] .= " charset=\"" . $this->Config['Mail']['CharSet'] . "\"\n";
				$this->Message['body'] .= "Content-Transfer-Encoding: 8-bit\n";
				$this->Message['body'] .= "\n" . $body_html . "\n\n";
			}

			/* Attach inline */
			if ($attachment_inline > 0) {
				foreach ($this->Message['attachments'] as $attachment) {
					if ($attachment['contentdisposition'] == 'inline') {
						$this->Message['body'] .= '--' . $this->Message['boundary'] . "_HTML\n";
						$this->Message['body'] .= 'Content-Type: ' . $attachment['contenttype'] . "; name=\"" . $attachment['filename'] . "\"\n";
						$this->Message['body'] .= 'Content-Transfer-Encoding: ' . $attachment['encoding'] . "\n";
						$this->Message['body'] .= 'Content-Disposition: ' . $attachment['contentdisposition'] . "; filename=\"" . $attachment['filename'] . "\"\n";
						if (isset($attachment['contentid'])) {
							$this->Message['body'] .= 'Content-Id: <' . $attachment['contentid'] . ">\n";
						}
						$this->Message['body'] .= "\n" . $attachment['encoded_data'] . "\n";
					}
				}
				/* Close MIME HTML */
				$this->Message['body'] .= '--' . $this->Message['boundary'] . "_HTML--\n\n";

			}

			/* Close MIME ALT */
			if (($attachment_attach > 0) && (! empty($body_html))) {
				$this->Message['body'] .= '--' . $this->Message['boundary'] . "_ALT--\n\n";
			}

			/* Attach files */
			if ($attachment_attach > 0) {
				foreach ($this->Message['attachments'] as $attachment) {
					if ($attachment['contentdisposition'] != 'inline') {
						$this->Message['body'] .= '--' . $this->Message['boundary'] . "\n";
						$this->Message['body'] .= 'Content-Type: ' . $attachment['contenttype'] . "; name=\"" . $attachment['filename'] . "\"\n";
						$this->Message['body'] .= 'Content-Transfer-Encoding: ' . $attachment['encoding'] . "\n";
						$this->Message['body'] .= 'Content-Disposition: ' . $attachment['contentdisposition'] . "; filename=\"" . $attachment['filename'] . "\"\n";
						if (isset($attachment['contentid'])) {
							$this->Message['body'] .= 'Content-id: <' . $attachment['contentid'] . ">\n";
						}
						$this->Message['body'] .= "\n" . $attachment['encoded_data'] . "\n";
					}
				}
			}

			/* Close MIME */
			$this->Message['body'] .= '--' . $this->Message['boundary'] . "--\n";

		}
			
		/* Send mail */
		if (! $this->_send_mail()) {
			return false;
		}

		/* Remove contructed message  - save memory */
		$this->Message = $this->_array_element_delete($this->Message, 'body');

		return true;

	}


	/* 
	 * Set or append a header
 	 *
	 * Set or append to a header, only To, CC, Bcc will append values
	 *
	 * @param header - string value of header, case sensitive
	 * @param value - value of the header
	 * @param replace - force replace on a multi-value header, optional, default = false
	 * @return true = success, false = error use error to get error text
	 */
	function header_set($header, $value, $replace = false) {

		if (! isset($header)) {
			$this->Error = 'No header passed to set';
			return false;
		}
		if (! isset($value)) {
			$this->Error = 'No header value passed, use header_unset to remove header';
			return false;
		}

		if ((isset($this->Message['headers'][$header])) && (in_array($header, $this->Config['HeaderMulti'])) && (! $replace)) {
			if (is_array($this->Message['headers'][$header])) {
				if (is_array($value)) {
					foreach ($value as $item) {
						if (! in_array($item, $this->Message['headers'][$header])) {
							array_push($this->Message['headers'][$header], $item);
						}
					}
				} else {
					if (! in_array($value, $this->Message['headers'][$header])) {
						array_push($this->Message['headers'][$header], $value);
					}
				}
			} else {
				$this->Message['headers'][$header] = array( $this->Message['headers'][$header], $value );
			}
		} else {
			if ( (! in_array($header, $this->Config['HeaderMulti']) ) && (is_array($value) ) )  {
				$this->Error = 'Array value not valid for non-multi headers';
				return false;
			} else {
				$this->Message['headers'][$header] = $value;
			}
		}

		return true;

	}


	/* 
	 * Remove a header
 	 *
	 * Removes give header from the global header array
	 *
	 * @param header - string value of header, case sensitive
	 * @return true = success, false = error use error to get error text
	 */
	function header_unset($header) {

		if (isset($this->Message['headers'][$header])) {
			$this->Message['headers'] = $this->_array_element_delete($this->Message['headers'], $header);
			return true;
		}	
		$this->Error = 'Header is not set';
		return false;

	}


	/* 
	 * Format an email address
 	 *
	 * Format an email address in full name <email> format
	 *
	 * @param name - string value of name
	 * @param email - string value of email address
	 * @return string - formatted email address
	 */
	function email_format($name = '', $email = '') {

		if (! isset($name)) {
			$this->Error = 'Empty name value';
			return false;
		}
		if (! isset($email)) {
			$this->Error = 'Empty email value';
			return false;
		}

		return $name . ' <' . $email . '>';

	}

	/* 
	 * Generate an unique content id
 	 *
	 * Generate an unique content id for use with inline attachments
	 *
	 * @return string - content id
	 */
	function content_id() {

		list($usec, $sec) = explode(' ', microtime());
		$usec = $usec * 10000000;

		return getmypid() . '_' . $sec . '_' . $usec . '@' . $this->Config['Hostname'];

	}


	/* 
	 * Attach a file to the email
	 
	 *
	 * Attach a file to the email, either attached or inline
	 *
	 * @param data - Data to encode as attachment
	 * @param filename - Name of the attachment, when saving this will be the file name
	 * @param contenttype = MIME type of the attachment
	 * @param contentdisposition - attachment or inline
	 * @param contentid - Content id for use in inline attachments
	 * @return string - content id
	 */
	function attach($data, $filename, $contenttype = 'application/octet', $contentdisposition = 'attachment', $contentid = '') {

		$struc = array();

		/* Check for required PHP functions */
		if (! function_exists('base64_encode')) {
			$this->Error = "Required function \'base64_encode\' not available";
			return false;
		}

		/* Check size of the data */
		if (strlen($data) == 0) {
			$this->Error = 'No data passed to attach, zero length data';
			return false;
		}
		$struc['Size'] = strlen($data);

		/* Encode the data */
		$struc['encoded_data'] = chunk_split(base64_encode($data), 76, "\n");
		$data = false; /* free up memory */
		
		/* Check size of encoded data */
		if (strlen($struc['encoded_data']) > $this->Config['AttachMaxSize']) {
			$this->Error = 'Encoded attachment size exceeds maximum size';
			return false;
		}
		$struc['SizeEncoded'] = strlen($struc['encoded_data']);

		/* Set attachment properties */
		$struc['filename'] = $filename;
		$struc['contenttype'] = $contenttype;
		$struc['contentdisposition'] = $contentdisposition;
		$struc['encoding'] = 'base64';
		if (! empty($contentid)) {
			$struc['contentid'] = $contentid;
		}

		/* Check all attachment sizes */
		if (isset($this->Message['attachments_size'])) {
			if ($this->Message['attachments_size'] + $struc['SizeEncoded'] >  $this->Config['AttachMaxSize']) {
				$this->Error = 'Unable to attach file, all attachments will exceed Max Size';
				return false;
			}
		}

		/* Push attachment into message attachments array */
		if (! isset($this->Message['attachments_size'])) {
			$this->Message['attachments_size'] = 0;
		}
		$this->Message['attachments_size'] += $struc['SizeEncoded'];
		$this->Message['attachments'][] = $struc;

		/* Set MIME boundary */
		$this->_boundary_set();

		return true;

	}


	/*
	 * Clear attached files
 	 *
	 * Clear all attached files
	 *
	 * @return boolean - true = success, false = error 
	 */
	function attach_clear() {

		$this->Message = $this->_array_element_delete($this->Message, 'attachments');
		$this->Message = $this->_array_element_delete($this->Message, 'attachments_size');
		$this->Message = $this->_array_element_delete($this->Message, 'boundary');

		return true;

	}

	/*
	 * Read a files content
 	 *
	 * Read a files content and return it, binary safe
	 *
	 * @parma - string - file to read
	 * @return - binary - file data
	 */
	function read_file($file) {


		if (preg_match('/^\S{3,4}:\/\//', $file)) {
			$this->Error = 'Only local files are allowed';
			return false;
		}

		set_magic_quotes_runtime(0);

		if (($fh = @fopen($file, 'rb')) === false) {
			$this->Error = 'Unable to open file for read';
			return false;	
		}
		if (($data = fread($fh, filesize($file))) === false) {
			$this->Error = 'Unable to read file';
			return false;	
		}
		fclose($fh);

		set_magic_quotes_runtime(get_magic_quotes_gpc());

		return $data;

	}


	/*
	 * Read Mailer error
	 * 
	 * Read the error the last ran mailer function generated
	 *
	 * @return - string - Error 
	 */
	function error() {

		return $this->Error;

	}

	/*
	 * Send email
	 * 
	 * Internal mailer function to send the email.  This will use whatever means have been configured to send email.
	 *
	 * @return - boolean - true = success, false = error
	 */
	function _send_mail() {

		/* Add additional headers to header_order */
		$headers = '';
		$header_order = $this->Config['HeaderOrder'];
		foreach ($this->Message['headers'] as $key => $value) {
			if (! in_array($key, $header_order)) {
				$header_order[] = $key;
			}
		}

		if ($this->Config['Mail']['Type'] == 'PHP') {
			/* 
			 * Process Mail with PHP internal function 
			 */
			if (! function_exists('mail')) {
				$this->Error = "Required function \'mail\' not available";
				return false;
			}
			
			/* Check ini settings in Windows */
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
				$smtp_ini = ini_get('SMTP');
				if ((empty($smtp_ini)) || (strtoupper($smtp_ini) == 'LOCALHOST')) {
					if (empty($this->Config['Mail']['SMTP_Host'])) {
						$this->Error = 'Unable to send email with php mail function, php.ini setting SMTP is not set or SMTP Host not defined';
						return false;
					} else {
						if (ini_set('SMTP', $this->Config['Mail']['SMTP_Host']) == false) {
							$this->Error = 'Unable to set php setting SMTP';
							return false;
						}
						if (ini_set('smtp_port', $this->Config['Mail']['SMTP_Port']) == false) {
							$this->Error = 'Unable to set php setting smtp_port';
							return false;
						}
					}
				}
			}


			/* Setup to */
			$to = $this->Message['headers']['To'];
			if (is_array($to)) {
				$to = implode(', ', $to);
			}
	
			/* Create additional headers */
			foreach ($header_order as $key) {
				if (($key != 'To') && ($key != 'Subject')) {
					if (isset($this->Message['headers'][$key])) {
						if (is_array($this->Message['headers'][$key])) {
							$headers .= $key . ': ' . implode(', ',$this->Message['headers'][$key]) . "\n";
						} else {
							$headers .= $key . ': ' . $this->Message['headers'][$key] . "\n";
						}
					}
				}
			}

			/* Send the email */
			if (! @mail($to, $this->Message['headers']['Subject'], $this->Message['body'], $headers)) {
				$this->Error = 'Unable to send email with php mail function, check your system logs';
				return false;
			}

		} elseif ($this->Config['Mail']['Type'] == 'SMTP') {
			/* 
			 * Process Mail with SMTP 
			 */
			if (! function_exists('fsockopen')) {
				$this->Error = "Required function 'fsockopen' not available";
				return false;
			}

			/* Create headers text */
			foreach ($header_order as $key) {
				if ((isset($this->Message['headers'][$key])) && ($key != 'Bcc')) {
					if (is_array($this->Message['headers'][$key])) {
						$headers .= $key . ': ' . str_replace("\n", "\r\n", implode(', ',$this->Message['headers'][$key])) . "\r\n";
					} else {
						$headers .= $key . ': ' . str_replace("\n", "\r\n", $this->Message['headers'][$key]) . "\r\n";
					}
				}
			}

			/* Setup from address */
			$from = $this->Message['headers']['From'];
			if (strpos($from, '<') != false) {
				$from = substr($from, strpos($from, '<') + 1);
				$from = substr($from, 0, strlen($from) - 1);
			}

			/* Setup to addresses */
			if (is_array($this->Message['headers']['To'])) {
				$to = $this->Message['headers']['To'];
			} else {
				$to = array($this->Message['headers']['To']);
			}
			if (isset($this->Message['headers']['Cc'])) {
				if (is_array($this->Message['headers']['Cc'])) {
					foreach ($this->Message['headers']['Cc'] as $item) {
						array_push($to, $item);
					}
				} else {
					array_push($to, $this->Message['headers']['Cc']);
				}
			}
			if (isset($this->Message['headers']['Bcc'])) {
				if (is_array($this->Message['headers']['Bcc'])) {
					foreach ($this->Message['headers']['Bcc'] as $item) {
						array_push($to, $item);
					}
				} else {
					array_push($to, $this->Message['headers']['Bcc']);
				}
			}

			/* Open SMTP connection */
			if (($smtp_sock = @fsockopen($this->Config['Mail']['SMTP_Host'], $this->Config['Mail']['SMTP_Port'], $errno, $errstr, 1)) === false) {
				$this->Error = "Unable to connect to SMTP Host '" . $this->Config['Mail']['SMTP_Host'] . "': (" . $errno . ') ' . $errstr;
				return false;
			}

			/* Read SMTP Socket */
			$smtp_response = fgets($smtp_sock, 4096);
			if (substr($smtp_response,0,3) != '220') {
				$this->Error = 'Error returned by SMTP host: ' . $smtp_response;
				fclose($smtp_sock);
				return false;
			}
			
			/* Start SMTP conversation */
			fputs($smtp_sock, 'HELO ' . $this->Config['Hostname'] . '\r\n');
			$smtp_response = fgets($smtp_sock, 4096);
			if (substr($smtp_response,0,3) != '250') {
				$this->Error = 'Error returned by SMTP host: ' . $smtp_response;
				fclose($smtp_sock);
				return false;
			}
			
			/* Perform Authenication - If username and password set */
			if ((! empty($this->Config['Mail']['SMTP_Username'])) && (! empty($this->Config['Mail']['SMTP_Password']))) {

				fputs($smtp_sock, "AUTH LOGIN\r\n");
				$smtp_response = fgets($smtp_sock, 4096);
				if (substr($smtp_response,0,3) != '334') {
					$this->Error = 'SMTP Host does not appear to support authenication: ' . $smtp_response;
					fclose($smtp_sock);
					return false;
				}
				
				fputs($smtp_sock, base64_encode($this->Config['Mail']['SMTP_Username']) . "\r\n");
				$smtp_response = fgets($smtp_sock, 4096);
				if (substr($smtp_response,0,3) != '334') {
					$this->Error = 'SMTP Authenication failure: ' . $smtp_response;
					fclose($smtp_sock);
					return false;
				}
				
				fputs($smtp_sock, base64_encode($this->Config['Mail']['SMTP_Password']) . "\r\n");
				$smtp_response = fgets($smtp_sock, 4096);
				if (substr($smtp_response,0,3) != '235') {
					$this->Error = 'SMTP Authenication failure: ' . $smtp_response;
					fclose($smtp_sock);
					return false;
				}
			}	

			/* Send mail from */
			fputs($smtp_sock, 'MAIL FROM: <' . $from . ">\r\n");
			$smtp_response = fgets($smtp_sock, 4096);
			if (substr($smtp_response,0,3) != '250') {
				$this->Error = 'SMTP Host rejected from address: ' . $smtp_response;	
				fclose($smtp_sock);
				return false;
			}
			
			/* Send rcpt to */
			foreach ($to as $item) {
				fputs($smtp_sock, 'RCPT TO: <' . $item . ">\r\n");
				$smtp_response = fgets($smtp_sock, 4096);
				if (substr($smtp_response,0,3) != '250') {
					$this->Error = 'SMTP Host rejected to address: ' . $smtp_response;
					fclose($smtp_sock);
					return false;
				}
			}
			
			/* Send data to start message */
			fputs($smtp_sock, "DATA\r\n");
			$smtp_response = fgets($smtp_sock, 4096);
			if (substr($smtp_response,0,3) != '354') {
				$this->Error = 'SMTP host rejected data command:' . $smtp_response;	
				fclose($smtp_sock);
				return false;
			}
			
			/* Send message headers and body */
			$message = str_replace("\n", "\r\n", $this->Message['body']);
			fputs($smtp_sock, $headers . "\r\n" . $message . "\r\n.\r\n");
			$smtp_response = fgets($smtp_sock,4096);
			if (substr($smtp_response,0,3) != '250') {
				$this->Error = 'SMTP error while sending email: ' . $smtp_response;	
				fclose($smtp_sock);
				return false;
			}
			
			/* Send quit */
			fputs($smtp_sock,"QUIT\r\n");
			$smtp_response = fgets($smtp_sock, 4096);
			if (substr($smtp_response,0,3) != '221') {
				$this->Error = 'SMTP Host rejected quit command: ' . $smtp_response;	
				fclose($smtp_sock);
				return false;
			}
			
			/* Close connection */
			fclose($smtp_sock);


		} elseif ($this->Config['Mail']['Type'] == 'DirectInject') {
			/* 
			 * Process Mail with DirectInject 
			 */
			
			/* Check and Process DirectInject path */
			if (empty($this->Config['Mail']['DirectInject_Path'])) {
				$this->Error = 'No DirectInject_Path defined';
				return false;
			}
			list($cmd) = explode(' ',$this->Config['Mail']['DirectInject_Path']);
			if (file_exists($cmd)) {
				if (is_readable($cmd)) {
					if (function_exists('is_executable')) {
						if (! is_executable($cmd)) {
							$this->Error = 'DirectInject_Path is not executable';
							return false;
						}
					}
				} else {
					$this->Error = 'DirectInject_Path is not readable';
					return false;
				}
			} else {
				$this->Error = 'DirectInject_Path does not exist';
				return false;
			}

			/* Create headers */
			foreach ($header_order as $key) {
				if (isset($this->Message['headers'][$key])) {
					if (is_array($this->Message['headers'][$key])) {
						$headers .= $key . ': ' . implode(', ',$this->Message['headers'][$key]) . "\n";
					} else {
						$headers .= $key . ': ' . $this->Message['headers'][$key] . "\n";
					}
				}
			}

			/* Contruct process */
			$desc_spec = array (
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w'),
				2 => array('pipe', 'w')
			);
			$process = proc_open($this->Config['Mail']['DirectInject_Path'], $desc_spec, $pipes, NULL, NULL);
			if (! is_resource($process)) {
				$this->Error = 'Unable to open DirectInject executable process: (' . $errno . ') ' . $errstr;
				return false;
			}

			/* Write message to process on STDIN */
			fwrite($pipes[0], $headers . "\n");
			fwrite($pipes[0], $this->Message['body'] . "\n");
			fclose($pipes[0]);

			/* Read stderr and stdout from process */
			$stdout = stream_get_contents($pipes[1]);
			$stderr = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);

			/* Close the process */
			$return_level = proc_close($process);

			/* Process process results */
			if ($return_level > 0) {
				$this->Error = "DirectInject command output: '" . $stdout . "' error: '" . $stderr . "'";
				return false;
			}


		} else {
			$this->Error = 'Invalid Mail Type Defined';
			return false;
		}

		return true;

	}


	/*
	 * RFC SMTP Date 
	 * 
	 * Returns a RFC SMTP formatted date string
	 *
	 * @param - string - input date
	 * @return - string - date string
	 */
	function _get_email_date($time = '') {

		if (empty($time)) {
			$time = time();
		}

		return date('r', $time);

	}


	/*
	 * Array Element Delete
	 * 
	 * Deletes an element from an array
	 *
	 * @param - array - array to modify
	 * @param - string - key to delete
	 * @return - array 
	 */
	function _array_element_delete($input, $search) {

		$output = array();

		if (isset($input)) {
			if (is_array($input)) {
				foreach ($input as $key => $value) {
					if ($search != $key) {
						$output[$key] = $value;
					}
				}
			}
		}	

		return $output;
	
	}


	/*
	 * Sets MIME message boundary 
	 * 
	 * Sets the MIME message boundary in the mailer object
	 *
	 * @return - true 
	 */
	function _boundary_set() {

		if (! isset($this->Message['boundary'])) {
			list($usec, $sec) = explode(' ', microtime());
			$usec = $usec * 10000000;
			$this->Message['boundary'] = '--_MAILER_' . getmypid() . '_' . $sec . '_' . $usec;
		}
		return true;

	}


/* end of class */
}

