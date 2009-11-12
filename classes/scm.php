<?php
class scm
{
	public $memoryJumpOpcode = null;
	public $memoryOffset = -1;
	public $memorySize = 0;
	
	public $objectJumpOpcode = null;
	public $objectOffset = -1;
	public $objectCount = 0;
	public $objectSize = 0;
	public $objects = array();
	const OBJECT_HEADER_SIZE = 24;	#Model names are 24 bytes
	
	public $missionJumpOpcode = null;
	public $missionOffset = -1;
	public $missionCount = 0;
	public $missionSize = 0;
	public $missions = array();
	const MISSION_HEADER_SIZE = 4;	#Mission offsets are 32-bit addresses
	
	public $mainOffset = -1;
	
	public $scm;					#SCM data
	
	public $structure = array();	#Native data structures (offset => opcode object)
	
	public $labelMap = array();		#Hash of calling opcodes to destination offset
	public $labelUse = array();		#Hash of referenced labels
	public $labelId = 1;
	
	public $rLabelMap = array();	#Reverse of above, labels => offset
	public $rVarAllocated = -1;
	public $rVarMap = array();		#Hash of variables to their memory offsets
	const GVAR_SIZE = 4;			#Size of a single allocation for global vars
	
	public $reOffsetMap = array();	#Map of label names to places they need to be updated 
	public $reVarMap = array();		#Map of var names to places they need to be updated 
	
	public $globalVars = array();	#Var name => memory offset
	protected $globalVarId = 1;		#Current auto-increment var ID
	
	public $opcodes = array();		#Opcode data (0002 => array(name => ....))
	
	static $dType2class = array
	(
		1	=>	'dInt32',
		2	=>	'dGVar',
		3	=>	'dLVar',
		4	=>	'dInt8',
		5	=>	'dInt16',
		6	=>	'dFloat32',
		7	=>	'dGVarArray',
		8	=>	'dLVarArray',
		9	=>	'dString8',
		10	=>	'dGString8',
		11	=>	'dLString8',
		12	=>	'dGString8Array',
		13	=>	'dLString8Array',
		14	=>	'dStringVl',
		15	=>	'dString16',
		16	=>	'dGStringVl',
		17	=>	'dLStringVl',
		18	=>	'dGStringVlArray',
		19	=>	'dLStringVlArray',
		900	=>	'dMString8'			#Magic string for 03A4 and others (no internal data type)
	);

	public function __construct($scm = null)
	{
		$this->scm = $scm;
		
		$opcodeFile = app()->config('paths.app').'data'.DS.'opcodes.yml';
		$parser = new yaml;
		$this->opcodes = $parser->read($opcodeFile);
		
		$ini = file_get_contents(app()->config('paths.app').'data'.DS.'VICESCM.ini');
		$lines = explode(BR,$ini);
		foreach($lines as $line)
		{
			if($line[0] == ';') continue;
			if(trim($line) == '[variables]') break;
			list($opcodeData,$help) = explode(',',$line);
			list($opcode,$argCount) = explode('=',$opcodeData);
			$opcode = strtoupper($opcode);
			if(!$opcode) continue;
			#if($argCount < 1)
			if(!$this->opcodes[$opcode])
			{
				$this->opcodes[$opcode] = array('name' => '_nodata_');
				if($argCount)
					$this->opcodes[$opcode]['args'] = array_fill(0,$argCount,array());
			}
		}
	}
	
	public function decompile()
	{
		$this->processHeaders();
	}
	
	#NOTE: From != For!
	public static function getClassForDType($dType)
	{
		$classname = self::$dType2class[$dType];
		if(!$classname)
			throw new exception_framework("Unknown data type",0,$dType);
		return new $classname;
	}
	
	public static function getDTypeForClass($class)
	{
		if(is_object($class))
			$class = get_class($class);
		if($class == 'dOffset')
			$class = 'dInt32';
		foreach(self::$dType2class as $dType => $cls)
			if($cls == $class)
				return $dType;
		return -1;
	}
	
	public function processHeaders()
	{
		app()->captureCout('processHeaders');
		#Start from offset 0, read jump opcode which tells how far to jump forward
		#Space between the jump opcode and the jump location is empty space for var storage
		$jump = $this->getOpcode(0);
		$offset = $jump->getArg();
		app()->cout('Offset at %s',$offset);
		$this->memoryOffset = $jump->getSize();
		$this->memorySize = $offset - $jump->getSize();
		$this->memoryJumpOpcode = $jump;
		app()->cout("Variable storage found, %s bytes at %s",
			$this->memorySize,$this->memoryOffset);
		
		#Process jump opcode
		$this->objectOffset = $offset;
		$jump = $this->getOpcode($offset);
		
		#Jump past opcode....
		$offset += $jump->getSize();
		#Get section ID (blank?)
		$object_section = $this->getRawData($offset,1);
		$offset += 1;
		
		#Get object count
		$object_count = $this->getRawData($offset,4);
		$object_count = flipEnd($object_count);
		$object_count = raw2dec($object_count);
		$offset += 4;
		
		app()->cout("Objects found, %s total",$object_count);
		#Objects are 24 bytes, and hold a negative ID
		for($object = 1; $object <= $object_count; $object++)
		{
			$name = $this->getRawData($offset,self::OBJECT_HEADER_SIZE);
			$this->objects[] = $name;
			app()->cout('%sObject %s: %s at %s',TAB,-$object,$name,$offset);
			$offset += self::OBJECT_HEADER_SIZE;
		}
		#Then jump to missions
		$offset = $jump->getArg();
		
		
		#Get mission jump opcode
		$this->missionOffset = $offset;
		$this->objectJumpOpcode = $jump;
		$jump = $this->getOpcode($offset);
		
		#Jump past THAT
		$offset += $jump->getSize();
		
		#Get/skip the mission section id
		$mission_section = $this->getRawData($offset,1);
		$offset += 1;
		
		$main_offset = $jump->getArg();
		for($i = $main_offset - self::MISSION_HEADER_SIZE;
			$i >= $offset; $i -= self::MISSION_HEADER_SIZE)
		{
			$address = $this->getRawData($i,self::MISSION_HEADER_SIZE);
			$address = flipEnd($address);
			$address = raw2hex($address);
			$address = hexdec($address);
			
			#If mission offset is before main thread, it can't be legit
			if($address < $main_offset)
			{
				continue;
			}
			
			#Null pointer
			if(!$address)
			{
				continue;
			}
			
			#Already defined? (wtf)
			if(in_array($address,$this->missions))
			{
				continue;
			}
			
			$address = $this->registerOffset($address);
			$this->missions[] = $address;
		}
		$this->missions = array_reverse($this->missions);
		app()->cout('Missions found, %s total',count($this->missions));
		foreach($this->missions as $id => $address)
			app()->cout('%sMission %s: %s (%s)',
				TAB,$id,$address,$this->tryToGetThreadNameForOffset($address));
			
		$offset = $main_offset;
		$this->mainOffset = $offset;
		$this->missionJumpOpcode = $jump;
		$end = $this->missions[0] ? $this->missions[0] : strlen($this->scm);
		app()->cout('Main thread begins at %s, ends at %s, size %s',
			$offset,$end,$end - $offset);
			
		#Process main thread
		while($offset < strlen($this->scm))
		{
			$opcode = $this->getOpcode($offset);
			app()->cout('Found opcode %s at %s',$opcode->getOpcode(),$offset);
			app()->cout('%s Size: %s',TAB,$opcode->getSize());
			foreach($opcode->getArgs() as $arg)
			{
				app()->cout('%s Arg: %s (%s - %s)'
					,TAB.TAB,$arg->getValue(),$arg->getDataType(),
					$arg->getDType());
			}
			$this->structure[$offset] = $opcode;
			$offset += $opcode->getSize();
			
			#Hacky stopword
			#if($opcode->getOpcode() == '03A4' && $arg->getValue() == '"AMBBANK"')
			#	break;
		}
		
		$log = app()->captureCout();
		$logFile = app()->config('paths.app').'dissassembly.log';
		file_put_contents($logFile,$log);
	}
	
	const PARSE_STATE_MEMORY = 1;
	const PARSE_STATE_OBJECTS = 2;
	const PARSE_STATE_MISSIONS = 3;
	const PARSE_STATE_MAIN = 4;
	const PARSE_STATE_LABEL = 5;
	public function parseDisassembly($disassembly)
	{
		app()->captureCout('parseDissembly');
		$lines = explode(BR,$disassembly);
		$state = 0;
		$lastLabel = -1;
		$offset = 0;
		foreach($lines as $lineNo => $line)
		{
			$lineNo += 1;
			#app()->cout('State %s, %s',$state,$lineNo);
			#Handler for comments and labels
			switch($line[0])
			{
				case '#':	#Comment
					continue 2;
				case '':	#Blank line
					continue 2;
				case ':':	#Label
					$lastLabel = substr($line,1);
					app()->cout('Label %s at %s',$lastLabel,$offset);
					$this->registerLabel($lastLabel,$offset);
					continue 2;
			}
			
			switch($state)
			{
				case self::PARSE_STATE_OBJECTS:
					if($line[0] != TAB)
					{
						$state = self::PARSE_STATE_MAIN;
						break;
					}
					$object = trim($line,"\t\"");
					app()->cout('%sObject %s at %s',
						TAB,$object,$offset);
					$this->objectSize += self::OBJECT_HEADER_SIZE;
					$this->objects[] = $object;
					$offset += self::OBJECT_HEADER_SIZE;
					continue 2;
				break;
				case self::PARSE_STATE_MISSIONS:
					if($line[0] != TAB)
					{
						$state = self::PARSE_STATE_MAIN;
						break;
					}
					$mission = trim($line,"\t\"");
					app()->cout('%sMission %s at %s',
						TAB,$mission,$offset);
					$this->missions[] = $mission;
					$this->missionSize += self::MISSION_HEADER_SIZE;
					$offset += self::MISSION_HEADER_SIZE;
					continue 2;
				break;
			}
			
			list($opcode,$args) = explode(' ',$line,2);
			#Hook to change state machine			
			switch($opcode)
			{
				case 'MEMORY':
					$state = self::PARSE_STATE_MEMORY;
					app()->cout('Memory found, %s bytes at line %s',$args,$lineNo);
					$this->memoryOffset = $offset;
					$this->memorySize = $args;
					$offset += $args;	#Memory size
					#$offset += 1;		#Padding
					continue 2;
				case 'OBJECTS':
					$state = self::PARSE_STATE_OBJECTS;
					app()->cout('Objects found at line %s',$lineNo);
					$this->objectOffset = $offset;
					$this->objectSize = 0;
					$offset += 1;	#Segment ID
					$offset += 4;	#object count (int32)
					continue 2;
				case 'MISSIONS':
					$state = self::PARSE_STATE_MISSIONS;
					app()->cout('Missions found at line %s',$lineNo);
					$this->missionOffset = $offset;
					$this->missionCount = 0;
					$offset += 1;	#Segment ID
					$offset += 8;	#Mysterious padding data
					$offset += 4;	#mission count (int32)
					continue 2;
			}
			
			$args = explode(' ',$args);
			
			$opcode = new opcode;
			$opcode->newFromDis($line,$this);
			
			app()->cout('Opcode %s put at offset %s, size: %s',
				$opcode->getOpcode(),$offset,$opcode->getSize());
			
			$this->structure[$offset] = $opcode;
			$offset += $opcode->getSize();
			$lastLabel = -1;
		}
		
		app()->cout('%sLabel map:%s%s',
			BR,BR,print_r($this->rLabelMap,1));
		
		app()->cout('%sGVar map: (%s allocated, %s bytes)%s',
			BR,count($this->rVarMap),count($this->rVarMap) * 4,
			print_r($this->rVarMap,1));
		
		$log = app()->captureCout();
		$path = app()->config('paths.app').'assembly.log';
		file_put_contents($path,$log);
	}
	
	public function getDisassembly()
	{
		#Expected vars
		#if(!$this->memoryJumpOpcode)
		#	$this->memoryJumpOpcode = $this->structure[0];
		#if(!$this->objectJumpOpcode)
		#	$this->objectJumpOpcode = $this->structure
		#	[$this->memoryJumpOpcode->getSize() + $this->memorySize];
		
		$str = $this->memoryJumpOpcode->getOpcode().' '.$this->memoryJumpOpcode->getRawArg()->getNiceValue().BR;
		$str .= 'MEMORY '.$this->memorySize.BR.BR;
		$str .= ':'.$this->labelForOffset($this->objectOffset).BR;
		$str .= $this->objectJumpOpcode->getOpcode().' '.$this->objectJumpOpcode->getRawArg()->getNiceValue().BR;
		$str .= 'OBJECTS'.BR;
		foreach($this->objects as $object)
			$str .= TAB.dMString8::formatValue($object).BR;
		$str .= BR.':'.$this->labelForOffset($this->missionOffset).BR;
		$str .= $this->missionJumpOpcode->getOpcode().' '.$this->missionJumpOpcode->getRawArg()->getNiceValue().BR;
		$str .= 'MISSIONS'.BR;
		foreach($this->missions as $address)
			$str .= TAB.':'.$this->labelForOffset($address).BR;
		foreach($this->structure as $offset => $opcode)
		{
			$label = $this->labelForOffset($offset);
			if($label)
				$str .= BR.':'.$label.BR;
			$str .= $opcode->getOpcode();
			$str .= ' ';
			foreach($opcode->getArgs() as $argId => $arg)
				$str .= $arg->getNiceValue().($argId == $opcode->getArgCount() - 1 ? '' : ' ');
			$str .= BR;
		}
		
		return $str;
	}
	
	public function getAssembly()
	{
		app()->captureCout('getAssembly');
		$out = fopen('main.scm','wb');
		
		$offset = 0;
		for($i = 0; true; $i++)
		{
			app()->cout('At %s',$offset);
			#Memory
			if($offset == $this->memoryOffset)
			{
				app()->cout('%sMemory at %s, wrote %s bytes',
					TAB,$offset,$this->memorySize);
				$write = str_repeat("\0",$this->memorySize);	#+1 for padding
				$offset += $this->memorySize;
			}
			#Objects
			elseif($offset == $this->objectOffset)
			{
				app()->cout('%sObjects at %s, count %s',
					TAB,$offset,count($this->objects));
				$write = "\0";
				$write .= pack('l',count($this->objects));
				$offset += strlen($write);
				foreach($this->objects as $object)
					$write .= str_pad($object,self::OBJECT_HEADER_SIZE,"\0");
				$offset += count($this->objects) * self::OBJECT_HEADER_SIZE;
			}
			#Missions
			elseif($offset == $this->missionOffset)
			{
				app()->cout('%sMissions at %s, count %s',
					TAB,$offset,count($this->missions));
				$write = str_repeat("\0",1 + 8);	#1 for padding, 8 for mystery data
				$write .= pack('l',count($this->missions));
				$offset += strlen($write);
				foreach($this->missions as $mission)
					$write .= dOffset::getAssembly($mission,$offset,$this);
				$offset += count($this->missions) * self::MISSION_HEADER_SIZE;
			}
			#Normal opcodes
			else
			{
				$opcode = $this->structure[$offset];
				if(!$opcode)
				{
					app()->cout('No opcode at %s',$offset);
					app()->cout('Going to stop now.');
					break;
				}
				$write = $opcode->getAssembly($offset,$this);
				app()->cout('%sOpcode %s at %s, %s bytes%s',
					TAB,$opcode->getOpcode(),$offset - $opcode->getSize(),-1,BR);
			}
			
			app()->cHex($write);
			
			fwrite($out,$write);
		}
		
		foreach($this->reVarMap as $var => $offsets)
			foreach($offsets as $off)
				$this->writeRawData($out,$off,
					pack('s',$this->rVarMap[$var]));
		foreach($this->reOffsetMap as $var => $offsets)
			foreach($offsets as $off)
				$this->writeRawData($out,$off,
					pack('l',$this->rLabelMap[substr($var,1)]));
		#print_r($this->rVarMap);
		#print_r($this->reVarMap);
		#print_r($this->rLabelMap);
		#print_r($this->reOffsetMap);
		
	}
	
	public function writeRawData($handle,$offset,$data)
	{
		app()->cHex($data);
		app()->cout(' to %s',$offset);
		fseek($handle,$offset);
		fwrite($handle,$data);
	}
	
	public function getOpcode($offset)
	{
		$Copcode = substr($this->scm,$offset,2);
		$opcode = new opcode;
		$opcode->newFromOffset($Copcode,$offset,$this);
		return $opcode;
	}
	
	public function getOpcodeData($opcode)
	{
		return $this->opcodes[$opcode];
	}
	
	public function getRawData($offset,$length = 1)
	{
		return substr($this->scm,$offset,$length);
	}
	
	public function getClassFromDType($dType)
	{
		$return = self::$dType2class[$dType];
		if(!$return && $dType)
			$return = 'dMString8';
		return $return;
	}
	
	public function labelForOffset($offset)
	{
		if($this->labelUse[$offset])
			return $this->labelUse[$offset];
		if(in_array($offset,$this->missions))
			return $offset;
		if($offset == $this->objectOffset)
			return $offset;
		if($offset == $this->missionOffset)
			return $offset;
		return false;
	}
	
	public function registerLabel($label,$offset)
	{
		$this->rLabelMap[$label] = $offset;
	}
	
	public function registerDisVar($var)
	{
		if($this->rVarAllocated == -1)
			$this->rVarAllocated = $this->memoryOffset + 1;
		if(!$this->rVarMap[$var])
		{
			$allocated = $this->rVarMap[$var] = $this->rVarAllocated;
			$this->rVarAllocated += self::GVAR_SIZE;
		}
		#Interesting hack...
		if(!$allocated)
			$allocated = $this->rVarMap[$var];
		app()->cout('%sAllocated %s to %s',TAB,$var,$allocated);
		return $allocated;
	}
	
	public function registerOffset($offset,$fromOffset = -1)
	{
		$this->labelMap[$fromOffset] = $offset;
		if(!$this->labelUse[$offset])
			$this->labelUse[$offset] = $this->labelId++;
		return $this->labelUse[$offset];
	}
	
	public function registerOffsetRe($label,$offset)
	{
		if(!$this->reOffsetMap[$label])
			$this->reOffsetMap[$label] = array();
		$this->reOffsetMap[$label][] = $offset;
	}
	
	public function registerVarRe($var,$offset)
	{
		if(!$this->reVarMap[$var])
			$this->reVarMap[$var] = array();
		$this->reVarMap[$var][] = $offset;
	}
	
	public function registerVar($offset,$value,$local = false)
	{
		if($local)
			return false;
		if(!$this->globalVars[$value])
			$this->globalVars[$value] = $this->globalVarId++;
		app()->cout('%sVar %s found at %s',
			TAB,$this->globalVars[$value],$value);
		return $this->globalVars[$value];
	}
	
	public function tryToGetThreadNameForOffset($offset,$ahead = 1024)
	{
		$opcode = hex2raw('03A4');
		$opcode = flipEnd($opcode);
		if(($start = strpos($this->scm,$opcode,$offset)) === false)
		{
			return '_NoName_';
		}
		$name = $this->getOpcode($start);
		return $name->getArg();
	}
	
	public function getDataTypeForArg($opcode,$argId,$arg)
	{
		switch($arg[0])
		{
			case '$':
				if($arg[1] == '_')
					return 'GVar';
				else
					return 'LVar';
			break;
			case '@':
				return 'LVar';
			case ':':
				return 'Offset';
			break;
		}
		if(is_numeric($arg))
		{
			if(strpos($arg,'.') !== false)
			{
				return 'Float32';
			}
			else
			{
				if($arg >= 32768 || $arg < -32768)
					return 'Int32';
				elseif($arg >= 128 || $arg < -128)
					return 'Int16';
				else
					return 'Int8';
			}
		}
		elseif(strpos($arg,'"') !== false)
			return 'MString8';
	}
}
?>