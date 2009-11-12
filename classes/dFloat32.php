<?php
class dFloat32 extends dBase
{
	const PRECISION = 4;
	public $size = 4;
	
	public function formatValue($value)
	{
		$value = flipEnd($value);
		$value = unpack('f',$value);
		$value = $value[1];
		$value = round($value,self::PRECISION);
		if(strpos($value,'.') === false)
			$value .= '.0';
		return $value;
	}
	
	public function reParse($value)
	{
		$value = pack('f',$value);
		#$value = flipEnd($value);
		return $value;
	}
	
	public function getAssembly($value)
	{
		return $value;
	}
}
?>