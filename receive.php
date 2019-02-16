<?php
// version: 20190214 test
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
                    $cmd=$Security->cmd($this->config['php_path'].'php sync.php',['Microrectification',$from_host]);
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
                $cmd=$Security->cmd($this->config['php_path'].'php send.php',['block','current',$from_host]);
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
                $cmd=$Security->cmd($this->config['php_path'].'php sync.php',['Microsynchronization',$from_host,$data['height']]);
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
            $cmd=$Security->cmd($this->config['php_path'].'php send.php',['block',$data['id'],'all','true']);
            system($cmd);
        }
        $this->log('add block-ok',1);
        $this->echo_display_json(true,'add block-ok');    
    }

}


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
