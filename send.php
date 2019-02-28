<?php
// version: 20190227
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
class send extends base{
	private static $_instance = null;
	function __construct(){
        if ($this->info['cli'] != true) {
            echo "\nneed to run cli modle";
            exit;
        }
		parent::__construct();
	}
    public static function getInstance(){
        if(self::$_instance === null)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function block($id='current',$to_hostname='all',$linear='true'){
    	if ($id==='') {
    		$id='current';
    	}
    	if ($to_hostname==='') {
    		$to_hostname='all';
    	}
    	if ($linear==='') {
    		$linear='true';
    	}
    	$sql=OriginSql::getInstance();
    	$r=[];
    	if ($to_hostname=='all' or $to_hostname=='') {
	    	if ($linear=='true') {
	    		$orderby='RAND()';
	    		$limit=intval($this->config['block_propagation_peers']);
	    	}else{
	    		$orderby='';
	    		$limit=0;	
	    	}

	    	$r=$sql->select('peer','hostname',0,array("blacklisted<".time()),$orderby,$limit);
    	}else{
    		$r[]=array('hostname'=>$to_hostname);
    	}
    	//id
    	$block=Blockinc::getInstance();

    	if ($id=='current') {
    		$current = $block->current();
    		$data = $block->export_for_other_peers($current['id']);
    	}else{
    		$data = $block->export_for_other_peers($id);
    	}
    	if (!$data) {
    		$this->log('send->block data false',0,true);
    		exit;	
    	}
    	//send
    	foreach ($r as $key => $value) {
    		//echo "Block sent to ".$value['hostname']."\n";
		    if ($this->config['local_node']==false) {
		        $data['from_host']=$this->config['hostname'];
		    }
		    // echo_array($data);
		    // echo_array($this->peer_post($value['hostname']."/peer.php?q=submitBlock", json_encode($data)));
		    $this->peer_post($value['hostname']."/receive.php?q=submitBlock", json_encode($data));
    	}
    	//
    	return true;
    }

    public function transaction($mem_id){
    	$sql=OriginSql::getInstance();
    	$mem = Mempoolinc::getInstance();
    	//select
    	$data = $mem->get_mempool_from_id($mem_id);
	    if (!$data) {
	    	$this->log('send->transaction data false',0,true);
	        //echo "Invalid transaction id\n";
	        exit;
	    }
    	if ($data['peer'] == "local") {
    		$data['peer']=$this->config['hostname'];
    		$orderby='';
    		$limit=0;
    	}else{
    		$orderby='RAND()';
    		$limit=intval($this->config['transaction_propagation_peers']);	
    	}

    	$r=$sql->select('peer','hostname',0,array("blacklisted<".time()),$orderby,$limit);

	    foreach ($r as $key => $value) {
	    	//echo "Transaction sent to ".$value['hostname']."\n";
	    	$this->peer_post($value['hostname']."/receive.php?q=submitTransaction", json_encode($data));
	    }
	    return true;
    }
	private function peer_post($url, $json_post_data, $timeout = 60){
	        $postdata = http_build_query(
	            [
	                'data' => $json_post_data,
	                "coin" => 'origin',
	            ]
	        );

	        $opts = [
	            'http' =>
	                [
	                    'timeout' => $timeout,
	                    'method'  => 'POST',
	                    'header'  => 'Content-type: application/x-www-form-urlencoded',
	                    'content' => $postdata,
	                ],
	        ];

	        $context = stream_context_create($opts);
	        $result = file_get_contents($url, false, $context);
	        $res = json_decode($result, true);

	        // the function will return false if something goes wrong
	        if ($res['status'] == "ok" || $res['coin'] == 'origin') {
	            return $res['data'];
	        }else{
	        	$this->log('send->peer_post false',0,true);
	            return false;
	        }  
	}



}
date_default_timezone_set("UTC");
if (!isset($argv[1])) {
	exit;
}
$q = trim($argv[1]);
$send=new send;
switch ($q) {
	case 'block':
		if (isset($argv[2])) {
			$id=$argv[2];
		}else{
			$id='';
		}
		if (isset($argv[3])) {
			$to_hostname=$argv[3];
		}else{
			$to_hostname='';
		}
		if (isset($argv[4])) {
			$linear=$argv[4];
		}else{
			$linear='';
		}
		$send->block($id,$to_hostname,$linear);
		break;
	case 'transaction':
		if (isset($argv[2])) {
			$id=$argv[2];
		}else{
			exit;
		}
		$send->transaction($id);
		break;
	
	default:
		# code...
		break;
}
?>