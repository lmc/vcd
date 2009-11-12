<?php
class dGVar extends dBase
{
	public $type = 'var';
	public $size = 2;
	
	public function formatValue($value)
	{
		return dInt32::formatValue($value,true);
	}
	
	public function formatDisplay($value)
	{
		return '$_'.$value;
	}
	
	public function reParse($value)
	{
		return $value;
	}
	
	public function getAssembly($value,&$offset,&$scm)
	{
		$scm->registerVarRe($value,$offset);
		return pack('s',0);
	}

}
?>