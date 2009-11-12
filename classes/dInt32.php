<?php
class dInt32 extends dBase
{
	public $type = 'int';
	public $signed = true;
	public $size = 4;
	
	public function formatValue($value,$isOffset = false)
	{
		switch(strlen($value))
		{
			case 4:
				$f = 'l';
			case 2:
				if(!$f)
					$f = 's';
			case 1:
				if(!$f)
					$f = 'c';
				if(strlen($value) == 4 && !$isOffset)
					$value = flipEnd($value);
				$v = unpack($f,$value);
				$v = $v[1];
				return $v;
		}
		return raw2dec($value);
	}
	
	public function reParse($value)
	{
		switch(get_class($this))
		{
			case 'dInt32':
				$f = 'l';
			case 'dInt16':
				if(!$f)
					$f = 's';
			case 'dInt8':
				if(!$f)
					$f = 'c';
				$ret = pack($f,$value);
			break;
			default:
				throw new exception_framework("Unknown datatype for dInt32::reParse()",0,get_class($this));
		}
		#$ret = flipEnd($ret);
		return $ret;
	}
	
	public function getAssembly($value)
	{
		return $value;
	}
}
?>