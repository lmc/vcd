<?php
class dOffset extends dInt32
{
	public function formatValue($value)
	{
		if(!is_numeric($value) && $value[0] == ':')
			return $value;
		$value = flipEnd($value);
		$ret = unpack('l',$value);
		return $ret[1];
	}
	
	public function formatDisplay($value)
	{
		return ':'.$value;
	}
	
	public function reParse($value)
	{
		return $value;
	}
	
	public function getAssembly($value,&$offset,&$scm)
	{
		$scm->registerOffsetRe($value,$offset);
		return pack('l',0);
	}
}
?>