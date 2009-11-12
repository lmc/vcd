<?php
#Base class for data-types
class dBase
{
	public $type = 'int';
	public $signed = true;
	public $size = 1;		#Bytes
	public $value = 0;
	
	public function __construct()
	{
		
	}
	
	public function formatValue($value)
	{
		throw new exception_framework("No formatValue() for data type",0,get_class($this));
	}
	
	public function formatDisplay($value)
	{
		return $this->formatValue($value);
	}
	
	public function longName()
	{
		return substr(get_class($this),1);
	}
	
	public function reParse($value)
	{
		throw new exception_framework("No reParse() for data type",0,get_class($this));
	}
	
	public function getAssembly($value)
	{
		throw new exception_framework("No getAssembly() for data type",0,get_class($this));		
	}
}
?>