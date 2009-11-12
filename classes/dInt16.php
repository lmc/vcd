<?php
class dInt16 extends dInt32
{
	public $size = 2;
	
	public function formatValue($value)
	{
		$value = flipEnd($value);
		$ret = unpack('s',$value);
		return $ret[1];
	}
}
?>