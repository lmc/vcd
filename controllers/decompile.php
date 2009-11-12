<?php
class controller_decompile extends controller_base
{
	public $function_index = array
	(
		
	);
	public function index()
	{
		$file = app()->config('paths.scm');
		$this->from_file($file);
	}
	
	public $function_to_file = array
	(
		
	);
	public function to_file($toFile)
	{
		$file = app()->config('paths.scm');
		$this->from_file($file,$toFile);
	}
	
	public $function_from_file = array
	(
		
	);
	public function from_file($path,$toPath = '')
	{
		$data = file_get_contents($path);
		app()->cout("Opened %s (%s bytes)",$path,strlen($data));
		$this->scm = new scm($data);
		$this->scm->decompile();
		
		$dis = $this->scm->getDisassembly();
		if($toPath)
			file_put_contents($toPath,$dis);
		else
			print($dis);
		#print_r($this->scm->labelUse);
		$GLOBALS['_SCM'] = $this->scm;
	}
}
?>