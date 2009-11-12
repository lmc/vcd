<?php
class dLVar extends dGVar
{
	public function formatValue($value)
	{
		$value = flipEnd($value);
		$ret = unpack('s',$value);
		return $ret[1];
	}
	
	public function formatDisplay($value)
	{
		$value = self::formatValue($value);
		return '@'.$value;
	}
	
	public function getAssembly($value,&$offset,&$scm)
	{
		$value = substr($value,1);
		printf("Found %s at %s\n",$value,$offset);
		#$scm->registerVarRe($value,$offset);
		$return = pack('s',$value);
		#$return = flipEnd($return);
		printf("Packed as %s\n\n",raw2hex($return));
		return $return;
	}
}
?>