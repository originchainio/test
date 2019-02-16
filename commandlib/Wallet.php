<?php
/**
 * 
 */
// version: 20190216 test
class Wallet extends base{
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
    public function createwallet($mode='all'){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->generate_account();
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function getpublickeybyaddress($mode='all',$address){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->get_public_key_from_address($address);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function getpublickeybyalias($mode='all',$alias){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->get_public_key_from_alias($alias);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function getaliasbyaddress($mode='all',$address){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->get_alias_frome_address($address);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function getaliasbypublickey($mode='all',$publickey){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->get_alias_frome_publickey($publickey);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function getaddressesbyalias($mode='all',$alias){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->get_address_from_alias($alias);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function getaddressesbypublickey($mode='all',$publickey){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->get_address_from_publickey($publickey);
    }
    public function getaddressinfo($mode='all',$address){
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','*',1,array("id='".$address."'"),'',1);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function getbalance($mode='all',$address_or_publickey){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->get_balance_from_address($address_or_publickey);
        if ($res) {
            return array('result' => $res, 'error'=>'');
        }
        $res=$Accountinc->get_balance_from_public_key($address_or_publickey);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }

    public function listlockunspent($mode='all',$publickey){
        $sql=OriginSql::getInstance();
        $res=$sql->select('mem','id',0,array("public_key='".$publickey."'"),'',0);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => $res, 'error'=>'');
        }
    }
    public function sendtoaddress($mode='all',$fromaddress,$toaddress,$privatekey,$amount){
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return array('result' => '', 'error'=>'current fail');
        }
        $Accountinc=Accountinc::getInstance();
        $frompublickey=$Accountinc->get_public_key_from_address($fromaddress);
        if (!$frompublickey) {
            return array('result' => '', 'error'=>'frompublic fail');
        }

        $mem=Mempoolinc::getInstance();
        $fee=$amount*0.005;
        $tt=time();
        $signature=$mem->signature($toaddress,$amount,$fee,1,'',$tt,$frompublickey, $privatekey);
        $hash=$mem->hasha($toaddress,$amount,$fee,$signature,1,'',$tt,$frompublickey);


        $res=$mem->check(array(
            'id' => $hash,
            'height' => $current['height']+1,
            'dst' => $toaddress,
            'val' => $amount,
            'fee' => $fee,
            'signature' => $signature,
            'version' => 1,
            'message' => '',
            'date' => $tt,
            'public_key' => $frompublickey,
            'peer' => 'local',
             ));
        if (!$res) {
            return array('result' => '', 'error'=>'mem check fail');
        }
        $res=$mem->add_mempool($current['height']+1,$toaddress,$amount,$fee,$signature,1,'',$frompublickey,$tt,'local');
        if ($res) {
            return array('result' => 'ok', 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'add mem fail');
        }
    }
    public function sendtoalias($mode='all',$fromaddress,$alias,$privatekey,$amount){
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return array('result' => '', 'error'=>'current fail');
        }
        $Accountinc=Accountinc::getInstance();
        $frompublickey=$Accountinc->get_public_key_from_address($fromaddress);
        if (!$frompublickey) {
            return array('result' => '', 'error'=>'frompublic fail');
        }

        $mem=Mempoolinc::getInstance();
        $fee=$amount*0.005;
        $tt=time();
        $signature=$mem->signature($alias,$amount,$fee,2,'',$tt,$frompublickey, $privatekey);
        $hash=$mem->hasha($alias,$amount,$fee,$signature,2,'',$tt,$frompublickey);

        $res=$mem->check(array(
            'id' => $hash,
            'height' => $current['height']+1,
            'dst' => $alias,
            'val' => $amount,
            'fee' => $fee,
            'signature' => $signature,
            'version' => 2,
            'message' => '',
            'date' => $tt,
            'public_key' => $frompublickey,
            'peer' => 'local',
             ));
        if (!$res) {
            return array('result' => '', 'error'=>'mem check fail');
        }
        $res=$mem->add_mempool($current['height']+1,$alias,$amount,$fee,$signature,2,'',$frompublickey,$tt,'local');
        if ($res) {
            return array('result' => 'ok', 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'add mem fail');
        }
    }

    public function checkalias($mode='all',$alias){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->alias_alive_from_alias($alias);
        if ($res==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => 'ok', 'error'=>'');
        }
    }
    public function registalias($mode='all',$fromaddress,$alias){
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return array('result' => '', 'error'=>'current fail');
        }
        $Accountinc=Accountinc::getInstance();
        $frompublickey=$Accountinc->get_public_key_from_address($fromaddress);
        if (!$frompublickey) {
            return array('result' => '', 'error'=>'frompublic fail');
        }

        $mem=Mempoolinc::getInstance();
        $fee=0;
        $tt=time();
        $signature=$mem->signature($fromaddress,0,$fee,3,$alias,$tt,$frompublickey, $privatekey);

        $res=$mem->check(array(
            'height' => $current['height']+1,
            'dst' => $fromaddress,
            'val' => 0,
            'fee' => $fee,
            'signature' => $signature,
            'version' => 3,
            'message' => $alias,
            'date' => $tt,
            'public_key' => $frompublickey,
            'peer' => 'local',
             ));
        if (!$res) {
            return array('result' => '', 'error'=>'mem check fail');
        }


        $res=$mem->add_mempool($current['height']+1,$fromaddress,0,$fee,$signature,3,$alias,$frompublickey,$tt,'local');
        if ($res) {
            return array('result' => 'ok', 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'add mem fail');
        }
    }
}

?>