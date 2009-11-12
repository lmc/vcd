<?php
class opcode
{
	const OPCODE_SIZE = 2;
	public $opcode = 0x0000;
	public $offset = -1;
	public $args = array();
	protected $scm = null;
	protected $NOT = false;
	protected $dis = false;
	
	public function __construct()
	{
		
	}
	
	public function newFromOffset($Copcode,$offset,&$scm)
	{
		if(strlen($Copcode) != 2)
			throw new exception_framework("Opcode not 2 bytes",0,$Copcode);
		$last  = substr($Copcode,0,1);
		$first = substr($Copcode,1,1);
		
		#The NOT operator sets the most significant bit to 1
		#TODO: Double-check that's only unsetting the most-significant bit
		if(($first & hex2raw('80')) == hex2raw('80'))
		{
			$this->NOT = true;
			$first = $first & hex2raw('0F');
		}
		$this->opcode = $first.$last;
		$this->offset = $offset;
		$this->scm = &$scm;
		
		$this->parseArgsFromOffset($offset);
		
	}
	
	public function newFromDis($line,&$scm)
	{
		$this->dis = true;
		$this->scm = $scm;
		$segs = explode(' ',trim($line));
		$this->opcode = array_shift($segs);
		if($this->opcode[0] == '8')
		{
			$this->NOT = true;
		}
		$this->parseArgsFromDis($segs);
	}
	
	/*
	 * Human-readable, properly-formatted opcode
	 * For internal use only
	 */
	protected function opcode()
	{
		return strtoupper(bin2hex($this->opcode));
	}
	
	/*
	 * Human-readable, properly formatted opcode
	 * Distinct from opcode() in that it detected this->NOT
	 */
	public function getOpcode()
	{
		if($this->dis)
			return $this->opcode;
		$opcode = $this->opcode();
		if($this->NOT)
		{
			$opcode[0] = '8';
		}
		return $opcode;
	}
	
	public function parseArgsFromOffset($offset)
	{
		$data = $this->scm->getOpcodeData($this->opcode());
		if(!$data)
			die('No opcode data for '.$this->opcode().' at '.$offset);
		$offset += self::OPCODE_SIZE;	#Skip past the actual opcode
		if($data['args'])
		{
			foreach($data['args'] as $argData)
			{
				//list($dType,$name) = $arg;
				$arg = new opcodeArg;
				$arg->newFromOffset($offset,$this,$this->scm,$argData);
				$offset += $arg->getSize();
				$this->args[] = $arg;
			}			
		}
	}
	
	public function parseArgsFromDis($args)
	{
		$argData = $this->scm->getOpcodeData($this->getDataOpcode());
		if(count($args) != count($argData['args']))
			throw new exception_framework("Arg count mismatch (found ".count($args).", expecting ".count($argData['args']).')',0,$this->getOpcode());
		foreach($args as $argId => $value)
		{
			if($args == '')
				continue;
			$arg = new opcodeArg;
			$arg->newFromDis($value,$argId,$this,$this->scm,$argData[$argId]);
			$this->args[] = $arg;
			app()->cout('%sParsed %s, (%s - %s)',
				TAB,$value,$arg->getDataType(),$arg->getSize());
		}
	}
	
	public function getDataOpcode()
	{
		$opcode = $this->getOpcode();
		if($opcode[0] == '8')
			$opcode[0] = '0';
		return $opcode;
	}
	
	public function getOffset()
	{
		return $this->offset;
	}
	
	public function getEnd()
	{
		return $this->getOffset() + $this->getSize();
	}
	
	public function getArgCount()
	{
		return count($this->args);
	}
	
	public function getArgs()
	{
		return $this->args;
	}
	
	public function getRawArg($arg = 0)
	{
		return $this->args[$arg];
	}
	
	public function getArg($arg = 0)
	{
		return $this->args[$arg]->getValue();
	}
	
	public function getSize()
	{
		$size = self::OPCODE_SIZE;
		#NOTE: 004F and 0913 take a variable amount of args.
		#Terminated with \0 immeadiately after last arg value
		if($this->getOpcode() == '004F')
			$size++;
		foreach($this->args as $arg)
			$size += $arg->getSize();
		return $size;
	}
	
	public function getAssembly(&$offset,&$scm)
	{
		$data = $this->getOpcode();
		$data = hex2raw($data);
		$data = flipEnd($data);
		$offset += self::OPCODE_SIZE;
		
		foreach($this->args as $argId => $arg)
		{
			$data .= $arg->getAssembly($offset,$scm);
		}
		if($this->getOpcode() == '004F')
		{
			$data .= "\0";
			$offset++;
		}
		
		return $data;
	}
}
?>