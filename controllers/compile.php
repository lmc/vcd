<?php
class controller_compile extends controller_base
{
	public $function_index = array
	(
		
	);
	public function index()
	{
		$path = app()->config('paths.app');
		$path .= 'dis.txt';
		$this->from_file($path);
	}
	
	public function from_file($file)
	{
		$data = file_get_contents($file);
		$this->scm = new scm;
		$this->scm->parseDisassembly($data);
		$this->scm->getAssembly();
	}
	
	public $function_and_copy = array();
	public function and_copy()
	{
		$this->index();
		copy('main.scm',app()->config('paths.scm'));
	}
}
?>