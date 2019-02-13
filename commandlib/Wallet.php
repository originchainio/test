<?php
/**
 * 
 */
// version: 20190212 test
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
    public function abandontransaction($txid){

    }
    public function bumpfee($txid){

    }
    public function createwallet(){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->generate_account();
    }
    public function getpublickeybyaddress($address){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->get_public_key_from_address($address);
    }
    public function getpublickeybyalias($alias){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->get_public_key_from_alias($alias);
    }
    public function getaliasbyaddress($address){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->get_alias_frome_address($address);
    }
    public function getaliasbypublickey($publickey){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->get_alias_frome_publickey($publickey);
    }
    public function getaddressesbyalias($alias){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->get_address_from_alias($alias);
    }
    public function getaddressesbypublickey($publickey){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->get_address_from_publickey($publickey);
    }
    public function getaddressinfo($address){
        $sql=OriginSql::getInstance();
        return $sql->select('acc','*',1,array("id='".$address."'"),'',1);
    }
    public function getbalance($address_or_publickey){
        $Accountinc=Accountinc::getInstance();
        $res=$Accountinc->get_balance_from_address($address_or_publickey);
        if ($res) {
            return $res;
        }
        $res=$Accountinc->get_balance_from_public_key($address_or_publickey);
        return $res;
    }

    public function listlockunspent($publickey){
        $sql=OriginSql::getInstance();
        return $sql->select('mem','id',0,array("public_key='".$publickey."'"),'',0);
    }
    public function sendtoaddress($fromaddress,$toaddress,$privatekey,$amount){
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return false;
        }
        $frompublickey=$this->getpublickeybyaddress($fromaddress);
        if (!$frompublickey) {
            return false;
        }

        $mem=Mempoolinc::getInstance();
        $fee=$amount*0.005;
        $tt=time();
        $signature=$mem->signature($toaddress,$amount,$fee,1,'',$tt,$frompublickey, $privatekey);
        $res=$mem->check(array(
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
            return false;
        }
        $res=$mem->add_mempool($current['height']+1,$toaddress,$amount,$fee,$signature,1,'',$frompublickey,$tt,'local');
        if ($res) {
            return true;
        }else{
            return false;
        }
    }
    public function sendtoalias($fromaddress,$alias,$privatekey,$amount){
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return false;
        }
        $frompublickey=$this->getpublickeybyaddress($fromaddress);
        if (!$frompublickey) {
            return false;
        }

        $mem=Mempoolinc::getInstance();
        $fee=$amount*0.005;
        $tt=time();
        $signature=$mem->signature($alias,$amount,$fee,2,'',$tt,$frompublickey, $privatekey);
        $res=$mem->check(array(
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
            return false;
        }
        $res=$mem->add_mempool($current['height']+1,$alias,$amount,$fee,$signature,2,'',$frompublickey,$tt,'local');
        if ($res) {
            return true;
        }else{
            return false;
        }
    }

    public function checkalias($alias){
        $Accountinc=Accountinc::getInstance();
        return $Accountinc->alias_alive_from_alias($alias);
    }
    public function registalias($fromaddress,$alias){
        $block=Blockinc::getInstance();
        $current=$block->current();
        if (!$current) {
            return false;
        }
        $frompublickey=$this->getpublickeybyaddress($fromaddress);
        if (!$frompublickey) {
            return false;
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
            return false;
        }


        $res=$mem->add_mempool($current['height']+1,$fromaddress,0,$fee,$signature,3,$alias,$frompublickey,$tt,'local');
        if ($res) {
            return true;
        }else{
            return false;
        }
    }
}

?>