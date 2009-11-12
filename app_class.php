<?php
class application_vcd extends application
{
	public function cout($str)
	{
		$args = func_get_args();
		$str = array_shift($args);
		
		$out = vsprintf($str,$args).BR;
		if($this->captureCout)
			$this->captureCoutStr .= $out;
		else
			print $out;
	}
	
	public function captureCout($name = null)
	{
		if(!$name)
		{
			$this->captureCout = null;
			return $this->captureCoutStr;
		}
		$this->captureCoutStr = '';
		$this->captureCout = $name;
	}
	
	public function cHex($str)
	{
		$out = '';
		$out = raw2hex($str);
		$this->cout($out);
	}
}

function hex2raw($hex)
{
	$data = '';
	for($i = 0; $i < strlen($hex);)
	{
		$data .= chr(hexdec($hex[$i++].$hex[$i++]));
	}
	return $data;
}

function flipEnd($data)
{
	return strrev($data);
}

function raw2hex($str)
{
	$out = '';
	for($i = 0; $i < strlen($str); $i++)
		$out .= bin2hex($str[$i]);
	return $out;
}

function raw2dec($data)
{
	$dec = 0;
	for($i = strlen($data); $i >= 0; $i--)
	{
		$dec += ord($data[$i]);
	}
	return $dec;
}

function raw2dec2($data)
{
	$hex = bin2hex($data);
	return hexdec($data);
}
/*
set_error_handler('error_handler');
function error_handler($code,$error)
{
	if($code == E_WARNING)
		if(1)
			app()->cout('WARNING: '.$error);
		else
			print 'WARNING: '.$error.BR;
}
*/
?>