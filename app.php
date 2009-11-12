<?php
$SAF_APPLICATION_PATH = dirname(__FILE__).'/';
$SAF_FRAMEWORK_PATH = $SAF_APPLICATION_PATH.'../../saf2/trunk/sapphire/';
if(!$SAF_SKIP_FRAMEWORK_LOAD)
{
	include $SAF_FRAMEWORK_PATH.'framework.php';
}
if(!class_exists('application_marketing',0))
	include $SAF_APPLICATION_PATH.'app_class.php';

$SAF_app_override = 'application_vcd';
include $SAF_FRAMEWORK_PATH.'loader.php';
?>