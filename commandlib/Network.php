<?php
/**
 * 
 */
// version: 20190225
class Network extends base{
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

    //"node" "add|remove|check"
    public function addnode($mode='cli',$node,$type='add'){
    // Array
    // (
    //     [result] => ok
    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        switch ($type) {
            case 'add':
                $sql=OriginSql::getInstance();
                if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)==false) {
                    $res=$sql->add('peer',array('hostname'=>$node,'blacklisted'=>0,'ping'=>0,'reserve'=>1,'ip'=>md5($node),'fails'=>0,'stuckfail'=>0));
                    if ($res) {
                        return array('result' => 'ok', 'error'=>'');
                    }
                }
                return array('result' => '', 'error'=>'add fail');
                break;
            case 'remove':
                $sql=OriginSql::getInstance();
                if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)!=false) {
                    $res=$sql->delete('peer',array("hostname='".$node."'"));
                    if ($res) {
                        return array('result' => 'ok', 'error'=>'');
                    }else{
                        return array('result' => '', 'error'=>'delete remove fail');
                    }
                }else{
                    return array('result' => '', 'error'=>'remove fail');
                }
                
                break;
            case 'check':
                $Peerinc=Peerinc::getInstance();
                if ($Peerinc->check($node)==false) {
                    return array('result' => '', 'error'=>'check fail');
                }
                return array('result' => 'ok', 'error'=>'');
                break;
            default:
                return array('result' => '', 'error'=>'method fail');
                break;
        }
    }
    public function checknodeping($mode='cli',$node=''){
    //node : http://192.168.1.37
    // Array
    // (
    //     [result] => Array
    //         (
    //             [node] => http://192.168.1.37
    //             [result] => ok
    //         )

    //     [error] =>
    // )

    //node : ''
    // Array
    // (
    //     [result] => Array
    //         (
    //             [0] => Array
    //                 (
    //                     [node] => http://192.168.1.37
    //                     [result] => ok
    //                 )

    //             [1] => Array
    //                 (
    //                     [node] => http://192.168.1.38
    //                     [result] => error
    //                 )

    //             [2] => Array
    //                 (
    //                     [node] => http://192.168.1.40
    //                     [result] => error
    //                 )

    //         )

    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $Peerinc=Peerinc::getInstance();
        if ($node=='') {
            $result=[];
            $sql=OriginSql::getInstance();
            $res=$sql->select('peer','*',0,array(),'',0);
            foreach ($res as $x) {
                $a = $Peerinc->peer_post($x['hostname']."/peer.php?q=ping");
                if ($a == "success") {
                    $s['node']=$x['hostname'];
                    $s['result']='ok';
                    $result[]=$s;
                } else {
                    $s['node']=$x['hostname'];
                    $s['result']='error';
                    $result[]=$s;
                }
            }
        }else{
            $result=[];
            $a = $Peerinc->peer_post($node."/peer.php?q=ping");
                if ($a == "success") {
                    $result['node']=$node;
                    $result['result']='ok';
                } else {
                    $result['node']=$node;
                    $result['result']='error';
                }
        }
        return array('result' => $result, 'error'=>'');
    }
    public function clearbanned($mode='cli'){
    // Array
    // (
    //     [result] => ok
    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->delete_fails_peer()===false) {
            return array('result' => '', 'error'=>'fail');
        }
        return array('result' => 'ok', 'error'=>'');
    }

    public function disconnectnode($mode='cli',$node){
    // Array
    // (
    //     [result] => ok
    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)) {
            $res=$sql->update('peer',array('reserve' => 0),array("hostname='".$node."'"));
            if ($res) {
                return array('result' => 'ok', 'error'=>'');
            }
        }
        return array('result' => '', 'error'=>'fail');
    }
    public function getaddednodeinfo($mode='cli',$node){
    // Array
    // (
    //     [result] => Array
    //         (
    //             [status] => ok
    //             [data] => Array
    //                 (
    //                     [id] => qg5WUqu2k...
    //                     [generator] => 2j7bge...
    //                     [height] => 532
    //                     [date] => 1550493762
    //                     [nonce] => BuRXML...
    //                     [signature] => iKx1CJM...
    //                     [difficulty] => 1197253762101
    //                     [argon] => $ejFVNjl...
    //                     [transactions] => 1
    //                 )

    //             [trx_data] => Array
    //                 (
    //                     [0] => Array
    //                         (
    //                             [id] => 5Qu914...
    //                             [height] => 532
    //                             [dst] => 2j7bgeD...
    //                             [val] => 0.65000000
    //                             [fee] => 0.00000000
    //                             [signature] => 381yX...
    //                             [version] => 0
    //                             [message] =>
    //                             [date] => 1550493762
    //                             [public_key] => PZ8Tyr...
    //                             [block] => qg5WUqu2kk...
    //                         )

    //                 )

    //             [miner_public_key] => PZ8Tyr4Nx8...
    //             [miner_reward_signature] => 381yX...
    //             [mn_public_key] =>
    //             [mn_reward_signature] =>
    //             [from_host] => http://192.168.1.37
    //         )

    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->ping($node,5)==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            $res=$Peerinc->peer_post($node."/peer.php?q=currentBlock", [], 5);
            if ($res==false) {
                return array('result' => '', 'error'=>'fail');
            }else{
                return array('result' => $res, 'error'=>'');
            }
        }
    }
    public function getconnectioncount($mode='cli'){
    // Array
    // (
    //     [result] => 2
    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        $all_count=$sql->select('peer','*',2,array("reserve=1"),'',1);
        if ($all_count>=$this->config['max_peer']) {
            return array('result' => $this->config['max_peer'], 'error'=>'');
        }else{
            return array('result' => $all_count, 'error'=>'');
        }
    }
    public function getpeerinfo($mode='cli'){
    // Array
    // (
    //     [result] => Array
    //         (
    //             [0] => Array
    //                 (
    //                     [id] => 3
    //                     [hostname] => http://192.168.1.37
    //                     [blacklisted] => 0
    //                     [ping] => 0
    //                     [reserve] => 1
    //                     [ip] => 64e2c0c9724b5e8f9531b236d586ca3c
    //                     [fails] => 0
    //                     [stuckfail] => 0
    //                 )

    //             [1] => Array
    //                 (
    //                     [id] => 5
    //                     [hostname] => http://192.168.1.40
    //                     [blacklisted] => 0
    //                     [ping] => 0
    //                     [reserve] => 1
    //                     [ip] => 7497a4833b9545e3164ec66f3ee77014
    //                     [fails] => 0
    //                     [stuckfail] => 0
    //                 )

    //         )

    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',0,array("reserve=1"),'',0);
        if ($res) {
            return array('result' => $res, 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'fail');
        }
    }
    public function listbanned($mode='cli'){
    // Array
    // (
    //     [result] => Array
    //         (
    //             [0] => Array
    //                 (
    //                     [id] => 5
    //                     [hostname] => http://192.168.1.40
    //                     [blacklisted] => 0
    //                     [ping] => 0
    //                     [reserve] => 0
    //                     [ip] => 7497a4833b9545e3164ec66f3ee77014
    //                     [fails] => 101
    //                     [stuckfail] => 20
    //                 )

    //         )

    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',0,array("fails>100 or stuckfail>100"),'',0);
        if ($res!==false) {
            return array('result' => $res, 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'fail');
        }
    }
    public function ping($mode='cli',$node){
    // Array
    // (
    //     [result] => ok
    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->ping($node,5)==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => 'ok', 'error'=>'');
        }
    }
    public function getversion($mode='all'){
    // Array
    // (
    //     [result] => Version 1.0 Build 20181209
    //     [error] =>
    // )

        return array('result' => $this->info['version'], 'error'=>'');
    }
}

?>