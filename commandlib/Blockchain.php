<?php
/**
 * 
 */
// version: 20190225
class Blockchain extends base{
	private static $_instance = null;

	function __construct(){
		 parent::__construct();
	}
    public static function getInstance(){
        if(self::$_instance === null)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

	public function getbestblockhash($mode='all'){
	// Array
	// (
	//     [result] => 5H4r7QUZux....
	//     [error] =>
	// )
		return array('result' => cache::get('bestblockhash'), 'error'=>'');
	}

	public function getblock($mode='all',$blockhash){
	// Array
	// (
	//     [result] => Array
	//         (
	//             [id] => 2TZP5uoD9fovgNoxv...
	//             [generator] => 2j7bgeD9vZDsg....
	//             [height] => 499
	//             [date] => 1550483746
	//             [nonce] => 5jL9z4yHaOn8jeijv4j...
	//             [signature] => iKx1CJNN3veGyEcniuv7g395Pc...
	//             [difficulty] => 1360222505684
	//             [argon] => $MVdqS1dTRFVkZDZQdUlo...
	//             [transactions] => 1
	//         )
	//     [error] =>
	// )
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','*',1,array("id='".$blockhash."'"),'',1);
		if ($res) {
			return array('result' => $res, 'error'=>'');
		}else{
			return array('result' => '', 'error'=>'fail');
		}
	}

	public function getblockchaininfo($mode='all'){
	// Array
	// (
	//     [result] => Array
	//         (
	//             [id] => 3BXTRtucboPM...
	//             [generator] => 2j7bgeD9vZDsgG5z9vJEZ...
	//             [height] => 529
	//             [date] => 1550492210
	//             [nonce] => umMGjm601x...
	//             [signature] => AN1rKqnXTwfyywc5UZdM8n...
	//             [difficulty] => 1085944455421
	//             [argon] => $TlEzbk5ocnRoNkZ...
	//             [transactions] => 1
	//         )

	//     [error] =>
	// )
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','*',1,array(),'height DESC',1);
		if ($res) {
			return array('result' => $res, 'error'=>'');
		}else{
			return array('result' => '', 'error'=>'fail');
		}
	}	

	public function getblockcount($mode='all'){
	// Array
	// (
	//     [result] => 529
	//     [error] =>
	// )
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','height',1,array(),'height DESC',1);
		if ($res) {
			return array('result' => $res['height'], 'error'=>'');
		}else{
			return array('result' => '', 'error'=>'fail');
		}
	}	
	public function getblockhash($mode='all',$height){
	// Array
	// (
	//     [result] => 3eXGYv8J3fhfgcT1owH5aHgDZc...
	//     [error] =>
	// )
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','id',1,array("height=".$height),'',1);
		if ($res) {
			return array('result' => $res['id'], 'error'=>'');
		}else{
			return array('result' => '', 'error'=>'fail');
		}
	}
	public function getblockstats($mode='all',$hash_or_height){
	// Array
	// (
	//     [result] => Array
	//         (
	//             [avgfee] => 0
	//             [blockhash] => 3eXGYv8J3fhfgcT1owH5aHgDZcz...
	//             [height] => 50
	//             [maxfee] => 0
	//             [minfee] => 0
	//             [time] => 1550420005
	//             [total_out] => 0
	//             [totalfee] => 0
	//             [txs] => 1
	//         )

	//     [error] =>
	// )
		$sql=OriginSql::getInstance();
		if (is_numeric($hash_or_height)) {
			$res=$sql->select('blocks','*',1,array("height=".$hash_or_height),'',1);
		}else{
			$res=$sql->select('blocks','*',1,array("id=".$hash_or_height),'',1);
		}
		if (!$res) {
			return array('result' => '', 'error'=>'fail');
		}

		$trx_list=$sql->select('trx','val,fee,version',0,array("block='".$res['id']."'"),'',0);
		$trx_count=count($trx_list);

		$all_fee=0;
		$min_fee=0;
		$max_fee=0;
		$total_out=0;
		foreach ($trx_list as $value) {
			$all_fee=$all_fee+$value['fee'];
			if ($value['fee']<$min_fee) {
				$min_fee=$value['fee'];
			}
			if ($value['fee']>$max_fee) {
				$max_fee=$value['fee'];
			}
			if ($value['version']==1 or $value['version']==2) {
				$total_out=$total_out+$value['val'];
			}
		}
		$avgfee=round($all_fee/$trx_count,8);

		$arrayName = array(
		  "avgfee"=>$avgfee,
		  "blockhash"=>$res['id'],
		  "height"=>$res['height'],
		  "maxfee"=>$max_fee,
		  "minfee"=>$min_fee,
		  "time"=>$res['date'],
		  "total_out"=>$total_out,
		  "totalfee"=>$all_fee,
		  "txs"=>$trx_count,
		);
		return array('result' => $arrayName, 'error'=>'');
	}
	public function getchaintips($mode='all'){
	// Array
	// (
	//     [result] => Array
	//         (
	//             [0] => Array
	//                 (
	//                     [id] => 3BXTRtucboPMkb6RF...
	//                     [height] => 529
	//                     [status] => active
	//                 )

	//             [1] => Array
	//                 (
	//                 )

	//         )

	//     [error] =>
	// )
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','id,height',1,array(),'height DESC',1);
		$res['status']='active';

		$validfork=cache::get('validfork');
		if ($validfork==false) {
			$validfork=[];
		}
		return array('result' => array($res,$validfork), 'error'=>'');
	}

	public function getdifficulty($mode='all'){
	// Array
	// (
	//     [result] => 9223372036854775800
	//     [error] =>
	// )
		$block=Blockinc::getInstance();
		$res=$block->get_next_difficulty();
		if ($res) {
			return array('result' => $res, 'error'=>'');
		}else{
			return array('result' => '', 'error'=>'fail');
		}
	}

	public function getmempoolentry($mode='all',$txid){
	//Array
	//(
	//	[result] =>Array
	//         (
	//              [id] => 3BXTRtucboPMkb6RF...
	//              [height] => 
	//              [dst] => 
	//              [val] => 
	//              [fee] => 
	//            	[signature] => 
	//            	[version] => 
	//            	[message] => 
	//             	[date] => 
	//				[public_key] => 
	//				[peer] => 
	//         )
	//	[error] => 
	//)

		$sql=OriginSql::getInstance();
		$res=$sql->select('mem','*',1,array("id='".$txid."'"),'',1);
		if ($res) {
			return array('result' => $res, 'error'=>'');
		}else{
			return array('result' => '', 'error'=>'fail');
		}
	}

	public function getmempoolsize($mode='all'){
	// Array
	// (
	//     [result] => 33
	//     [error] => 
	// )

		$sql=OriginSql::getInstance();
		$res=$sql->select('mem','*',2,array(),'',0);

		if ($res===false) {
			return array('result' => '', 'error'=>'fail');
		}else{
			return array('result' => $res, 'error'=>'');
		}
	}

	public function getrawmempool($mode='all'){
	// Array
	// (
	//     [result] => Array
	//         (
	//         	3BXTRtucboPMkb6RF...,
	//         	asdw22ucboPMkb6RF...,
	//         	...
	//         )

	//     [error] =>
	// )
		$sql=OriginSql::getInstance();
		$res=$sql->select('mem','id',0,array(),'',0);
		if ($res===false) {
			return array('result' => '', 'error'=>'fail');
		}else{
			return array('result' => $res, 'error'=>'');
		}
	}

	public function gettxout($mode='all',$txid){
		return $this->getmempoolentry('all',$txid);
	}

	//**********This function finds a bug and waits to be fixed ,Tester: Luuhoo
	public function verifychain($mode='cli',$start_height,$end_height){
	// error: Array
	// (
	//     [result] => Array
	//         (
	//             [status] =>
	//             [error_result] => Array
	//                 (
	//                     [height] => Array
	//                         (
	//                             [0] => 1
	//                         )

	//                     [id] => Array
	//                         (
	//                             [0] => 5mEVo2RLoEZt4Zn...
	//                         )

	//                 )

	//         )

	//     [error] =>
	// )

		if ($mode!=='cli') {
			return array('result' => '', 'error'=>'fail');
		}
		$return_array = array();

        $start_height = intval($start_height);
        $end_height = intval($end_height);
        if ($start_height<=0) {
            $start_height=1;
        }
        $limit=$end_height-$start_height;
        if ($limit<=0) {
            $limit=10;
        }

        $sql=OriginSql::getInstance();
        $blocks = [];
        $block = Blockinc::getInstance();

        $res=$sql->select('block','*',0,array("height>=".$start_height),'height ASC',$limit);
        foreach ($res as $x) {
            $res_trx=$sql->select('trx','*',0,array("height=".$x['height']),'',0);
            if (!$res_trx) {
                $res_trx=[];
            }
            $ress=$sql->select('public_key,signature','*',1,array("height=".$x['height'],"version=0"),'',1);
            if (!$ress) {
                $return_array['height'][]="$x[height]";
                $return_array['id'][]="$x[id]";
                break;
            }
            $miner_public_key=$ress['public_key'];
            $miner_reward_signature=$ress['signature'];

            $ress=$sql->select('public_key,signature','*',1,array("height=".$x['height'],"version=4"),'',1);
            if (!$ress) {
                $return_array['height'][]="$x[height]";
                $return_array['id'][]="$x[id]";
                break;
            }
            $mn_public_key=$ress['public_key'];
            $mn_reward_signature=$ress['signature'];

            $check_block_arr=[
                'data'=>$x,
                'trx_data'=>$res_trx,
                'miner_public_key'=>$miner_public_key,
                'miner_reward_signature'=>$miner_reward_signature,
                'mn_public_key'=>$mn_public_key,
                'mn_reward_signature'=>$mn_reward_signature,
                'from_host'=>''
            ];

            if (!$block->check($check_block_arr)) {
                $return_array['height'][]="$x[height]";
                $return_array['id'][]="$x[id]";
                break;
            }else{
                // echo "check block $x[height] - $x[id] [ok]\n";
            }
        }
        if (count($return_array['height'])==0) {
        	$statuss=true;
        }else{
        	$statuss=false;
        }

        return array('result' => array(
        	'status' => $statuss,
        	'error_result'=>$return_array
        ), 'error'=>'');
	}
	public function cleanblockchain($mode='cli'){
	// Array
	// (
	//     [result] => ok
	//     [error] =>
	// )
		if ($mode!=='cli') {
			return array('result' => '', 'error'=>'fail');
		}

        if (cache::get('sync_lock')=='lock') {
        	return array('result' => '', 'error'=>'locking');
        }
        $sql=OriginSql::getInstance();
        $tables = ["accounts","blocks","transactions","mempool","masternode"];
        foreach ($tables as $table) {
            $sql->exec("TRUNCATE TABLE {$table}");
        }
        cache::set('sync_lock','unlock',900);
        return array('result' => 'ok', 'error'=>'');
	}



}

?>