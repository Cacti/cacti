<?php
/**
* @version V3.20 17 Feb 2003 (c) 2000-2003 John Lim (jlim@natsoft.com.my). All rights reserved.
* Released under both BSD license and Lesser GPL library license.
* Whenever there is any discrepancy between the two licenses,
* the BSD license will take precedence.
*
* Set tabs to 4 for best viewing.
*
* Latest version is available at http://php.weblogs.com
*
*  Portable MSSQL Driver that supports || instead of +
*
*/
include_once(ADODB_DIR.'/drivers/adodb-mssql.inc.php');

class ADODB_mssqlpo extends ADODB_mssql {
	var $databaseType = "mssqlpo";
	var $concat_operator = '||'; 
	function ADODB_mssqlpo()
	{
		ADODB_mssql::ADODB_mssql();
	}
	
	/*
		The big difference between mssqlpo and it's parent mssql is that mssqlpo supports
		the more standard || string concatenation operator.
	*/
	function _query($sql,$inputarr)
	{
		return ADODB_mssql::_query(str_replace('||','+',$sql),$inputarr);
	}
}

class ADORecordset_mssqlpo extends ADORecordset_mssql {
	var $databaseType = "mssqlpo";
	function ADORecordset_mssqlpo($id,$mode=false)
	{
		$this->ADORecordset_mssql($id,$mode);
	}
	
	function MoveNext() 
	{
		if (!$this->EOF) {		
			$this->_currentRow++;
			if ($this->fetchMode & ADODB_FETCH_ASSOC) {
			global $ADODB_mssql_has_datetimeconvert;
				if ($ADODB_mssql_has_datetimeconvert) // only for PHP 4.2.0 or later
					$this->fields = @mssql_fetch_assoc($this->_queryID);
				else {
					$flds = @mssql_fetch_array($this->_queryID);
					if (is_array($flds)) {
						$fassoc = array();
						foreach($flds as $k => $v) {
							if (is_numeric($k)) continue;
							$fassoc[$k] = $v;
						}
						$this->fields = $fassoc;
					} else 
						$this->fields = $flds;
				}
				if (ADODB_ASSOC_CASE == 0) {
					foreach($this->fields as $k=>$v) {
						$this->fields[strtolower($k)] = $v;
					}
				} else if (ADODB_ASSOC_CASE == 1) {
					foreach($this->fields as $k=>$v) {
						$this->fields[strtoupper($k)] = $v;
					}
				}
				
			} else {
				$this->fields = @mssql_fetch_row($this->_queryID);
			}
			if (is_array($this->fields)) return true;
			$this->EOF = true;
		}
		return false;
	}
}
?>