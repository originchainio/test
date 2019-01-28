<?php
// version: 20190115 test
include_once(__DIR__.'/class/base.php');
include __DIR__.'/lib/OriginSql.lib.php';
include __DIR__.'/class/MainSQLpdo.php';
class main extends base{

	function __construct(){
		parent::__construct();

	}

	public function index(){
		$sql=OriginSql::getInstance();
	    $res=$sql->select('config','val',1,array("cfg='sanity_last'"),'',1);

		if (!$res) {
			echo 'error';	exit;
		}

		// run sanity
		if (time() - $res['val'] > $this->config['sanity_interval']) {
		    system("php sanity.php  > /dev/null 2>&1  &");
		}
	}
}
date_default_timezone_set("UTC");
error_reporting(0);
ini_set('display_errors', "off");
$main=new main;
$main->index();

?>