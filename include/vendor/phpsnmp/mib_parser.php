<?php
/*
   +-------------------------------------------------------------------------+
   | Copyright (C) 2004-2022 The Cacti Group                                 |
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
   | Cacti: The Complete RRDtool-based Graphing Solution                     |
   +-------------------------------------------------------------------------+
   | This code is designed, written, and maintained by the Cacti Group. See  |
   | about.php and/or the AUTHORS file for specific developer information.   |
   +-------------------------------------------------------------------------+
   | http://www.cacti.net/                                                   |
   +-------------------------------------------------------------------------+
*/

/* This mib parser bases on the PHP mib_compiler script of David Eder for
   phpsnmp which bases itself on libsnmp. Both scripts are licensed under the
   GNU Lesser General Public License.

   /**
   * phpsnmp - a PHP SNMP library
   * Copyright (C) 2004 David Eder <david@eder,us>
   *
   * Based on snmp - a Python SNMP library
   * Copyright (C) 2003 Unicity Pty Ltd <libsnmp@unicity.com.au>
   *
   * This library is free software; you can redistribute it and/or
   * modify it under the terms of the GNU Lesser General Public
   * License as published by the Free Software Foundation; either
   * version 2.1 of the License, or (at your option) any later version.
   *
   * This library is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
   * Lesser General Public License for more details.
   *
   * You should have received a copy of the GNU Lesser General Public
   * License along with this library; if not, write to the Free Software
   * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
   *
*/
class MibParser extends MibCache {
	protected $parsed = array();
	public    $oids   = array();
	public    $mib    = false;

	/**
	 * Constructor
	 */
	function __construct() {
		set_time_limit(0);
		ini_set('memory_limit', '-1');
		error_reporting(E_ALL);
	}

	function add_mib($filename, $mib_name) {
		MibParser::parse_mib(file_get_contents($filename), $mib_name, true);
	}

	/**
	 * Get Tokens
	 *
	 * @param string $text
	 * @return array
	 */
	function get_tokens($text) {
		$in_quote = false;
		$in_comment = false;
		$token = '';
		$tokens = array();
		$length = strlen($text);

		for($i = 0; $i < $length; $i++) {
			if ($in_quote) {
				if ($text[$i] == '"') {
					$in_quote = false;

					if ($token != '') {
						/* strip whitespaces from the end of the beginning of every object description row */
						$lines = preg_split( '/\r\n|\r|\n/', $token);
						$token = '';

						foreach($lines as $line) {
							$token .= trim($line) . "\r\n";
						}

						$tokens[] = $token;
						$token = '';
					}
				} else {
					$token .= $text[$i];
				}
			} elseif ($in_comment) {
				if ($text[$i] == "\n" || $text[$i] == "\r") {
					$in_comment = false;
				}
			} else {
				switch($text[$i]) {
					case ':':
						if ($text[$i+1] == ':' && $text[$i+2] == '=') {
							if ($token != '') {
								$tokens[] = $token;
								$token = '';
							}

							$tokens[] = '::=';
							$i += 2;
						} else {
							$token .= $text[$i];
						}

						break;
					case '.':
						if ($text[$i+1] == '.') {
							if ($token != '') {
								$tokens[] = $token;
								$token = '';
							}

							$tokens[] = '..';
							$i++;
						} else {
							$token .= $text[$i];
						}

						break;
					case ',':
					case ';':
					case '{':
					case '}':
					case '(':
					case ')':
					case '|':
						if ($token != '') {
							$tokens[] = $token;
							$token = '';
						}

						$tokens[] = $text[$i];

						break;
					case ' ':
					case "\t":
					case "\n":
					case "\r":
						if ($token != '') {
							$tokens[] = $token;
							$token = '';
						}

						break;
					case '-':
						if ($text[$i+1] == '-') {
						  $in_comment = true;
						} else {
							$token .= $text[$i];
						}

						break;
					case '"';
						$in_quote = true;

						break;
					default:
						$token .= $text[$i];
				}
			}
		}

		if ($token != '') {
			$tokens[] = $token;
		}

		return $tokens;
	}

	/**
	 * Parse simple token
	 *
	 * @param array $tokens
	 * @param integer $index
	 * @param array $allowed
	 * @return array
	 */
	function parse_simple_token($tokens, &$index, $allowed=null) {
		$index++;

		if (is_array($allowed)) {
			if (in_array(strtolower($tokens[$index]), $allowed)) {
				return $tokens[$index];
			}
		} elseif (is_null($allowed)) {
			if ($tokens[$index] == '{') {
				return MibParser::parse_bracket_token($tokens, $index, '{', '}');
			} else {
				return $tokens[$index];
			}
		}

		trigger_error("unknown token {$tokens[$index]} {$tokens[$index]}", E_USER_ERROR);

		return $tokens[$index];
	}

	/**
	 * Parse SYNTAX token
	 *
	 * @param array $tokens
	 * @param integer $index
	 * @return array
	 */
	function parse_SYNTAX_token($tokens, &$index) {
		$ret = null;

		switch($tokens[$index+1]) {
			case 'SEQUENCE':
				if ($tokens[$index+2] == 'OF') {
					$index += 3;
					if ($tokens[$index] == '{') {
						$ret = array('SEQUENCE OF'=>MibParser::parse_bracket_token($tokens, $index, '{', '}'));
					} else {
						$ret = array('SEQUENCE OF'=>$tokens[$index]);
					}
				}

				break;
			case 'OCTET':
				if ($tokens[$index+2] == 'STRING') {
					$index += 3;

					if ($tokens[$index] == '{') {
						$ret = array('OCTET STRING'=>MibParser::parse_bracket_token($tokens, $index, '{', '}'));
					} elseif ($tokens[$index] == '(') {
						$ret = array('OCTET STRING'=>MibParser::parse_bracket_token($tokens, $index, '(', ')'));
					} else {
						$ret = 'OCTET STRING';
					}
				}

				break;
			case 'OBJECT':
				if ($tokens[$index+2] == 'IDENTIFIER') {
					$index++;
					$ret = $tokens[$index] . ' ' . $tokens[$index+1];
					$index++;
				} else {
					trigger_error("unknown token {$tokens[$index+1]} {$tokens[$index+2]}", E_USER_ERROR);
				}

				break;
			case 'INTEGER':
			case 'Counter':
			case 'Counter32':
			case 'Counter64':
			case 'Integer32':
			case 'Gauge':
			case 'Gauge32':
			case 'TimeStamp':
			case 'TimeTicks':
			case 'PhysAddress':
			case 'IpAddress':
			case 'DateAndTime':
			case 'TimeInterval':
			case 'Unsigned32':
			case 'DisplayString':
				$index++;
				$ret = $tokens[$index];

				if ($tokens[$index+1] == '{') {
					$index++;
					$ret = array($ret=>MibParser::parse_bracket_token($tokens, $index, '{', '}'));
				} elseif ($tokens[$index+1] == '(') {
					$index++;
					$ret = array($ret=>MibParser::parse_bracket_token($tokens, $index, '(', ')'));
				}

				break;
			default:
				$index++;
				$ret = $tokens[$index];

				if ($tokens[$index+1] == '{') {
					$index++;
					$ret = array($ret=>MibParser::parse_bracket_token($tokens, $index, '{', '}'));
				} elseif ($tokens[$index+1] == '(') {
					$index++;
					$ret = array($ret=>MibParser::parse_bracket_token($tokens, $index, '(', ')'));
				}

				break;
		}

		return $ret;
	}

	/**
	 * Parse bracket token
	 *
	 * @param array $tokens
	 * @param integer $index
	 * @param integer $start
	 * @param integer $end
	 * @return array
	 */
	function parse_bracket_token($tokens, &$index, $start, $end) {
		$begin = $index + 1;

		while($index + 1 < count($tokens) && $tokens[$index] != $end) {
			$index++;

			if ($tokens[$index] == $start) {
				MibParser::parse_bracket_token($tokens, $index, $start, $end);
				$index++;
			}
		}

		return array_slice($tokens, $begin, $index - $begin);
	}

	/**
	 * Parse a MIB file
	 *
	 * @param string $mibtext
	 * @param boolean $full
	 */
	function parse_mib($mibtext, $mib_name, $full=false) {
		$tokens = MibParser::get_tokens($mibtext);
		$cnt = count($tokens);
		$rec = array();

		for($index = 0; $index < $cnt; $index++) {
			if ($tokens[$index] == 'DEFINITIONS' && $tokens[$index+1] == "::=" && $tokens[$index+2] == "BEGIN"){
				$mib_name = $tokens[$index-1];
				$this->mib = $mib_name;
			} elseif (in_array($tokens[$index], array('OBJECT-IDENTITY', 'OBJECT-TYPE', 'OBJECT-GROUP', 'NOTIFICATION-GROUP', 'MODULE-IDENTITY', 'NOTIFICATION-TYPE'))) {
				if ($tokens[$index-1] != ',' && $tokens[$index+1] != 'FROM' && $tokens[$index+1] != 'MACRO') {
					if (isset($rec['NAME']) && isset($rec['VALUE'])) {
						$this->parsed[] = $rec;
					}

					$rec = array(
						'NAME' => $tokens[$index-1],
						'MIB'  => $mib_name,
						'TYPE' => $tokens[$index]
					);
				}
			} elseif ( $tokens[$index] == 'TEXTUAL-CONVENTION') {
				if ($tokens[$index-1] == '::=') {
					if (isset($rec['NAME']) && isset($rec['VALUE'])) {
						$this->parsed[] = $rec;
					}

					$rec = array(
						'NAME'  => $tokens[$index-2],
						'MIB'   => $mib_name,
						'TYPE'  => $tokens[$index],
						'VALUE' => 'TEXTUAL-CONVENTION'
					);
				}
			} elseif ($tokens[$index] == 'OBJECT') {
				if ($tokens[$index+1] == 'IDENTIFIER' && $tokens[$index-1] != '(' && $tokens[$index-1] != '::=' && $tokens[$index-1] != 'SYNTAX' && $tokens[$index-2] != '(') {
					if (isset($rec['NAME']) && isset($rec['VALUE'])) {
						$this->parsed[] = $rec;
					}

					$rec = array(
						'NAME' => $tokens[$index-1],
						'MIB'  => $mib_name,
						'TYPE' => $tokens[$index]
					);
				}
			} elseif ($tokens[$index] == '{') {
				MibParser::parse_bracket_token($tokens, $index, '{', '}');
			} elseif (isset($rec['NAME'])) {
				if ($tokens[$index] == '::=' && $tokens[$index+1] != 'TEXTUAL-CONVENTION') {
					$rec['VALUE'] = MibParser::parse_simple_token($tokens, $index);
					$this->parsed[] = $rec;
					$rec = array();
				} elseif ($full) {
					if ($tokens[$index] == 'ACCESS') {
						$rec['ACCESS'] = MibParser::parse_simple_token($tokens, $index, array('read-only', 'not-accessible', 'read-write'));
					} elseif ($tokens[$index] == 'OBJECTS') {
						$rec['OBJECTS'] = MibParser::parse_simple_token($tokens, $index);
					} elseif ($tokens[$index] == 'NOTIFICATIONS') {
						$rec['NOTIFICATIONS'] = MibParser::parse_simple_token($tokens, $index);
					} elseif ($tokens[$index] == 'DEFVAL') {
						$rec['DEFVAL'] = MibParser::parse_simple_token($tokens, $index);
					} elseif ($tokens[$index] == 'DESCRIPTION') {
						$rec['DESCRIPTION'] = MibParser::parse_simple_token($tokens, $index);
					} elseif ($tokens[$index] == 'INDEX') {
						$rec['INDEX'] = MibParser::parse_simple_token($tokens, $index);
					} elseif ($tokens[$index] == 'MAX-ACCESS') {
						$rec['MAX-ACCESS'] = MibParser::parse_simple_token($tokens, $index, array('read-only', 'not-accessible', 'read-write', 'read-create', 'accessible-for-notify'));
					} elseif ($tokens[$index] == 'REFERENCE') {
						$rec['REFERENCE'] = MibParser::parse_simple_token($tokens, $index);
					} elseif ($tokens[$index] == 'STATUS') {
						$rec['STATUS'] = MibParser::parse_simple_token($tokens, $index, array('current', 'deprecated', 'obsolete', 'mandatory'));
					} elseif ($tokens[$index] == 'SYNTAX') {
						$rec['SYNTAX'] = MibParser::parse_SYNTAX_token($tokens, $index);
					} elseif ($tokens[$index] == 'UNITS') {
						$rec['UNITS'] = MibParser::parse_simple_token($tokens, $index);
					}
				}
			}
		}

		if (isset($rec['NAME']) && isset($rec['VALUE'])) {
			$this->parsed[] = $rec;
		}
	}

	function generate(){
		$this->oids['enterprises'] = array('oid' => '.1.3.6.1.4.1');

		foreach($this->parsed as $object) {
			if (isset($object['VALUE'][0]) && !is_numeric($object['VALUE'][0])) {
				if (isset($object['VALUE'][1]) && is_numeric($object['VALUE'][1])) {
					if (isset($this->oids[$object['VALUE'][0]]['oid'])) {
						$oid = $this->oids[$object['VALUE'][0]]['oid'] . '.' . $object['VALUE'][1];
					} else {
						$oid = db_fetch_cell_prepared('SELECT `oid`
							FROM snmpagent_cache
							WHERE name = ?
							LIMIT 1',
							array($object['VALUE'][0]));

						$oid .= '.' . $object['VALUE'][1];
					}

					$syntax = null;
					if (isset($object['SYNTAX'])) {
						$syntax = is_array($object['SYNTAX']) ? key($object['SYNTAX']) : $object['SYNTAX'];
					}

					$parent_otype = strtoupper(substr($object['VALUE'][0], -5));
					$otype = $object['TYPE'];

					if ($otype == 'OBJECT-TYPE' && $syntax !== null && !in_array(strtoupper(substr($object['NAME'], -5)), array('TABLE', 'ENTRY')) && !in_array($parent_otype, array('TABLE', 'ENTRY')) ) {
						$oid .= '.0';
						$otype = 'DATA';
					}

					$kind ='unknown';
					if (in_array($otype, array('MODULE-IDENTITY', 'OBJECT-IDENTITY'))) {
						$kind = 'Node';
					} else if (in_array($otype, array('NOTIFICATION-GROUP', 'OBJECT-GROUP'))) {
						$kind = 'Group';
					} else if ($otype == 'OBJECT-TYPE' && strtoupper(substr($object['NAME'], -5)) == 'TABLE' && $syntax == 'SEQUENCE OF') {
						$kind = 'Table';
					} else if ($otype == 'OBJECT-TYPE' && strtoupper(substr($object['NAME'], -5)) == 'ENTRY' && $parent_otype == 'TABLE') {
						$kind = 'Row';
					} else if ($otype == 'OBJECT-TYPE' && $parent_otype == 'ENTRY') {
						$kind = 'Column';
					} else if ($otype == 'DATA') {
						$kind = 'Scalar';
					} else if ($otype == 'NOTIFICATION-TYPE') {
						$kind = 'Notification';
					}

					$this->oids[$object['NAME']] = array(
						'oid'         => $oid,
						'max-access'  => (isset($object['MAX-ACCESS'])? $object['MAX-ACCESS'] : 'not-accessible'),
						'syntax'      => $syntax,
						'otype'       => $otype,
						'kind'        => $kind,
						'mib'         => $object['MIB'],
						'description' => $object['DESCRIPTION']
					);

					if ($otype == 'OBJECT-GROUP' && isset($object['OBJECTS'])) {
						$this->oids[$object['NAME']]['objects'] = array_diff($object['OBJECTS'], array(','));
					} elseif ($otype == 'NOTIFICATION-GROUP' && isset($object['NOTIFICATIONS'])) {
						$this->oids[$object['NAME']]['notifications'] = array_diff($object['NOTIFICATIONS'], array(','));
					} elseif ($otype == 'NOTIFICATION-TYPE' && isset($object['OBJECTS'])) {
						$this->oids[$object['NAME']]['objects'] = array_diff($object['OBJECTS'], array(','));
					}
				} elseif ($object['VALUE'] == 'TEXTUAL-CONVENTION') {
					$syntax = null;

					if (isset($object['SYNTAX'])) {
						$syntax = is_array($object['SYNTAX']) ? key($object['SYNTAX']) : $object['SYNTAX'];
					}

					$kind = 'Textual-Convention';
					$otype = 'TEXTUAL-CONVENTION';

					$this->oids[$object['NAME']] = array(
						'oid'         => '',
						'max-access'  => '',
						'syntax'      => $syntax,
						'otype'       => $otype,
						'kind'        => $kind,
						'mib'         => $object['MIB'],
						'description' => $object['DESCRIPTION']
					);
				}
			}
		}

		unset($this->oids['enterprises']);
		unset($this->parsed);
	}
}

