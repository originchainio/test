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
class receive extends base{
    function __construct($coinname){
        parent::__construct();

        if ($coinname=='' or $coinname!='origin') {
            $this->echo_display_json(false,"Invalid coin");
            exit;
        }
    }

    public function submitTransaction($id,$height,$dst,$val,$fee,$signature,$version,$message,$date,$public_key,$peer){
        if (cache::get('sync_block')=='lock') {
            $this->echo_display_json(false,'sync lock');
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
            $this->log('receive->submitTransaction mem check false',0,true);
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

        //send mem to peer ,this is new mem ,check function check is new
        $id=escapeshellarg($id);
        $Security=Security::getInstance();
        $cmd=$Security->cmd($this->config['php_path'].'php send.php',['transaction',$id]);
        system($cmd);


        $this->echo_display_json(true,"transaction-ok");
    }
    //Other peer block is sent to me
    public function submitBlock($data,$trx_data,$miner_public_key,$miner_reward_signature,$mn_public_key,$mn_reward_signature,$from_host=''){
        if (cache::get('sync_block')=='lock') {
            $this->echo_display_json(false,'Sanity lock in place');
            exit;
        }

        $data['id'] = san($data['id']);

        $block=Blockinc::getInstance();
        $transaction=Transactioninc::getInstance();
        $Security=Security::getInstance();
        //
        $current = $block->current();
        // block already in the blockchain
        if ($current['id'] == $data['id']) {
            $this->log('receive->submitBlock block already in the blockchain false',0,true);
            $this->echo_display_json(true,'block-ok');
            exit;
        }
        //
        if ($data['date'] > time() + 30) {
            $this->log('receive->submitBlock block time false',0,true);
            $this->echo_display_json(false,'block in the future');
            exit;
        }
        // $current['height'] == $data['height']
        if ($current['height'] == $data['height'] && $current['id'] != $data['id']) {
            if ($current['transactions']<=$data['transactions']) {
                if ($from_host!=='') {
                    $cmd=$Security->cmd($this->config['php_path'].'php sync.php',['Microrectification',$from_host]);
                    system($cmd);
                    $this->log('receive->submitBlock Microrectification',0,true);
                    $this->echo_display_json(true,'Microrectification');
                }
                exit;
            }else{
                $this->log('receive->submitBlock reverse-Microrectification',0,true);
                $this->echo_display_json(true,'reverse-Microrectification');// if it's not, suggest to the peer to get the block from us
                exit;
            }
        }
 
        // $data['height']>$current['height']
        if ($data['height']>$current['height'] and $data['height'] - $current['height'] > 150) {
            $this->log('receive->submitBlock block-out-of-sync',0,true);
            $this->echo_display_json(false,'block-out-of-sync');
            exit;
        }

        // $data['height']>$current['height']
        if ($data['height']>$current['height'] and $data['height'] - $current['height'] <= 150) {
            if ($from_host!='') {
                $cmd=$Security->cmd($this->config['php_path'].'php sync.php',['Microsynchronization',$from_host,$data['height']]);
                system($cmd);
                $this->log('receive->submitBlock '.$cmd,0,true);
                $this->echo_display_json(true,'current block-old Microsynchronization');
                // exit;
            }
        }
        // $data['height'] < $current['height']
        if ($data['height'] < $current['height'] and $this->config['local_node']==false) {

            if ($from_host!=='') {
                $from_host = san_host($from_host);
                $cmd=$Security->cmd($this->config['php_path'].'php send.php',['block','current',$from_host]);
                system($cmd);

            }
            $this->log('receive->submitBlock block-too-old',0,true);
            $this->echo_display_json(false,'block-too-old');
            exit;
        }



        // send block to all our peers
        if ($this->config['local_node']==false) {
            $Security=Security::getInstance();
            $cmd=$Security->cmd($this->config['php_path'].'php send.php',['block',$data['id'],'all','true']);
            system($cmd);
        }
        $this->log('receive->submitBlock add block-ok',0,true);
        $this->echo_display_json(true,'add block-ok');    
    }

}

date_default_timezone_set("UTC");
if (!isset($_POST['coin']) or !isset($_GET['q'])) {
    exit;
}
$receive=new receive($_POST['coin']);
$q = trim($_GET['q']);

switch ($q) {
    case 'submitTransaction':
        $data = json_decode(trim($_POST['data']), true);
        $receive->submitTransaction(
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
        $receive->submitBlock($data['data'],$data['trx_data'],$data['miner_public_key'],$data['miner_reward_signature'],$data['mn_public_key'],$data['mn_reward_signature'],$from_host);
        break;
    default:
        break;
}
