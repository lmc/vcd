<?php
class controller_profile extends controller_base
{
	public $function_opcode = array
	(
		
	);
	public function opcode($inOpcode)
	{
		$scm = $GLOBALS['_SCM'];
		app()->cout('Profiling %s',$inOpcode);
		$found = 0;
		$argTypes = array();
		$argValues = array();
		foreach($scm->structure as $offset => $opcode)
		{
			if($opcode->getOpcode() == $inOpcode)
			{
				foreach($opcode->getArgs() as $argId => $arg)
				{
					$argTypes[$argId][$arg->getDataType()]++;
					$argValues[$argId][$arg->getNiceValue()]++;
				}
				$found++;
			}
		}
		app()->cout('Found %s uses',$found);
		print_r($argTypes);
		print_r($argValues);
	}
}
?>