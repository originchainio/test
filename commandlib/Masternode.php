<?php
/**
 * 
 */
// version: 20190225
class Masternode extends base{
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
 
    public function registermasternode($mode='cli',$privatekey){
    // Array
    // (
    //     [result] => ok
    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        if (!valid_len($this->config['masternode_public_key']) and ($this->config['masternode']==true)) {
            return array('result' => '', 'error'=>'fail');
        }
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return array('result' => '', 'error'=>'current fail');
        }

        $Accountinc=Accountinc::getInstance();
        $toaddress=$Accountinc->get_address_from_publickey_db($this->config['masternode_public_key']);
        if (!$toaddress) {
            return array('result' => '', 'error'=>'masternode_public_key fail');
        }

        $mem=Mempoolinc::getInstance();
        $fee=0;
        $amount=0;
        $tt=time();
        $signature=$mem->signature($toaddress,$amount,$fee,100,$this->config['hostname'],$tt,$this->config['masternode_public_key'], $privatekey);
        $hash=$mem->hasha($toaddress,$amount,$fee,$signature,100,$this->config['hostname'],$tt,$this->config['masternode_public_key']);
        $res=$mem->check(array(
            'id' => $hash,
            'height' => $current['height']+1,
            'dst' => $toaddress,
            'val' => $amount,
            'fee' => $fee,
            'signature' => $signature,
            'version' => 100,
            'message' => $this->config['hostname'],
            'date' => $tt,
            'public_key' => $this->config['masternode_public_key'],
            'peer' => 'local',
             ));
        if (!$res) {
            return array('result' => '', 'error'=>'mem check fail');
        }
        $res=$mem->add_mempool($current['height']+1,$toaddress,$amount,$fee,$signature,100,$this->config['hostname'],$this->config['masternode_public_key'],$tt,'local');
        if ($res) {
            return array('result' => 'ok', 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'add mem fail');
        }
    }
    public function cancelmasternode($mode='cli',$privatekey){
    // Array
    // (
    //     [result] => ok
    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        if (!valid_len($this->config['masternode_public_key'])) {
            return array('result' => '', 'error'=>'fail');
        }
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return array('result' => '', 'error'=>'current fail');
        }

        $Accountinc=Accountinc::getInstance();
        $toaddress=$Accountinc->get_address_from_publickey_db($this->config['masternode_public_key']);
        if (!$toaddress) {
            return array('result' => '', 'error'=>'masternode_public_key fail');
        }

        $mem=Mempoolinc::getInstance();
        $fee=0;
        $amount=0;
        $tt=time();
        $signature=$mem->signature($toaddress,$amount,$fee,103,$this->config['hostname'],$tt,$this->config['masternode_public_key'], $privatekey);
        $hash=$mem->hasha($toaddress,$amount,$fee,$signature,103,$this->config['hostname'],$tt,$this->config['masternode_public_key']);
        $res=$mem->check(array(
            'id' => $hash,
            'height' => $current['height']+1,
            'dst' => $toaddress,
            'val' => $amount,
            'fee' => $fee,
            'signature' => $signature,
            'version' => 103,
            'message' => $this->config['hostname'],
            'date' => $tt,
            'public_key' => $this->config['masternode_public_key'],
            'peer' => 'local',
             ));
        if (!$res) {
            return array('result' => '', 'error'=>'mem check fail');
        }
        $res=$mem->add_mempool($current['height']+1,$toaddress,$amount,$fee,$signature,103,$this->config['hostname'],$this->config['masternode_public_key'],$tt,'local');
        if ($res) {
            return array('result' => 'ok', 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'add mem fail');
        }
    }
    public function listmasternode($mode='cli'){
    // Array
    // (
    //     [result] => Array
    //         (
    //             [0] => Array
    //                 (
    //                     [public_key] => PZ8Tyr4Nx8MHsRAGM....
    //                     [height] => 811
    //                     [ip] => http://192.168.1.36
    //                     [last_won] => 845
    //                     [blacklist] => 845
    //                     [fails] => 0
    //                     [status] => 1
    //                 )

    //         )

    //     [error] =>
    // )

        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        $res=$sql->select('mn','*',0,array("status=1"),'',0);
        if ($res) {
            return array('result' => $res, 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'fail');
        }
    }
}

?>