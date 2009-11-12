<?php
class opcodeArg
{
	public $size = 0;
	public $dType = 0;
	public $value = null;
	public $display = null;
	
	public function __construct()
	{
		
	}
	
	public function newFromOffset($offset,&$opcode = null,&$scm = null,$argData = null)
	{
		$this->dType = $scm->getRawData($offset,1);
		$this->dType = raw2dec($this->dType);
		$classname = scm::getClassFromDType($this->dType);
		
		if($classname == 'dInt32' && $argData[0] == 'offset')
			$classname = 'dOffset';
		
		$this->type = new $classname;
		
		$argSize = $this->type->size;
		$extra = $this->getArgPadding();
		$this->value = substr($scm->scm,$offset + $extra,$argSize);
		
		if($this->type->type != 'string')
			$this->value = flipEnd($this->value,$offset);
			
		if($classname == 'dOffset')
			$this->display = $scm->registerOffset($this->getValue());
			
		if($this->type->type == 'var')
			$this->display = $scm->registerVar(
				$offset,$this->getValue(),get_class($this->type) == 'dLVar');
		
	}
	
	public function newFromDis($arg,$argId,&$opcode,&$scm,$argData)
	{
		$classname = $scm->getDataTypeForArg($opcode->getOpcode(),$argId,$arg);
		if(!$classname)
			throw new exception_framework("Can't get data type for arg",0,$arg);
		$classname = 'd'.$classname;
		$this->type = new $classname;
		if(get_class($this->type) == 'dGVar')
			$scm->registerDisVar($arg);
		$this->value = $arg;
		$this->value = $this->type->reParse($this->value);
	}
	
	public function getSize()
	{
		$extra = $this->getArgPadding();
		return $this->type->size + $extra;	#+1 for data type id
	}
	
	public function getArgPadding()
	{
		if($this->type->longName() == 'MString8')
			return 0;
		else
			return 1;
	}
	
	public function getDType()
	{
		return $this->dType;
	}
	
	public function getDataType()
	{
		return $this->type->longName();
	}
	
	public function getValue()
	{
		return $this->type->formatValue($this->value);
	}
	
	public function getNiceValue()
	{
		if($this->display === false)
			return $this->type->formatDisplay($this->value);
		elseif($this->display)
			return $this->type->formatDisplay($this->display);
		return $this->getValue();
	}
	
	public function getAssembly(&$offset,&$scm)
	{
		$data = '';
		if($this->type->longName() != 'MString8')
		{
			$type .= scm::getDTypeForClass($this->type);
			$data .= pack('c',$type);
			$offset += 1;
		}
		$value = $this->type->getAssembly($this->value,$offset,$scm);
		$data .= $value;
		$offset += strlen($value);
		
		return $data;
	}
}
?>