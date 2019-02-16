<?php
// version: 20190215 test
include __DIR__.'/class/base.php';
include __DIR__.'/lib/OriginSql.lib.php';
include __DIR__.'/class/cache.php';
include __DIR__.'/class/MainSQLpdo.php';
class index extends base{
	function __construct(){
		parent::__construct();
	}
	public function index(){
		$sql=OriginSql::getInstance();

		$res=$sql->select('blocks','*',1,'','height DESC',1);
		$height=$res['height'];
		$sync_synchronization_time=cache::get('sync_synchronization_time');
		$sync_last_time=cache::get('sync_last_time');

		include $this->echo_display('index');


	}
    private function echo_display($name)
    {

        return __DIR__ . "/templets/" . $name . ".html";

    }
}
date_default_timezone_set("UTC");
// error_reporting(0);
// ini_set('display_errors', "off");
$index=new index;
$index->index();
?>