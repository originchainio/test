<?php
/**
 * 
 */
// version: 20190214 test
class Mining extends base{
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

    public function getmininginfo($mode='all'){
        $block=Blockinc::getInstance();
        $current=$block->current();

        $diff = $block->get_next_difficulty($current);
        if (!$diff) {
            return array('result' => '', 'error'=>'fail');
        }
        $argon_mem=16384;
        $argon_threads=4;
        $argon_time=4;

        $res = [
            "difficulty" => $diff,
            "block"      => $current['id'],
            "height"     => $current['height'],
            "recommendation"=> 'mine',
            "argon_mem"  => $argon_mem,
            "argon_threads"  => $argon_threads,
            "argon_time"  => $argon_time,
        ];
        return array('result' => $res, 'error'=>'');
    }
    public function getminingwork($mode='all'){
        if (file_exists(self::$SANITY_LOCK_PATH)) {
            return array('result' => '', 'error'=>'locking');
        }

        $block=Blockinc::getInstance();
        $current = $block->current();


        // get the mempool transactions
        $Mempool=Mempoolinc::getInstance();
        $data = $Mempool->get_mempool_transaction_for_news($current['height']+1,$block->max_transactions());
        if ($data==false) {
            $data=[];
        }

        $difficulty = $block->get_next_difficulty($current);
        if (!$difficulty) {
            return array('result' => '', 'error'=>'fail');
        }
        $reward = $block->reward($current['height']+1, $data);
        if (!$reward) {
            return array('result' => '', 'error'=>'fail');
        }
        return array('result' => array(
            'height'=>$current['height'] + 1,
            //'data'=>$data,
            'reward'=>$reward,
            'block'=>$current['id'],
            'difficulty'=>$difficulty
        ), 'error'=>'');

    }
    public function submitblock($mode='all',$nonce,$argon,$public_key,$signature,$reward_signature,$data,$date){
        if ($this->config['local_node']==true) {
            return array('result' => '', 'error'=>'This is local_node can not mine');
        }
        $nonce = san($nonce);
        $public_key = san($public_key);
        $signature = san($signature);
        $reward_signature = san($reward_signature);
        $date = intval($date);
        $data=json_decode($data, true);

        // check if the miner won the block
        $block=Blockinc::getInstance();
        $current=$block->current();
        $diff = $block->get_next_difficulty($current);

        $result = $block->mine($public_key, $nonce, $argon,$diff, $current['id'], $current['height'], time());
        
        if ($result==false) {
        	$this->log('check block mine [false]',1);
            return array('result' => '', 'error'=>'mine-rejected');
        }

        //date
        if (time()-$current['date']<=30) {
            $this->log('check block date [false]',1);
            return array('result' => '', 'error'=>'date-rejected');
        }

        // generate the new block
        if ($date <= $current['date']) {
            return array('result' => '', 'error'=>'date-rejected');
        }

        $generator = $acc->get_address_from_public_key($public_key);
     

        $reward_miner_private_key='';
        $res = $block->add($public_key,$current['height']+1, $nonce, $data, $date, $diff, $reward_miner_private_key,$argon);


        if ($res) {
            $current = $block->current();

            $Security=Security::getInstance();
            $cmd=$Security->cmd($this->config['php_path'].'php '.dirname(dirname(__FILE__)).'/send.php',['block',$current['id']]);
            system($cmd);
            return array('result' => 'ok', 'error'=>'');
            exit;
        }
        return array('result' => '', 'error'=>'rejected');
    }
    public function submitnonce($mode='all',$nonce,$argon,$public_key,$private_key){
        if ($this->config['local_node']==true) {
            return array('result' => '', 'error'=>'This is local_node can not mine');
        }
        $block=Blockinc::getInstance();
        $current=$block->current();

        $nonce = san($nonce);
        $public_key = san($public_key);
        $private_key = san($private_key);

        $diff = $block->get_next_difficulty($current);
        // check if the miner won the block
        $result = $block->mine($public_key, $nonce, $argon,$diff, $current['id'], $current['height'], time());

        if ($result==false) {
        	$this->log('check block mine [false]',1);
        	return array('result' => '', 'error'=>'mine-rejected');
        	exit;
        } 

        //date
        if (time()-$current['date']<=30) {
            $this->log('check block date [false]',1);
            return array('result' => '', 'error'=>'date-rejected');
            exit;
        }

        // generate the new block
        $res = $block->forge($nonce, $argon, $public_key,$private_key);


        if ($res) {
            $current = $block->current();

            $Security=Security::getInstance();
            $cmd=$Security->cmd($this->config['php_path'].'php '.dirname(dirname(__FILE__)).'/send.php',['block',$current['id']]);
            system($cmd);
            $this->log('cmd:'.$cmd,1);
            return array('result' => 'ok', 'error'=>'');
            exit;
        }
        return array('result' => '', 'error'=>'rejected');
    }
}

?>