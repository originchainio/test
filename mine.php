<?php

// version: 20190128 test
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
include __DIR__.'/lib/OriginSql.lib.php';
// include __DIR__.'/lib/PostThreads.lib.php';
include __DIR__.'/lib/Security.lib.php';
include __DIR__.'/function/function.php';
include __DIR__.'/function/core.php';
class mine extends base{
    private $db;
    private static $SANITY_LOCK_PATH = __DIR__.'/tmp/sanity-lock';
    function __construct(){
        parent::__construct();

        
        $ip = san_ip($_SERVER['REMOTE_ADDR']);
        $ip = filter_var($ip, FILTER_VALIDATE_IP);

        // in case of testnet, all IPs are accepted for mining
        if (!in_array($ip, $this->config['allow_host']) && !empty($ip) && !in_array('*',$this->config['allow_host'])) {
            $this->echo_display_json(false,"unauthorized");
            exit;
        }
    }

    public function info(){
        $block=Blockinc::getInstance();
        // provides the mining info to the miner
        $current=$block->current();

        $diff = $block->get_next_difficulty($current);
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
        $this->echo_display_json(true,$res);
    }
    public function submitNonce($nonce,$argon,$public_key,$private_key){
        if (file_exists(self::$SANITY_LOCK_PATH)) {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }
        if ($this->config['local_node']==true) {
            $this->echo_display_json(false,'This is local_node can not mine');
            exit;
        }
        $block=Blockinc::getInstance();
        $current=$block->current();

        $nonce = san($nonce);
        $public_key = san($public_key);
        $private_key = san($private_key);

        $diff = $block->get_next_difficulty($current);
        // check if the miner won the block
        $result = $block->mine($public_key, $nonce, $argon,$diff, $current['id'], $current['height'], time());

        if ($result==false) {   $this->echo_display_json(false,'mine-rejected'); $this->log('check block mine [false]',1);   exit;   } 

        //date
        if (time()-$current['date']<=30) {
            $this->echo_display_json(false,'date-rejected');
            $this->log('check block date [false]',1);
            exit;
        }

        // generate the new block
        $res = $block->forge($nonce, $argon, $public_key,$private_key);


        if ($res) {
            //if the new block is generated, propagate it to all peers in background
            $current = $block->current();

            $Security=Security::getInstance();
            $cmd=$Security->cmd($this->config['php_path'].'php propagate.php',['block',$current['id']]);
            system($cmd);
            $this->log('cmd:'.$cmd,1);

            $this->echo_display_json(true,'accepted');
            exit;
        }

        $this->echo_display_json(false,'rejected');
    }
    public function submitBlock($nonce,$argon,$public_key,$signature,$reward_signature,$data,$date){
        // in case the blocks are syncing, reject all
        if (file_exists(self::$SANITY_LOCK_PATH)) {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }
        if ($this->config['local_node']==true) {
            $this->echo_display_json(false,'This is local_node can not mine');
            exit;
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
        
        if ($result==false) {    $this->echo_display_json(false,'mine-rejected'); $this->log('check block mine [false]',1);   exit;    }

        //date
        if (time()-$current['date']<=30) {
            $this->echo_display_json(false,'date-rejected');
            $this->log('check block date [false]',1);
            exit;
        }

        // generate the new block
        if ($date <= $current['date']) {
            $this->echo_display_json(false,'date-rejected');
            exit;
        }

        $generator = $acc->get_address_from_public_key($public_key);
     

        $reward_miner_private_key='';
        $res = $block->add($public_key,$current['height']+1, $nonce, $data, $date, $diff, $reward_miner_private_key,$argon);


        if ($res) {
            //if the new block is generated, propagate it to all peers in background
            $current = $block->current();

            $Security=Security::getInstance();
            $cmd=$Security->cmd($this->config['php_path'].'php propagate.php',['block',$current['id']]);
            system($cmd);

            $this->echo_display_json(true,'accepted');
            exit;
        }

        $this->echo_display_json(false,'rejected');
    }
    public function getWork(){
        if (file_exists(self::$SANITY_LOCK_PATH)) {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
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
        // always sort  the transactions in the same way


        // reward transaction and signature
        $reward = $block->reward($current['height']+1, $data);
        $this->echo_display_json(true,array(
            'height'=>$current['height'] + 1,
            'data'=>$data,
            'reward'=>$reward,
            'block'=>$current['id'],
            'difficulty'=>$difficulty
        ));
    }
}

set_time_limit(360);
if (!isset($_GET['q'])) {
    exit;
}
$q = $_GET['q'];
$mine=new mine();

if ($q == "info") {
    $mine->info();
} elseif ($q == "submitNonce") {
    if (!isset($_POST['nonce']) or !isset($_POST['argon']) or !isset($_POST['public_key']) or !isset($_POST['private_key'])) {
        exit;
    }
    $nonce = $_POST['nonce'];
    $argon = $_POST['argon'];
    $public_key = $_POST['public_key'];
    $private_key = $_POST['private_key'];
    $mine->submitNonce($nonce,$argon,$public_key,$private_key);
} elseif ($q == "submitBlock") {
    if (!isset($_POST['nonce']) or !isset($_POST['argon']) or !isset($_POST['public_key']) or !isset($_POST['private_key'])
        or !isset($_POST['reward_signature']) or !isset($_POST['data']) or !isset($_POST['date'])
    ) {
        exit;
    }
    $nonce = $_POST['nonce'];
    $argon = $_POST['argon'];
    $public_key = $_POST['public_key'];
    $signature = $_POST['private_key'];
    $reward_signature = $_POST['reward_signature'];
    $data = $_POST['data'];
    $date = $_POST['date'];
    $mine->submitBlock($nonce,$argon,$public_key,$signature,$reward_signature,$data,$date);
} elseif ($q == "getWork") {
    $mine->getWork();
}