<?php
/**
 * 
 */
// version: 20190225
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
        if (cache::get('sync_lock')=='lock') {
            return array('result' => '', 'error'=>'locking');
        }

        $block=Blockinc::getInstance();
        $current = $block->current();


        // get the mempool transactions
        $Mempool=Mempoolinc::getInstance();
        $data = $Mempool->get_mempool_transaction_for_news($current['height']+1,$block->max_transactions());
        if ($data==false) {
             $data=[];
            //return array('result' => '', 'error'=>'get mem is fails');
        }

        $difficulty = $block->get_next_difficulty($current);
        if (!$difficulty) {
            return array('result' => '', 'error'=>'fail diff');
        }
        $reward_r = $block->reward($current['height']+1, $data);
        $reward = $reward_r['miner_reward'];

        if (!$reward) {
            return array('result' => '', 'error'=>'fail reward');
        }
        return array('result' => array(
            'height'=>$current['height'] + 1,
            //'data'=>$data,
            'reward'=>$reward,
            'block'=>$current['id'],
            'difficulty'=>$difficulty
        ), 'error'=>'');

    }
    public function submitnonce($mode='all',$nonce,$argon,$public_key,$private_key){

        if ($this->config['local_node']==true) {
            return array('result' => '', 'error'=>'This is local_node can not mine');
        }
        $block=Blockinc::getInstance();
        $current=$block->current();
        if ($current==false) {
            return array('result' => '', 'error'=>'This is current get fail');
        }
        $nonce = san($nonce);
        $public_key = san($public_key);
        $private_key = san($private_key);

        $diff = $block->get_next_difficulty($current);
        if ($diff==false) {
            return array('result' => '', 'error'=>'diff get fail');
        }

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