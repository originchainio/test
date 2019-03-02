<?php
/*
The MIT License (MIT)
Copyright (C) 2019 OriginchainDev

originchain.net

　　Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the Software
is furnished to do so, subject to the following conditions:
　　
　　The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

// version: 20190225
class Accountinc extends base{
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
    // inserts just the account with public key address
    public function add_account($public_key,$address, $block_hash){
        $public_key=san($public_key);
        $address=san($address);
        $block_hash=san($block_hash);

        $sql=OriginSql::getInstance();
        $res=$sql->add('acc',array(
                            'id'=>$address,
                            'public_key'=>$public_key,
                            'block'=>$block_hash,
                            'balance'=>0,
                            'alias'=>NULL
        ));
        if ($res) {
            //$this->log('account.inc->add_account true',0,true);
            return true;
        }else{
            $this->log('account.inc->add_account false',0,true);
            return false;
        }
    }
    // Delete blocks based on Hash
    public function delete_form_block_hash($block_hash){
        $block_hash=san($block_hash);
        $sql=OriginSql::getInstance();

        $res=$sql->delete('acc',array("block='".$block_hash."'"));
        if ($res) {
            //$this->log('account.inc->delete_form_block_hash true',0,true);
            return true;
        }else{
            $this->log('account.inc->delete_form_block_hash false',0,true);
            return false;
        }
    }
    // inserts just the account with address
    public function add_account_from_address($address, $block_hash){
        $address=san($address);
        $block_hash=san($block_hash);
        $sql=OriginSql::getInstance();
        $res=$sql->add('acc',array(
                            'id'=>$address,
                            'public_key'=>'',
                            'block'=>$block_hash,
                            'balance'=>0,
                            'alias'=>NULL
        ));
        if ($res) {
            $this->log('account.inc->add_account_from_address true',0,true);
            return true;
        }else{
            $this->log('account.inc->add_account_from_address false',0,true);
            return false;
        }
    }

    // get Account's address from the public key
    public function get_address_from_public_key($public_key){
        $public_key=san($public_key);
        for ($i = 0; $i < 9; $i++) {
            $public_key = hash('sha512', $public_key, true);
        }
        $public_key = base58_encode($public_key);
        if (valid_base58($public_key)==true and valid_len($public_key,70,128)==true) {
            //$this->log('account.inc->get_address_from_public_key true',0,true);
            return $public_key;
        }else{
            $this->log('account.inc->get_address_from_public_key false',0,true);
            return false;
        } 
    }
    // Generate a new account
    // The account algorithm comes from arionum https://github.com/arionum/node
    public function generate_account(){
        $res=$this->generate_account_s();
        if ($res) {
            $address = $this->get_address_from_public_key($res['public_key']);
            //$this->log('account.inc->generate_account true',0,true);
            return ["address" => $address, "public_key" => $res['public_key'], "private_key" => $res['private_key']];
        }else{
            $this->log('account.inc->generate_account false',0,true);
            return false;
        }  
    }
    private function generate_account_s(){
        // using secp256k1 curve for ECDSA
        if ($this->config['openssl_cnf']!=NULL) {
            $args = [
                "curve_name"       => "secp256k1",
                "private_key_type" => OPENSSL_KEYTYPE_EC,
                'config'=>$this->config['openssl_cnf'],
            ];
        }else{
            $args = [
                "curve_name"       => "secp256k1",
                "private_key_type" => OPENSSL_KEYTYPE_EC,
            ];   
        }

        // generates a new key pair
        $res = openssl_pkey_new($args);
        // exports the private key encoded as PEM
        openssl_pkey_export($res, $private_key,null,$args);
        // converts the PEM to a base58 format
        $private_key = pem2coin($private_key);
        // exports the private key encoded as PEM
        $pubKey = openssl_pkey_get_details($res);
        // converts the PEM to a base58 format
        $public_key = pem2coin($pubKey['key']);
        return array('private_key'=>$private_key,'public_key'=>$public_key);
    }

    // check if an account already has an alias
    public function alias_alive_from_public_key($public_key){
        $public_key=san($public_key);
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','*',1,array('public_key="'.$public_key.'"'),'',1);
        if ($res['alias']=='' or $res['alias']==NULL) {
            //$this->log('account.inc->alias_alive_from_public_key true',0,true);
            return false;
        } else {
            $this->log('account.inc->alias_alive_from_public_key false',0,true);
            return true;
        }
    }
    // check if an account already has an alias
    public function alias_alive_from_alias($alias){
        $alias=san(strtolower($alias));
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','*',2,array('alias="'.$alias.'"'),'',1);
        if ($res!=0) {
            //$this->log('account.inc->alias_alive_from_alias true',0,true);
            return true;
        } else {
            $this->log('account.inc->alias_alive_from_alias false',0,true);
            return false;
        }
    }
    // Query whether publickey exists
    public function public_key_alive_from_public($public_key){
        $public_key=san($public_key);
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','*',2,array('public_key="'.$public_key.'"'),'',1);
        if ($res!=0) {
            //$this->log('account.inc->public_key_alive_from_public true',0,true);
            return true;
        } else {
            $this->log('account.inc->public_key_alive_from_public false',0,true);
            return false;
        }
    }
    // Query whether address exists
    public function address_alive_from_address($address){
        $address=san($address);
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','*',2,array('id="'.$address.'"'),'',1);
        if ($res!=0) {
            //$this->log('account.inc->address_alive_from_address true',0,true);
            return true;
        } else {
            $this->log('account.inc->address_alive_from_address false',0,true);
            return false;
        }
    }
    public function check_acc_pub_update_DB($public_key='',$address='',$block_hash){
        $public_key=san($public_key);
        $address=san($address);
        $block_hash=san($block_hash);
        
        if ($block_hash=='') {
            $this->log('block_hash is empty');
            return false;
        }
        $sql=OriginSql::getInstance();
        if ($public_key!='' and $address=='') {
            $address=$this->get_address_from_public_key($public_key);
            $address_true=$this->address_alive_from_address($address);
            $public_key_true=$this->public_key_alive_from_public($public_key);


            if ($public_key_true==false and $address_true==false) {
               return  $this->add_account($public_key,$address,$block_hash);
            }elseif($public_key_true==false and $address_true==true){
                return  $sql->update('acc',array('public_key'=>$public_key),array("id='".$address."'"));
            }elseif($public_key_true==true and $address_true==false){
                return  $sql->update('acc',array('id'=>$address),array("public_key='".$public_key."'"));
            }
            return true;
        }elseif($public_key=='' and $address==''){
            $this->log('account.inc->check_acc_pub_update_DB publickey and address is empty false',0,true);
            return false;
        }elseif($public_key!='' and $address!=''){
            if (valid_len($address,70,128)==false) {
                $this->log('account.inc->check_acc_pub_update_DB address len is fails false',0,true);
                return false;
            }
            $address1=$this->get_address_from_public_key($public_key);
            if ($address!=$address1) {
                $this->log('account.inc->check_acc_pub_update_DB address != publickey is address false',0,true);
                return false;
            }
            $address_true=$this->address_alive_from_address($address);
            $public_key_true=$this->public_key_alive_from_public($public_key);
            if ($public_key_true==false and $address_true==false) {
               return  $this->add_account($public_key,$address,$block_hash);
            }elseif($public_key_true==false and $address_true==true){
                return  $sql->update('acc',array('public_key'=>$public_key),array("id='".$address."'"));
            }elseif($public_key_true==true and $address_true==false){
                return  $sql->update('acc',array('id'=>$address),array("public_key='".$public_key."'"));
            }
            return true;
        }elseif($public_key=='' and $address!=''){
            if (valid_len($address,70,128)==false) {
                $this->log('account.inc->check_acc_pub_update_DB address len is fails false',0,true);
                return false;
            }
            $address_true=$this->address_alive_from_address($address);
            if ($address_true==false) {
                return $this->add_account_from_address($address, '');
            }
            return true;
        }else{
            $this->log('account.inc->check_acc_pub_update_DB fails fails false',0,true);
            return false;
        }
    }
    //get the account of an alias from database
    public function get_address_from_alias($alias){
        $alias_id=san(strtolower($alias));

        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','id',1,array('alias="'.$alias_id.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_address_from_alias true',0,true);
            return $res['id'];
        }else{
            $this->log('account.inc->get_address_from_alias false',0,true);
            return false;
        }
        
    }
    public function get_address_from_publickey_db($publickey){
        $publickey=san($publickey);

        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','id',1,array('public_key="'.$publickey.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_address_from_publickey_db true',0,true);
            return $res['id'];
        }else{
            $this->log('account.inc->get_address_from_publickey_db false',0,true);
            return false;
        }
        
    }
    //get the alias of an account from database
    public function get_alias_frome_address($address){
        $address=san($address);

        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','alias',1,array('id="'.$address.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_alias_frome_address true',0,true);
            return $res['alias'];
        }else{
            $this->log('account.inc->get_alias_frome_address false',0,true);
            return false;
        }
        
    }
    public function get_alias_frome_publickey($publickey){
        $address=san($address);

        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','alias',1,array('public_key="'.$publickey.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_alias_frome_publickey true',0,true);
            return $res['alias'];
        }else{
            $this->log('account.inc->get_alias_frome_publickey false',0,true);
            return false;
        }
        
    }
    public function alias_check_blacklist($alias){
        $this->log('account.inc->alias_check_blacklist',0,true);
        $alias=san(strtoupper($alias));
        $banned=["MERCURY","DEVS","DEVELOPMENT", "MARKETING", "MERCURY80","DEVARO", "DEVELOPER","DEVELOPERS","ARODEV", "DONATION","MERCATOX", "OCTAEX", "MERCURY", "ARIONUM", "ESCROW","OKEX","BINANCE","CRYPTOPIA","HUOBI","ITFINEX","HITBTC","UPBIT","COINBASE","KRAKEN","BITSTAMP","BITTREX","POLONIEX"];
            if (in_array($alias, $banned)) {
                return true;
            }else{
                return false;
            }

    }

    // returns the current account balance
    public function get_balance_from_address($address){
        $address=san($address);
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','balance',1,array('id="'.$address.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_balance_from_address true',0,true);
            return $res['balance'];
        }else{
            $this->log('account.inc->get_balance_from_address false',0,true);
            return false;
        }
    }
    public function get_balance_from_public_key($public_key){
        $public_key=san($public_key);
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','balance',1,array('public_key="'.$public_key.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_balance_from_public_key true',0,true);
            return $res['balance'];
        }else{
            $this->log('account.inc->get_balance_from_public_key false',0,true);
            return false;
        }
    }

    // get the public key for a specific account from database
    public function get_public_key_from_address($address){
        $address=san($address);
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','public_key',1,array('id="'.$address.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_public_key_from_address true',0,true);
            return $res['public_key'];
        }else{
            $this->log('account.inc->get_public_key_from_address false',0,true);
            return false;
        }

    }
    public function get_public_key_from_alias($alias){
        $alias=san($alias);
        $sql=OriginSql::getInstance();
        $res=$sql->select('acc','public_key',1,array('alias="'.$alias.'"'),'',1);
        if ($res) {
            //$this->log('account.inc->get_public_key_from_alias true',0,true);
            return $res['public_key'];
        }else{
            $this->log('account.inc->get_public_key_from_alias false',0,true);
            return false;
        }

    }



}
