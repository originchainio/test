<?php
// Peer architecture comes from https://github.com/arionum/node
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
class Peer extends base{
    function __construct($coinname){
        parent::__construct();

        if ($coinname=='' or $coinname!='origin') {
            $this->echo_display_json(false,"Invalid coin");
            exit;
        }
    }

    public function peer($hostname){
        // sanitize the hostname
        $hostname = filter_var($hostname, FILTER_SANITIZE_URL);
        $hostname = san_host($hostname);

        $Peerinc=Peerinc::getInstance();

        if ($Peerinc->check($hostname)==false) {
            $this->log('peer->peer hostname check false',0,true);
            $this->echo_display_json(false,"hostname check error");
            exit;
        }
        // re-peer to make sure the peer is valid
        if ($this->config['local_node']==false and $data['repeer'] == 1) {
            $res = peer_post($hostname."/peer.php?q=peer", ["hostname" => $this->config['hostname']]);
        }
        // if it's already peered, only repeer on request
        if ($Peerinc->get_peer_count_from_hostname($hostname)==false) {
            if ($Peerinc->get_peer_all_count()<$this->config['db_max_peers']) {
                $Peerinc->add($hostname,0,0,1,md5($hostname),0,0);
            }
        }
        $this->log('peer->peer add peer true',0,true);
        $this->echo_display_json(true,"add peer ok");        
    }
    public function ping($data=[]){
        // confirm peer is active
        $this->echo_display_json(true,"success");
    }
    public function currentBlock(){
        $block=Blockinc::getInstance();
        // receive a new transaction from a peer
        $current = $block->current();
        $export = $block->export_for_other_peers("", $current['height']);
        if (!$export) {
            $this->echo_display_json(false,"invalid-block");
        }
        $this->echo_display_json(true,$export);
    }
    public function getBlock($height){
        $height = intval($height);
        $block=Blockinc::getInstance();

        $export = $block->export_for_other_peers("", $height);
        if (!$export) {
            $this->echo_display_json(false,"invalid-block");
        }
        $this->echo_display_json(true,$export);
    }
    public function getBlocks($height){
        // returns X block starting at height,  used in syncing
        $height = intval($height);
        $sql=OriginSql::getInstance();
        $block=Blockinc::getInstance();

        $r = $sql->select('block','id,height',0,array("height>=".$height),'height ASC',100);

        foreach ($r as $x) {
            $blocks[$x['height']] = $block->export_for_other_peers($x['id']);
        }
        $this->echo_display_json(true,$blocks);
    }
    public function getPeers(){
        $sql=OriginSql::getInstance();
        
        $peers = $sql->select('peer','hostname',0,array("blacklisted<".time()),'RAND()',10);
        if (!$peers) {
            $this->echo_display_json(false,"invalid-peer");
        }
        $this->echo_display_json(true,$peers);
    }
    public function getBalance($address){
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','balance',1,array("id='".$address."'"),'',1);
        if (!$res) {
            $this->echo_display_json(true,'0.00000000');
        }else{
            $balance=number_format($res['balance'],8);
            $this->echo_display_json(true,$balance);
        }
    }


}

date_default_timezone_set("UTC");
if (!isset($_POST['coin']) or !isset($_GET['q'])) {
    exit;
}
$peer=new Peer($_POST['coin']);
$q = trim($_GET['q']);

switch ($q) {
    case 'peer':
        $data = json_decode(trim($_POST['data']), true);
        $peer->peer($data['hostname']);
        break;
    case 'ping':
        $peer->ping();
        break;
    case 'submitTransaction':
        $data = json_decode(trim($_POST['data']), true);
        $peer->submitTransaction(
            $data['id'],
            $data['height'],
            $data['dst'],
            $data['val'],
            $data['fee'],
            $data['signature'],
            $data['version'],
            $data['message'],
            $data['date'],
            $data['public_key'],
            $data['peer']
        );
        break;
    case 'submitBlock':
        $data = json_decode(trim($_POST['data']), true);
        if (isset($data['from_host'])) {
            $from_host=$data['from_host'];
        }else{
            $from_host='';
        }
        $peer->submitBlock($data['data'],$data['trx_data'],$data['miner_public_key'],$data['miner_reward_signature'],$data['mn_public_key'],$data['mn_reward_signature'],$from_host);
        break;
    case 'currentBlock':
        $peer->currentBlock();
        break;
    case 'getBlock':
        $data = json_decode(trim($_POST['data']), true);
        $peer->getBlock($data['height']);
        break;
    case 'getBlocks':
        $data = json_decode(trim($_POST['data']), true);
        $peer->getBlocks($data['height']);
        break;
    case 'getPeers':
        $peer->getPeers();
        break;
    case 'getBalance':
        $data = json_decode(trim($_POST['data']), true);
        $peer->getBalance($data['address']);
        break;
    default:

        break;
}

// $peer=new Peer($_POST['coin']);
// if(method_exists($peer,$q)){
//     if (!empty($_POST['data'])) {
//         $data = json_decode(trim($_POST['data']), true);
//         $peer->$q($data);
//     }else{
//         $peer->$q();
//     }
// }