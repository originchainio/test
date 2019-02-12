<?php

// version: 20190211 test
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

include __DIR__.'/commandlib/Mining.php';

class miner extends base{
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
        $Mining=Mining::getInstance();
        $res=$Mining->getmininginfo();
        $this->echo_display_json(true,$res);
    }
    public function submitNonce($nonce,$argon,$public_key,$private_key){
        if ($this->check_lock()==true) {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }
        $Mining=Mining::getInstance();
        $res=$Mining->submitnonce($nonce,$argon,$public_key,$private_key);

        if ($res) {
            $this->echo_display_json($res['status'],$res['message']);
        }else{
            $this->echo_display_json(false,'unknown');
        }
    }
    public function submitBlock($nonce,$argon,$public_key,$signature,$reward_signature,$data,$date){
        if ($this->check_lock()==true) {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }
        $Mining=Mining::getInstance();
        $res=$Mining->submitblock($nonce,$argon,$public_key,$signature,$reward_signature,$data,$date);

        if ($res) {
            $this->echo_display_json($res['status'],$res['message']);
        }else{
            $this->echo_display_json(false,'unknown');
        }
    }
    public function getWork(){
        if ($this->check_lock()==true) {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }

        $Mining=Mining::getInstance();
        $res=$Mining->getminingwork();
        $this->echo_display_json(true,$res);
    }
    private function check_lock(){
        if (file_exists($SANITY_LOCK_PATH)) {
            return true;
        }
        return false;
    }
}

set_time_limit(360);
if (!isset($_GET['method'])) {  exit;   }

$method = $_GET['method'];
$mine=new miner();


switch ($method) {
    case 'info':
        $mine->info();
        break;
    case 'submitNonce':
        if (!isset($_POST['nonce']) or !isset($_POST['argon']) or !isset($_POST['public_key']) or !isset($_POST['private_key'])) {
            exit;
        }
        $nonce = $_POST['nonce'];
        $argon = $_POST['argon'];
        $public_key = $_POST['public_key'];
        $private_key = $_POST['private_key'];
        $mine->submitNonce($nonce,$argon,$public_key,$private_key);
        break;
    case 'submitBlock':
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
        break;
    case 'getWork':
        $mine->getWork();
        break;
    default:
        # code...
        break;
}