<?php
/**
 * 
 */
// version: 20190211 test
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

	public function getbestblockhash(){
		return cache::get('bestblockhash');
	}

	public function getblock($blockhash){
		$sql=OriginSql::getInstance();
		return $sql->select('blocks','*',1,array("id='".$blockhash."'"),'',1);
	}

	public function getblockchaininfo(){
		$sql=OriginSql::getInstance();
		return $sql->select('blocks','*',1,array(),'height DESC',1);
	}	

	public function getblockcount(){
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','height',1,array(),'height DESC',1);
		if ($res) {
			return $res['height'];
		}else{
			return false;
		}
	}	
	public function getblockhash($height){
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','id',1,array("height=".$height),'',1);
		if ($res) {
			return $res['id'];
		}else{
			return false;
		}
	}
	public function getblockstats($hash_or_height){
		$sql=OriginSql::getInstance();
		if (is_numeric($hash_or_height)) {
			$res=$sql->select('blocks','*',1,array("height=".$hash_or_height),'',1);
		}else{
			$res=$sql->select('blocks','*',1,array("id=".$hash_or_height),'',1);
		}
		if (!$res) {
			return false;
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

		return array(
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
	}
	public function getchaintips(){
		$sql=OriginSql::getInstance();
		$res=$sql->select('blocks','id,height',1,array(),'height DESC',1);
		$res['status']='active';

		$validfork=cache::get('validfork');
		if ($validfork==false) {
			$validfork=[];
		}
		return array($res,$validfork);
	}

	public function getdifficulty(){
		$block=Blockinc::getInstance();
		return $block->get_next_difficulty();
	}

	public function getmempoolentry($txid){
		$sql=OriginSql::getInstance();
		return $sql->select('mem','*',1,array("id='".$txid."'"),'',1);
	}

	public function getmempoolsize(){
		$sql=OriginSql::getInstance();
		return $sql->select('mem','*',2,array("id='".$txid."'"),'',0);
	}

	public function getrawmempool(){
		$sql=OriginSql::getInstance();
		return $sql->select('mem','id',0,array(),'',0);
	}

	public function gettxout($txid){
		$this->getmempoolentry($txid);
	}
	public function verifychain($start_height,$end_height){
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
        return array(
        	'status' => $statuss,
        	'error_result'=>$return_array
        );
	}
}

?>