<?php
class dMString8 extends dBase
{
	public $type = 'string';
	public $signed = null;
	public $size = 8;
	
	public function formatValue($value)
	{
		return sprintf('"%s"',preg_replace('/[^A-Za-z0-9_]/','',$value));
	}
	
	public function reParse($value)
	{
		$value = str_replace('"','',$value);
		$CCs = str_repeat(hex2raw('CC'),1024);
		return str_pad($value,8,"\0".$CCs);
	}
	
	public function getAssembly($value,&$offset,&$scm)
	{
		$return = self::reParse($value);
		return $return;
	}
}
?>