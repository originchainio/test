<?php
// version: 20190131
include __DIR__.'/class/base.php';
include __DIR__.'/include/account.inc.php';
include __DIR__.'/include/blacklist.inc.php';
include __DIR__.'/include/block.inc.php';
include __DIR__.'/include/config.inc.php';
include __DIR__.'/include/masternode.inc.php';
include __DIR__.'/include/mempool.inc.php';
include __DIR__.'/include/peer.inc.php';
include __DIR__.'/include/transaction.inc.php';
// include __DIR__.'/include/propagate.inc.php';
include __DIR__.'/class/MainSQLpdo.php';
include __DIR__.'/class/cache.php';
include __DIR__.'/lib/OriginSql.lib.php';
// include __DIR__.'/lib/PostThreads.lib.php';
include __DIR__.'/lib/Security.lib.php';
include __DIR__.'/function/function.php';
include __DIR__.'/function/core.php';

class explorer extends base{

	function __construct()
	{
		parent::__construct();
	}

	public function index($page=1){
		if ($page==='') {
			$page=1;
		}
		$block=Blockinc::getInstance();
		$current=$block->current();

		$all_page_number=ceil($current['height']/20);

		if ($page>$all_page_number) {
			$page=$all_page_number;
		}
		if ($page<1) {
			$page=1;
		}
		//

		$start_number=($all_page_number-$page)*20;
		//
		$sql=OriginSql::getInstance();
		$b=$sql->select('block','*',0,array("height>=".$start_number,"height<".($start_number+20)),'height DESC',20);

		if ($b==false) {
			echo 'error';	exit;
		}
		$prv_page=$page-1;	$next_page=$page+1;
		if ($prv_page<1) {
			$prv_page=0;
		}
		if ($next_page>$all_page_number) {
			$next_page=0;
		}
		//reward
		$reward_nofee=$block->reward_nofee($current['height']);

		////Performance problems
		// $explorer_all_reward=cache::get('explorer_all_reward');
		// if ($explorer_all_reward==false) {
		// 	cache::set('explorer_all_reward',array('height'=>1,'max_reward'=>'0','destroy'=>'0','real_reward'=>'0'),0);
		// }
		// if ($explorer_all_reward['height']<$current['height']) {
		// 	for ($i=$explorer_all_reward['height']+1; $i <= $current['height']; $i++) { 
		// 		$caches_re=$block->reward_nofee($explorer_all_reward['height']+1);
		// 		$explorer_all_reward['height']=$explorer_all_reward['height']+1;

		// 		$explorer_all_reward['max_reward']=bcadd($explorer_all_reward['max_reward'],$caches_re['max_reward'],8);

		// 		$explorer_all_reward['destroy']=bcadd($explorer_all_reward['destroy'],$caches_re['destroy_reward'],8);

		// 		$a=bcadd($explorer_all_reward['real_reward'],$caches_re['mn_reward'],8);
		// 		$b=bcadd($a,$caches_re['miner_reward'],8);
		// 		$explorer_all_reward['real_reward']=$b;
		// 	}
		// 	cache::set('explorer_all_reward',$explorer_all_reward,0);
		// }


		include $this->echo_display('explorer_index');
	}
	public function block($block_id){
		$sql=OriginSql::getInstance();
		$acc=Accountinc::getInstance();
		$Blockinc=Blockinc::getInstance();

		$b=$sql->select('block','*',1,array("id='".$block_id."'"),'',1);
		if ($b==false) {
			echo 'error';	exit;
		}
		$trx_list=$sql->select('trx','*',0,array("block='".$block_id."'"),'',0);
		if (count($trx_list)>0) {
			foreach ($trx_list as $key => $value) {
				$trx_list[$key]['from_address']=$acc->get_address_from_public_key($value['public_key']);
			}
		}

		$reward_nofee=$Blockinc->reward_nofee($b['height']);
		include $this->echo_display('explorer_block');
	}
	// public function transaction_list($block_id){
	// 	include $this->echo_display('explorer_transaction_list');
	// }
	public function transaction($trx_id){
		$sql=OriginSql::getInstance();
		$acc=Accountinc::getInstance();
		$b=$sql->select('trx','*',1,array("id='".$trx_id."'"),'',1);
		if ($b==false) {
			echo 'error';	exit;
		}
		$b['from_address']=$acc->get_address_from_public_key($b['public_key']);

		include $this->echo_display('explorer_transaction');
	}
	public function address($address){
		$sql=OriginSql::getInstance();
		$b=$sql->select('acc','*',1,array("id='".$address."'"),'',1);
		if ($b==false) {
			$b=$sql->select('acc','*',1,array("alias='".$address."'"),'',1);
		}
		if ($b==false) {
			$b=$sql->select('acc','*',1,array("public_key='".$address."'"),'',1);
		}
		if ($b==false) {
			echo 'error';	exit;
		}

		include $this->echo_display('explorer_address');
	}
    private function echo_display($name)
    {

        return __DIR__ . "/templets/" . $name . ".html";

    }
}
date_default_timezone_set("UTC");
$explorer=new explorer;
if (!isset($_GET['q'])) {	$q='';	}else{
	$q = trim($_GET['q']);
}

switch ($q) {
	case 'block':
		if (!isset($_GET['data'])) {	exit;	}
		$data = trim($_GET['data']);
		$explorer->block($data);
		break;
	case 'transaction':
		if (!isset($_GET['data'])) {	exit;	}
		$data = trim($_GET['data']);
		$explorer->transaction($data);
		break;
	case 'address':
		if (!isset($_GET['data'])) {	exit;	}
		$data = trim($_GET['data']);
		$explorer->address($data);
		break;
	// case 'transaction_list':
	//  if (!isset($_GET['data'])) {	exit;	}
	// 	$data = trim($_GET['data']);
	// 	$explorer->transaction_list($data);
	// 	break;
	default:
		if (!isset($_GET['page'])) {
	    	$page=1;
		}else{
			$page = trim($_GET['page']);
		}
		
		$explorer->index($page);
		break;
}
?>