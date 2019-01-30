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
class Peer extends base{
    private static $SANITY_LOCK_PATH = __DIR__.'/tmp/sanity-lock';
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
            $this->log('hostname check error',1);
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
        $this->log('add peer ok');
        $this->echo_display_json(true,"add peer ok");        
    }
    public function ping($data=[]){
        // confirm peer is active
        $this->echo_display_json(true,"success");
    }
    public function submitTransaction($id,$height,$dst,$val,$fee,$signature,$version,$message,$date,$public_key,$peer){

        if (file_exists(self::$SANITY_LOCK_PATH)) {

            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }
        $id = san($id);
        // validate transaction data
        $Mempool=Mempoolinc::getInstance();

        if (!$Mempool->check([
                'id'=>$id,
                'height'=>$height,
                'dst'=>$dst,
                'val'=>$val,
                'fee'=>$fee,
                'signature'=>$signature,
                'version'=>$version,
                'message'=>$message,
                'date'=>$date,
                'public_key'=>$public_key,
                'peer'=>$peer,
        ])) {
            $this->log('Invalid transaction',1);
            $this->echo_display_json(false,"Invalid transaction");
            exit;
        }

        //$sql=OriginSql::getInstance();
        // make sure the peer is not flooding us with transactions
        //$res = $sql->select('mem','*',2,array("public_key='".$public_key."'"),'',1);
        // if ($res > 25) {
        //     $this->echo_display_json(false,"Too many transactions from this address in mempool. Please rebroadcast later.");
        //     exit;
        // }

        // $res = $sql->('mem','*',2,array("peer='".$this->ip."'"),'',1);
        // if ($res > $this->config['peer_max_mempool']) {
        //     $this->echo_display_json(false,"Too many transactions broadcasted from this peer");
        //     exit;
        // }

        // add to mempool
        $Mempool=Mempoolinc::getInstance();
        $Mempool->add_mempool($height,$dst,$val,$fee,$signature,$version,$message,$public_key,$date, $peer);

        //广播给其他peer 前边check已经检测过mem和trx里边没有出现 是新的mem
        $id=escapeshellarg($id);
        $Security=Security::getInstance();
        $cmd=$Security->cmd($this->config['php_path'].'php propagate.php',['transaction',$id]);
        system($cmd);


        $this->echo_display_json(true,"transaction-ok");
    }
    //接受别的peer过来的block
    public function submitBlock($data,$trx_data,$miner_public_key,$miner_reward_signature,$mn_public_key,$mn_reward_signature,$from_host=''){
        if (file_exists(self::$SANITY_LOCK_PATH)) {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }

        $data['id'] = san($data['id']);

        $block=Blockinc::getInstance();
        $transaction=Transactioninc::getInstance();
        //
        $current = $block->current();
        // block already in the blockchain
        if ($current['id'] == $data['id']) {
            $this->log('block-ok',1);
            $this->echo_display_json(true,'block-ok');
            exit;
        }
        //
        if ($data['date'] > time() + 30) {
            $this->log('block in the future',1);
            $this->echo_display_json(false,'block in the future');
            exit;
        }

        if ($current['height'] == $data['height'] && $current['id'] != $data['id']) {
            // different forks, same height
            // convert the first 12 characters from hex to decimal and the block with the largest number wins
            $no1 = hexdec(substr(coin2hex($current['id']), 0, 12));
            $no2 = hexdec(substr(coin2hex($data['id']), 0, 12));
            if (gmp_cmp($no1, $no2) == 1) {
                $accept_new = true;
            }else{
                $this->log('hexdec-false',1);
                $accept_new = false;
            }
            
            if ($accept_new) {
                // if the new block is accepted, run a microsanity to sync it
                if ($from_host!=='') {

                    $Security=Security::getInstance();
                    $cmd=$Security->cmd($this->config['php_path'].'php sanity.php',['Microrectification',$from_host]);
                    system($cmd);
                    $this->log('microsanity',1);
                    $this->echo_display_json(true,'microsanity');
                }
                exit;
            } else {
                $this->log('reverse-microsanity',1);
                $this->echo_display_json(true,'reverse-microsanity');// if it's not, suggest to the peer to get the block from us
                exit;
            }
        }
 
        // if the height of the block submitted is lower than our current height, send them our current block
        if ($data['height'] < $current['height'] and $this->config['local_node']==false) {
            $sql=OriginSql::getInstance();

            if ($from_host!=='') {
                $from_host = san_host($from_host);

                $Security=Security::getInstance();
                $cmd=$Security->cmd($this->config['php_path'].'php propagate.php',['block','current',$from_host]);
                system($cmd);

            }
            $this->log('block-too-old',1);
            $this->echo_display_json(false,'block-too-old');
            exit;
        }

        // if the block difference is bigger than 150, nothing should be done. They should sync via sanity
        if ($data['height']>$current['height'] and $data['height'] - $current['height'] > 150) {
            $this->log('block-out-of-sync',1);
            $this->echo_display_json(false,'block-out-of-sync');
            exit;
        }

            // request them to send us a microsync with the latest blocks
        if ($data['height']>$current['height'] and $data['height'] - $current['height'] <= 150) {
            if ($from_host!='') {
 
                $Security=Security::getInstance();
                $cmd=$Security->cmd($this->config['php_path'].'php sanity.php',['Microsynchronization',$from_host,$data['height']]);
                $this->log($cmd);
                system($cmd);

                $this->echo_display_json(true,'current block-old Microsynchronization');
                // exit;
            }
        }
        // // check block data
        // if (!$block->check(['data'=>$data,'trx_data'=>$trx_data,'miner_public_key'=>$miner_public_key,'miner_reward_signature'=>$miner_reward_signature,'mn_public_key'=>$mn_public_key,'mn_reward_signature'=>$mn_reward_signature])) {
        //     $this->log('check-false-invalid-block',1);
        //     $this->echo_display_json(false,'check-false-invalid-block');
        //     exit;
        // }

        // //check trx
        // foreach ($trx_data as $valueee) {
        //     if ($valueee['height']!=$data['height']) {
        //         $this->log('check trx height is false',1);
        //         $this->echo_display_json(false,'check trx height is false');
        //         exit;
        //     }
        //     if ($valueee['block']!=$data['id']) {
        //         $this->log('check trx block is false',1);
        //         $this->echo_display_json(false,'check trx block is false');
        //         exit;
        //     }
        // }


        // // add the block to the blockchain
        // $res = $block->add(
        //     $miner_public_key,
        //     $data['height'],
        //     $data['nonce'],
        //     $trx_data,
        //     $data['date'],
        //     $data['difficulty'],
        //     $data['signature'],
        //     $miner_reward_signature,
        //     $mn_reward_signature,
        //     $data['argon']
        //     );

        // if (!$res) {
        //     $this->log('invalid-block-data',1);
        //     $this->echo_display_json(false,'invalid-block-data');
        //     exit;
        // }
        // send it to all our peers
        if ($this->config['local_node']==false) {
            $Security=Security::getInstance();
            $cmd=$Security->cmd($this->config['php_path'].'php propagate.php',['block',$data['id'],'all','true']);
            system($cmd);
        }
        $this->log('add block-ok',1);
        $this->echo_display_json(true,'add block-ok');    
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


if (!isset($_POST['coin']) or !isset($_GET['q'])) {
    exit;
}
$peer=new Peer($_POST['coin']);
$q = trim($_GET['q']);

// $q='submitBlock';
// $_POST['data']=json_encode(['data'=>[
//     'id'=>'5z2BqfCxjSKBzJaPDHje2UQXn6DueBzuMAXqpcm5My1RBFQrkKnBoyiiN1ShLNtRk92oxnf83vod6BpAWgh4M5U3C',
//     'generator'=>'2j7bgeD9vZDsgG5z9vJEZ3qRFqaVCb3MizMgS3RF7b7Ynw2v3ZvUGRkm2LiAQZGptYJ1NNrcrn9TGzCwjM1kiHyi',
//     'height'=>114,
//     'date'=>1547547502,
//     'nonce'=>'duzOWekYqbZ3VZIqgxG7YbHUJZY6RMRpwxvjaCKCMTE',
//     'signature'=>'iKx1CJMi3nz9bAUFJfyYNkwKtPePFubPfdwouyLPfp5hQ2q1x7HJnb7dJR7L7TzfoU81W2o9VMRCmAJBJn77fhWsMhLyurtsGp',
//     'difficulty'=>'9223372036854775800',
//     'argon'=>'$M2kyU3NIa0NKdGdvMC9HQQ$wPzrAIDtXmftz+8h2uuP2ggazNZYiTKPR0lb3BGZeSE',
//     'transactions'=>3,

//     ],'trx_data'=>[],'miner_public_key'=>'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyoYgPZdpuR82KCiBhAmE1ewouSLKSB4fYWoPV3EP64pZVx5mUU9jjD9GYXMtWDdZBii2F4PfiaFnjAYUXejKEANi','miner_reward_signature'=>'','mn_public_key'=>'','mn_reward_signature'=>'','from_host'=>'']);
// $_POST['coin']='origin';


// $peer=new Peer($_POST['coin']);

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