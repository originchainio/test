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
class Util extends base{
    function __construct(){
        parent::__construct();

        if ($this->info['cli'] != true) {
            echo "\nneed to run cli modle";
            exit;
        }
    }

/**
 * @api {php util.php} clean Clean
 * @apiName clean
 * @apiGroup UTIL
 * @apiDescription Cleans the entire database
 *
 * @apiExample {cli} Example usage:
 * php util.php clean
 */
    public function clean(){
        if (file_exists("/tmp/sanity-lock")) {
            die("Sanity running. Wait for it to finish");
        }
        touch("/tmp/sanity-lock");

        $sql=OriginSql::getInstance();

        $tables = ["accounts","blocks","transactions","mempool","masternode"];
        foreach ($tables as $table) {
            $sql->exec("TRUNCATE TABLE {$table}");
        }
        echo "\n The database has been cleared\n";
        unlink("/tmp/sanity-lock");
    }
/**
 * @api {php util.php} pop Pop
 * @apiName pop
 * @apiGroup UTIL
 * @apiDescription Cleans the entire database
 *
 * @apiParam {Number} arg2 Number of blocks to delete
 *
 * @apiExample {cli} Example usage:
 * php util.php pop 1
 */
    public function pop($number){
        if (file_exists("tmp/sanity-lock")) {
            die("Sanity running. Wait for it to finish");
        }
        touch("tmp/sanity-lock");

        $number = intval($number);

        $block=Blockinc::getInstance();
        $block->pop($number);
        unlink("tmp/sanity-lock"); 
    }
/**
 * @api {php util.php} block_time block_time
 * @apiName block_time
 * @apiGroup UTIL
 * @apiDescription Shows the block time of the last 100 blocks
 *
 * @apiExample {cli} Example usage:
 * php util.php block_time
 *
 * @apiSuccessExample {text} Success-Response:
 * 16830 -> 323
 * ...
 * 16731 -> 302
 * Average block time: 217 seconds
 */
    public function block_time(){
        $sql=OriginSql::getInstance();
        $t = time();
        $res=$sql->select('block','*',0,array(),'height DESC',100);

        $start = 0;
        foreach ($res as $x) {
            if ($start == 0) {
                $start = $x['date'];
            }
            $time = $t - $x['date'];
            $t = $x['date'];
            echo "$x[height]\t\t$time\t\t$x[difficulty]\n";
            $end = $x['date'];
        }
        echo "Average block time: ".ceil(($start - $end) / 100)." seconds\n";
    }
/**
 * @api {php util.php} topeer Peer
 * @apiName topeer
 * @apiGroup UTIL
 * @apiDescription Creates a peering session with another node
 *
 * @apiParam {text} arg2 The Hostname of the other node
 *
 * @apiExample {cli} Example usage:
 * php util.php topeer http://peer1.origin.com
 *
 * @apiSuccessExample {text} Success-Response:
 * Peering OK
 */
    public function topeer($hostname){
        $hostname=trim($hostname);
        $res = peer_post($hostname."/peer.php?q=peer", ["hostname" => $this->config['hostname']]);
        if ($res != false) {
            echo "Peering OK\n";
        } else {
            echo "Peering FAIL\n";
        }
    }
/**
 * @api {php util.php} current Current
 * @apiName current
 * @apiGroup UTIL
 * @apiDescription Prints the current block in var_dump
 *
 * @apiExample {cli} Example usage:
 * php util.php current
 *
 * @apiSuccessExample {text} Success-Response:
 * array(9) {
 *  ["id"]=>
 *  string(88) "4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR"
 *  ["generator"]=>
 *  string(88) "5ADfrJUnLefPsaYjMTR4KmvQ79eHo2rYWnKBRCXConYKYJVAw2adtzb38oUG5EnsXEbTct3p7GagT2VVZ9hfVTVn"
 *  ["height"]=>
 *  int(16833)
 *  ["date"]=>
 *  int(1519312385)
 *  ["nonce"]=>
 *  string(41) "EwtJ1EigKrLurlXROuuiozrR6ICervJDF2KFl4qEY"
 *  ["signature"]=>
 *  string(97) "AN1rKpqit8UYv6uvf79GnbjyihCPE1UZu4CGRx7saZ68g396yjHFmzkzuBV69Hcr7TF2egTsEwVsRA3CETiqXVqet58MCM6tu"
 *  ["difficulty"]=>
 *  string(8) "61982809"
 *  ["argon"]=>
 *  string(68) "$SfghIBNSHoOJDlMthVcUtg$WTJMrQWHHqDA6FowzaZJ+O9JC8DPZTjTxNE4Pj/ggwg"
 *  ["transactions"]=>
 *  int(0)
 * }
 *
 */
    public function current(){
        $block=Blockinc::getInstance();
        echo_array($block->current());
    }
/**
 * @api {php util.php} blocks Blocks
 * @apiName blocks
 * @apiGroup UTIL
 * @apiDescription Prints the id and the height of the blocks >=arg2, max 100 or arg3
 *
 * @apiParam {number} arg2 Starting height
 *
 * @apiParam {number} [arg3] Block Limit
 *
 * @apiExample {cli} Example usage:
 * php util.php blocks 10800 5
 *
 * @apiSuccessExample {text} Success-Response:
 * 10801   2yAHaZ3ghNnThaNK6BJcup2zq7EXuFsruMb5qqXaHP9M6JfBfstAag1n1PX7SMKGcuYGZddMzU7hW87S5ZSayeKX
 * 10802   wNa4mRvRPCMHzsgLdseMdJCvmeBaCNibRJCDhsuTeznJh8C1aSpGuXRDPYMbqKiVtmGAaYYb9Ze2NJdmK1HY9zM
 * 10803   3eW3B8jCFBauw8EoKN4SXgrn33UBPw7n8kvDDpyQBw1uQcmJQEzecAvwBk5sVfQxUqgzv31JdNHK45JxUFcupVot
 * 10804   4mWK1f8ch2Ji3D6aw1BsCJavLNBhQgpUHBCHihnrLDuh8Bjwsou5bQDj7D7nV4RsEPmP2ZbjUUMZwqywpRc8r6dR
 * 10805   5RBeWXo2c9NZ7UF2ubztk53PZpiA4tsk3bhXNXbcBk89cNqorNj771Qu4kthQN5hXLtu1hzUnv7nkH33hDxBM34m
 *
 */
    public function blocks_id($start_height=1,$limit=1){
        if ($start_height==='') {
            $start_height=1;
        }
        if ($limit==='') {
            $limit=1;
        }
        $height = intval($height);
        $limit = intval($limit);

        $sql=OriginSql::getInstance();
        $res=$sql->select('block','*',0,array("height>=".$height),'height ASC',$limit);
        foreach ($res as $x) {
            echo "$x[height]\t$x[id]\n";
        }
    }
/**
 * @api {php util.php} recheck_blocks Recheck_Blocks
 * @apiName recheck_blocks
 * @apiGroup UTIL
 * @apiDescription Recheck all the blocks to make sure the blockchain is correct
 *
 * @apiExample {cli} Example usage:
 * php util.php recheck_blocks 1 100
 *
 */
    public function recheck_blocks($start_height,$end_height){
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
                echo "check block $x[height] - $x[id] [fail]\n";
                break;
            }
            $miner_public_key=$ress['public_key'];
            $miner_reward_signature=$ress['signature'];

            $ress=$sql->select('public_key,signature','*',1,array("height=".$x['height'],"version=4"),'',1);
            if (!$ress) {
                echo "check block $x[height] - $x[id] [fail]\n";
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
                echo "check block $x[height] - $x[id] [fail]\n";
                break;
            }else{
                echo "check block $x[height] - $x[id] [ok]\n";
            }
        }
    }
/**
 * @api {php util.php} peer Peer
 * @apiName print_peers
 * @apiGroup UTIL
 * @apiDescription Prints all the peers and their status
 *
 * @apiExample {cli} Example usage:
 * php util.php print_peers
 *
 * @apiSuccessExample {text} Success-Response:
 * http://111.111.111.111   active
 * ...
 */
    public function print_peers(){
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',0,array(),'reserve ASC',0);

        foreach ($res as $key => $value) {
            if ($value['reserve']==1) {
                echo '[ '.$key.' ]'.$x['hostname'].'  reserve'."\n";
            }else{
                echo '[ '.$key.' ]'.$x['hostname'].'  active'."\n";
            }
        }  
    }
/**
 * @api {php util.php} mempool Mempool
 * @apiName mempool_count
 * @apiGroup UTIL
 * @apiDescription Prints the number of transactions in mempool
 *
 * @apiExample {cli} Example usage:
 * php util.php mempool_count
 *
 * @apiSuccessExample {text} Success-Response:
 * Mempool count: 12
 */
    public function mempool_count(){
        $sql=OriginSql::getInstance();
        $res=$sql->select('mem','*',2,array(),'',0);
        echo "Mempool count: $res\n";
    }
 /**
 * @api {php util.php} delete-peer Delete-peer
 * @apiName delete_peer_hostname
 * @apiGroup UTIL
 * @apiDescription Removes a peer from the peerlist
 *
 * @apiParam {text} arg2 Peer's hostname
 *
 * @apiExample {cli} Example usage:
 * php util.php delete_peer_hostname http://abc.originchain.com
 *
 * @apiSuccessExample {text} Success-Response:
 * Peer removed
 */
    public function delete_peer_hostname($hostname){
        $peer = trim($hostname);
        if (empty($peer)) {die("Invalid peer");}

        $sql=OriginSql::getInstance();
        $res=$sql->delete('peer',array("hostname='".$peer."'"));

        if ($res) {
            echo "Peer removed [true]\n";
        }else{
            echo "Peer removed [false]\n";
        }
    }
 /**
 * @api {php util.php} delete-peer Delete-peer
 * @apiName delete_peer_hostname
 * @apiGroup UTIL
 * @apiDescription Removes a peer from the peerlist
 *
 * @apiParam {text} arg2 Peer's hostname
 *
 * @apiExample {cli} Example usage:
 * php util.php delete_peer_hostname http://abc.originchain.com
 *
 * @apiSuccessExample {text} Success-Response:
 * Peer removed
 */
    public function delete_peer_ip($ip){
        $peer = trim($ip);
        if (empty($peer)) {die("Invalid peer");}

        $sql=OriginSql::getInstance();
        $res=$sql->delete('peer',array("ip='".$peer."'"));

        if ($res) {
            echo "Peer removed [true]\n";
        }else{
            echo "Peer removed [false]\n";
        }
    }
/**
 * @api {php util.php} recheck_peers Recheck-peers
 * @apiName recheck_peers
 * @apiGroup UTIL
 * @apiDescription recheck a peer all ping
 *
 * @apiParam {text} arg2 Peer's hostname
 *
 * @apiExample {cli} Example usage:
 * php util.php recheck_peers
 *
 * @apiSuccessExample {text} Success-Response:
 * delete true
 */
    public function recheck_peers(){
        $sql=OriginSql::getInstance();

        $res=$sql->select('peer','*',0,array(),'',0);

        foreach ($r as $x) {
            $a = peer_post($x['hostname']."/peer.php?q=ping");
            if ($a == "success") {
                echo "$x[hostname] ->ok \n";
            } else {
                $res=$sql->delete('peer',array("id=".$x['id']));
                if ($res) {
                    echo "$x[hostname] -> delete true\n";
                }else{
                    echo "$x[hostname] -> delete false\n";
                }
            }
        }
    }
/**
 * @api {php util.php} peers_current Peers_current
 * @apiName peers_current
 * @apiGroup UTIL
 * @apiDescription Prints the current height of all the peers
 *
 * @apiExample {cli} Example usage:
 * php util.php peers_current
 *
 * @apiSuccessExample {text} Success-Response:
 * http://peer5.origin.com        16849
 * ...
 * http://peer10.origin.com        16849
 */
    public function peers_current(){
        $peer=Peerinc::getInstance();
        $sql=OriginSql::getInstance();

        $res=$sql->select('peer','*',0,array("reserve=1"),'',0);

        foreach ($res as $key => $value) {
            $a = $peer->peer_post($value['hostname']."/peer.php?q=currentBlock", [], 5);
            echo "[ $key ] $x[hostname]\t$a[height]\n";
        }  
    }
/**
 * @api {php util.php} balance Balance
 * @apiName balance
 * @apiGroup UTIL
 * @apiDescription Prints the balance of an address or a public key
 *
 * @apiParam {text} arg2 address or public_key
 *
 * @apiExample {cli} Example usage:
 * php util.php balance 5WuRMXGM7Pf8NqEArVz1NxgSBptkimSpvuSaYC79g1yo3RDQc8TjVtGH5chQWQV7CHbJEuq9DmW5fbmCEW4AghQr
 *
 * @apiSuccessExample {text} Success-Response:
 * Balance: 2,487
 */
    public function balance($address){
        $id = san($address);
        $sql=OriginSql::getInstance();

        $res=$sql->select('acc','balance',1,array("id='".$id."'"),'',1);
        if ($res) {
            echo "Balance: ".number_format($res['balance'],8)."\n";
        }else{
            echo "select false";
        }  
    }
/**
 * @api {php util.php} block Block
 * @apiName block
 * @apiGroup UTIL
 * @apiDescription Returns a specific block
 *
 * @apiParam {text} arg2 block id
 *
 * @apiExample {cli} Example usage:
 * php util.php block 4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR
 *
 * @apiSuccessExample {text} Success-Response:
 * array(9) {
 *  ["id"]=>
 *  string(88) "4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR"
 *  ["generator"]=>
 *  string(88) "5ADfrJUnLefPsaYjMTR4KmvQ79eHo2rYWnKBRCXConYKYJVAw2adtzb38oUG5EnsXEbTct3p7GagT2VVZ9hfVTVn"
 *  ["height"]=>
 *  int(16833)
 *  ["date"]=>
 *  int(1519312385)
 *  ["nonce"]=>
 *  string(41) "EwtJ1EigKrLurlXROuuiozrR6ICervJDF2KFl4qEY"
 *  ["signature"]=>
 *  string(97) "AN1rKpqit8UYv6uvf79GnbjyihCPE1UZu4CGRx7saZ68g396yjHFmzkzuBV69Hcr7TF2egTsEwVsRA3CETiqXVqet58MCM6tu"
 *  ["difficulty"]=>
 *  string(8) "61982809"
 *  ["argon"]=>
 *  string(68) "$SfghIBNSHoOJDlMthVcUtg$WTJMrQWHHqDA6FowzaZJ+O9JC8DPZTjTxNE4Pj/ggwg"
 *  ["transactions"]=>
 *  int(0)
 * }
 */
    public function block($id){
        $id = san($id);
        $sql=OriginSql::getInstance();

        $res=$sql->select('block','*',1,array("id='".$id."'"),'',1);
        if ($res) {
            echo_array($res);
        }else{
            echo "block false";
        }
        
    }
 /**
 * @api {php util.php} check_address Check-Address
 * @apiName check_address
 * @apiGroup UTIL
 * @apiDescription Checks a specific address
 *
 * @apiParam {text} arg2 address
 *
 * @apiExample {cli} Example usage:
 * php util.php check_address 4khstc1AknzDXg8h2v12rX42vDrzBaai6Rz53mbaBsghYN4DnfPhfG7oLZS24Q92MuusdYmwvDuiZiuHHWgdELLR
 *
 * @apiSuccessExample {text} Success-Response:
 * 'true' or 'false'
 */
    public function check_address($dst) {
        $dst = trim($dst);
        $sql=OriginSql::getInstance();

        $res=$sql->select('acc','*',1,array("id='".$dst."'"),'',1);
        if ($res) {
            echo "true\n";
        }else{
            echo "false\n";
        }
    }
 /**
 * @api {php util.php} check_alias Check_alias
 * @apiName check_alias
 * @apiGroup UTIL
 * @apiDescription Checks a specific alias
 *
 * @apiParam {text} arg2 alias
 *
 * @apiExample {cli} Example usage:
 * php util.php check_address abcde
 *
 * @apiSuccessExample {text} Success-Response:
 * 'true' or 'false'
 */
    public function check_alias($alias) {
        $alias = trim($alias);
        $sql=OriginSql::getInstance();

        $res=$sql->select('acc','*',1,array("alias='".$alias."'"),'',1);
        if ($res) {
            echo "true\n";
        }else{
            echo "false\n";
        }
    }
/**
 * @api {php util.php} get-address Get-Address
 * @apiName get-address
 * @apiGroup UTIL
 * @apiDescription Converts a public key into an address
 *
 * @apiParam {text} arg2 public key
 *
 * @apiExample {cli} Example usage:
 * php util.php get-address PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwQr8cE5s6APWAE1SWAmH6NM1nJTryBURULEsifA2hLVuW5GXFD1XU6s6REG1iPK7qGaRDkGpQwJjDhQKVoSVkSNp
 *
 * @apiSuccessExample {text} Success-Response:
 * 5WuRMXGM7Pf8NqEArVz1NxgSBptkimSpvuSaYC79g1yo3RDQc8TjVtGH5chQWQV7CHbJEuq9DmW5fbmCEW4AghQr
 */
    public function get_address($public_key){
        $public_key = trim($public_key);
        if (strlen($public_key) < 32) {
            die("Invalid public key");
        }
        $Account=Accountinc::getInstance();

        print($Account->get_address_from_public_key($public_key));
    }
/**
 * @api {php util.php} clean_fails Clean_fails
 * @apiName clean_fails
 * @apiGroup UTIL
 * @apiDescription Removes all the peers from blacklist
 *
 * @apiExample {cli} Example usage:
 * php util.php clean_fails
 *
 */
    public function clean_fails(){
        $sql=OriginSql::getInstance();

        $res=$sql->select('peer','id',0,array(),'',0);
        foreach ($res as $value) {
            $sql->update('peer',array('blacklisted'=>0,'fails'=>0,'stuckfail'=>0),array("id=".$value['id']));
        }
        echo "All the peers have been removed from the blacklist\n";
    }

    //$modify='check' 如果发现数据不对 自动进行update操作
    public function resync_accounts($modify='uncheck'){
        if ($modify==='') {
            $modify='uncheck';
        }
        // resyncs the balance on all accounts
        if (file_exists("tmp/sanity-lock")) {
            die("Sanity running. Wait for it to finish");
        }
        touch("tmp/sanity-lock");

        $sql=OriginSql::getInstance();
        // lock table to avoid race conditions on blocks
        $sql->lock_tables('blocks,accounts,transactions,mempool');
        $res=$sql->select('acc','*',0,array(),'',0);
        foreach ($res as $x) {
            $alias=$x['alias'];
            if (empty($alias)) {$alias="A";}
            //
            $rec=$sql->sum('trx','val',["(dst='".$x['id']."' or dst='".$alias."')","version=1","version=2"]);
            if ($rec==false) {
                $rec=0;
            }
            //
            $spent=$sql->sum('trx',['val','fee'],["public_key='".$x['public_key']."'","version=1","version=2"]);
            if ($spent==false) {
                $spent=0;
            }
            //100
            $mn_start_coun=$sql->select('trx','*',2,array("public='".$x['public_key']."'","version=100"),'',1);
            if ($mn_start_coun==false) {
                $mn_start_coun=0;
            }
            //103
            $mn_end_coun=$sql->select('trx','*',2,array("public='".$x['public_key']."'","version=103"),'',1);
            if ($mn_end_coun==false) {
                $mn_end_coun=0;
            }


            $balance=round($rec-$spent-($mn_start_coun*10000)+($mn_end_coun*10000), 8);
            if ($x['balance']!=$balance) {
                echo "rec: $rec, spent: $spent, bal: ".$x['balance'].", should be: ".$balance." - ".$x['id']." ".$x['public_key']."\n";
                if (trim($modify)=='check') {
                $sql->update('acc',array("balance=".$balance),array("id='".$x['id']."'"));
                }
            }
        }
        $sql->unlock_tables();
        echo "All done";
        unlink("tmp/sanity-lock"); 
    }

    public function compare_blocks($hostname,$limit,$dump='dump'){
        if ($dump==='') {
            $dump='dump';
        }
        $block=Blockinc::getInstance();
        $sql=OriginSql::getInstance();

        $current=$block->current();
        $peer=trim($hostname);
        $limit=intval($limit);
        if ($limit<=0) {
            $limit=100;
        }
        for ($i=$current['height']-$limit;$i<=$current['height'];$i++) {
            $data=peer_post($peer."/peer.php?q=getBlock", ["height" => $i]);
            if ($data==false) {
                continue;
            }
            $noour=$data['data'];
            $our=$sql->select('block','*',1,array("height=".$i),'',1);

            sort($our);
            sort($noour);
            if (!array_diff($our, $noour) && !array_diff($noour, $our)) {
                
            }else{
                echo "Failed block -> $i\n";
                if (trim($dump)=="dump") {
                    echo "\n\n  ---- Internal ----\n\n";
                    echo_array($our);
                    echo "\n\n  ---- External ----\n\n";
                    echo_array($noour);
                }
            }
        }
    }

    public function compare_accounts_balance($hostname){
        $peer=trim($hostname);

        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','id,balance',0,array(),'',0);

        foreach ($res as $x) {
            $data=peer_post($peer."/peer.php?q=getBalance", ["address" => $x['id']]);
            if ($data==false) {
                continue;
            }
            $data=number_format($data,8);
            $ourdata=number_format($x['balance'],8);

            if ($data!=$ourdata) {
                echo $x['id']."\t\t".$ourdata."\t".$data."\n";
            }
        }
    }

    public function version(){
        echo '\n\n'.$this->info['version'].'\n\n';
    }


    public function sendblock($hostname,$height){
        $peer=trim($hostname);
        $height=intval($height);


        $block=Blockinc::getInstance();
        $data = $block->export_for_other_peers("", $height);

        
        if($data===false){
            die("Could not find this block");
        }
        $response = peer_post($peer."/peer.php?q=submitBlock", $data, 60, true);
        echo_array($response);
    }

    public function check_node_blocks($hostname,$start_height){
        $peer=trim($hostname);
        $start_height=intval($start_height);
        if ($start_height<=0) {
            $start_height=1;
        }
        $block=Blockinc::getInstance();
        
        $last=peer_post($peer."/peer.php?q=currentBlock");

        $b=peer_post($peer."/peer.php?q=getBlock",["height"=>$start_height]);

        for ($i = $start_height+1; $i <= $last['data']['height']; $i++) {
            $c=peer_post($peer."/peer.php?q=getBlock",["height"=>$i]);
            if (!$block->mine(
                $c['mn_public_key'],
                $c['data']['nonce'],
                $c['data']['argon'],
                $c['data']['difficulty'],
                $b['data']['id'],
                $b['data']['height'],
                $c['data']['date']
            )) {
                print("Invalid block detected. ".$c['data']['height']." - ".$c['data']['id']."\n");
                break;
            } 
            echo "Block $i -> ok\n";
            $b=$c;
        }
    }


}
if (!isset($argv[1])) {
    exit;
}
$cmd = trim($argv[1]);
$util=new Util();
switch ($cmd) {
    case 'clean':
        $util->clean();
        break;
    case 'pop':
        if (!isset($argv[2])) {
            exit;
        }
        $number=$argv[2];
        $util->pop($number);
        break;
    case 'block_time':
        $util->block_time();
        break;
    case 'topeer':
        if (!isset($argv[2])) {
            exit;
        }
        $hostname=$argv[2];
        $util->topeer($hostname);
        break;
    case 'current':
        $util->current($hostname);
        break;
    case 'blocks_id':
        $start_height=$argv[2];
        $limit=$argv[3];
        $util->blocks_id($start_height,$limit);
        break;
    case 'recheck_blocks':
        if (!isset($argv[2]) or !isset($argv[3)) {
            exit;
        }
        $start_height=$argv[2];
        $end_height=$argv[3];
        $util->recheck_blocks($start_height,$end_height);
        break;
    case 'print_peers':
        $util->print_peers();
        break;
    case 'mempool_count':
        $util->mempool_count();
        break;
    case 'delete_peer_hostname':
        if (!isset($argv[2])) {
            exit;
        }
        $hostname=$argv[2];
        $util->delete_peer_hostname($hostname);
        break;
    case 'delete_peer_ip':
        if (!isset($argv[2])) {
            exit;
        }
        $ip=$argv[2];
        $util->delete_peer_hostname($ip);
        break;
    case 'recheck_peers':
        $util->recheck_peers();
        break;
    case 'peers_current':
        $util->peers_current();
        break;
    case 'balance':
        if (!isset($argv[2])) {
            exit;
        }
        $address=$argv[2];
        $util->balance($address);
        break;
    case 'block':
        if (!isset($argv[2])) {
            exit;
        }
        $id=$argv[2];
        $util->block($id);
        break;
    case 'check_address':
        if (!isset($argv[2])) {
            exit;
        }
        $dst=$argv[2];
        $util->check_address($dst);
        break;
    case 'check_alias':
        if (!isset($argv[2])) {
            exit;
        }
        $alias=$argv[2];
        $util->check_alias($alias);
        break;
    case 'get_address':
        if (!isset($argv[2])) {
            exit;
        }
        $public_key=$argv[2];
        $util->get_address($public_key);
        break;
    case 'clean_fails':
        $util->clean_fails();
        break;
    case 'resync_accounts':
        $modify=$argv[2];   //modify=uncheck or modify=check
        $util->resync_accounts($modify);
        break;
    case 'compare_blocks':
        if (!isset($argv[2]) or !isset($argv[3)) {
            exit;
        }
        $hostname=$argv[2];
        $limit=$argv[3];
        $dump=$argv[4]; //dump or undump/nodump
        $util->compare_blocks($hostname,$limit,$dump);
        break;
    case 'compare_accounts_balance':
        if (!isset($argv[2])) {
            exit;
        }
        $hostname=$argv[2];
        $util->compare_accounts_balance($hostname);
        break;
    case 'version':
        $util->version();
        break;
    case 'sendblock':
        if (!isset($argv[2]) or !isset($argv[3)) {
            exit;
        }
        $hostname=$argv[2];
        $height=$argv[3];
        $util->sendblock($hostname,$height)
        break;
    case 'check_node_blocks':
        if (!isset($argv[2]) or !isset($argv[3)) {
            exit;
        }
        $hostname=$argv[2];
        $start_height=$argv[3];
        $util->check_node_blocks($hostname,$start_height)
        break;
    default:
        # code...
        break;
}






// $util=new Util();
// if(method_exists($util,$cmd)){
//     $util->$cmd();
// }else{
//     echo 'unknow apiName';
// }
// $this->db->close();