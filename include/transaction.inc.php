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

// version: 20190227
class Transactioninc extends base{
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
    public function get_id_istrue_from_id($id){
        $sql=OriginSql::getInstance();
        $res=$sql->select('transactions','*',2,array('id="'.$id.'"'),'',1);
        if ($res!=0) {
            return true;
        }else{
            $this->log('Transaction.inc->get_id_istrue_from_id false',0,true);
            return false;
        }
    }
    // reverse and remove all transactions from a block
    public function delete_transactions_to_mempool_from_block($block_hash){
        $sql=OriginSql::getInstance();
        $Account=Accountinc::getInstance();

        $res0=$sql->select('transactions','*',0,array('block="'.$block_hash.'"'),'',0);
        foreach ($res0 as $value) {
            $public_key=$value['public_key'];
            $src=$Account->get_address_from_public_key($public_key);
            //
            switch ($value['version']) {
                case 0:
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("id='".$value['dst']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:0 miner reward false',1,true);
                        //return false;
                    }
                    break;
                case 1:
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("id='".$value['dst']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:1 account balance return1 false',1,true);
                        //return false;
                    }
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+$value['val']+$value['fee']),array("id='".$src."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:1 account balance return2 false',1,true);
                        //return false;
                    }
                    break;
                case 2:
                    $acc=$sql->select('accounts','balance',1,array("alias='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("alias='".$value['dst']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:2 alias balance return1 false',1,true);
                        //return false;
                    }
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+$value['val']+$value['fee']),array("id='".$src."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:2 alias balance return2 false',1,true);
                        //return false;
                    }
                    break;
                case 3:
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+10,'alias'=>NULL),array("id='".$value['dst']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:3 add alias return false',1,true);
                        //return false;
                    }
                    break;
                case 4:
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("id='".$value['dst']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:4 mn return false',1,true);
                        //return false;
                    }
                    $res=$sql->update('mn',array('last_won'=>0),array("public_key='".$public_key."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:4 mn last_won return false',1,true);
                        //return false;
                    }
                    break;
                case 5:
                    break;
                case 100:
                    $res=$sql->delete('masternode',array("public_key='".$value['public_key']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:100 del mn false',1,true);
                        //return false;
                    }
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+10000),array("id='".$src."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:100 update account false',1,true);
                        //return false;
                    }
                    # code...
                    break;
                case 101:
                    $res=$sql->update('masternode',array('status'=>1),array("public_key='".$value['public_key']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:101 update mn status false',1,true);
                        //return false;
                    }
                    break;
                case 102:
                    $res=$sql->update('masternode',array('status'=>0),array("public_key='".$value['public_key']."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:102 update mn status false',1,true);
                        //return false;
                    }
                    break;
                case 103:
                    $res=$sql->select('transactions','*',1,array("public_key='".$value['public_key']."'","version=100"),'height DESC',1);
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:103 select trx false',1,true);
                        //return false;
                    }

                    $start_height=$res['height'];
                    $start_ip=$res['message'];
                    //
                    $ress=$sql->select('transactions','*',1,array("public_key='".$value['public_key']."'","(version=101 or version=102)"),'height DESC',1);
                    if ($ress['version']==101) {
                        $status=0;
                    }else{
                        $status=1;
                    }
                    //
                    $ress=$sql->select('transactions','*',1,array("public_key='".$value['public_key']."'","version=111"),'height DESC',1);
                    if (!$ress) {
                        $last_won=0;    $blacklist=0;   $fails=0;      
                    }else{
                        $m=explode(",", $ress['message']);
                        $blacklist=$m[0];
                        $last_won=$m[1];
                        $fails=$m[2];
                    }

                    //add
                    $res=$sql->add('masternode',array('public_key' => $value['public_key'],'height'=>$start_height,'ip'=>$start_ip,'last_won'=>$last_won,'blacklist'=>$blacklist,'fails'=>$fails,'status'=>$status));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:103 add mn false',1,true);
                        //return false;
                    }
                    //
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-10000),array("id='".$src."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:103 update account false',1,true);
                        //return false;
                    }
                    break;
                case 111:
                    $m=explode(",", $value['message']);
                    $pub=$m[0];
                    $fails=$m[1];

                    $ress=$sql->select('transactions','*',1,array("public_key='".$pub."'",'height<'.$value['height'],"version=111"),'height DESC',1);
                    if (!$ress) {
                        $last_won=0;    $blacklist=0;   $fails=0;      
                    }else{
                        $m=explode(",", $ress['message']);
                        $pub2=$m[0];
                        $fails2=$m[1];
                        if ($fails2==0) {
                            $fails=0;
                            $blacklist=0;
                        }else{
                            $mnn=$sql->select('mn','*',1,array("public_key='".$pub."'"),'height DESC',1);
                            if ($mnn) {
                                $fails=$mnn['fails']-$fails;
                                if ($fails<0) {
                                    $fails=0;
                                }
                                $blacklist=$ress['height']+$fails*180;
                            }else{
                                $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:111 select mn false',1,true);
                                return false;
                            }

                        }
                        $last_won=$ress['height'];
                    }
                    $res=$sql->update('masternode',array('last_won'=>$last_won,'blacklist'=>$blacklist,'fails'=>$fails),array("public_key='".$pub."'"));
                    if (!$res) {
                        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block version:111 update mn false',1,true);
                        return false;
                    }
                    break;
                default:
                    $res=true;
                    break;
            }
            //
            if ($value['version']!=0 and $value['version']!=4 and $value['version']!=111) {
                $res=$sql->add('mempool',array(
                                            'id'=>$value['id'],
                                            'height'=>$value['height'],
                                            'dst'=>$value['dst'],
                                            'val'=>$value['val'],
                                            'fee'=>$value['fee'],
                                            'signature'=>$value['signature'],
                                            'version'=>$value['version'],
                                            'message'=>$value['message'],
                                            'date'=>$value['date'],
                                            'public_key'=>$value['public_key'],
                                            'peer'=>$value['peer']
                ));
                if (!$res) {
                    $this->log('Transaction.inc->delete_transactions_to_mempool_from_block add mem false',1,true);
                    return false;
                }
            }

            
            $res=$sql->delete('transactions',array("id='".$value['id']."'"));
            if (!$res) {
                $this->log('Transaction.inc->delete_transactions_to_mempool_from_block del trx false',1,true);
                return false;
            }
        }
        //del account
        $res=$sql->delete('acc',array("`block`='".$block_hash."'"));
        if ($res==false) {
            $this->log('Transaction.inc->delete_transactions_to_mempool_from_block del account false',1,true);
            //return false;
        }
        $this->log('Transaction.inc->delete_transactions_to_mempool_from_block true',0,true);
        return true;
    }


    // add a new transaction to the blockchain
    public function add_transactions_delete_mempool_from_block($id,$public_key,$block_hash,$height,$dst,$val,$fee,$signature,$version,$message,$date){
        $sql=OriginSql::getInstance();

        $res=$sql->add('trx',array(
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
                                'block'=>$block_hash
        ));
        if (!$res) {
            $this->log('Transaction.inc->add_transactions_delete_mempool_from_block add trx false',1,true);
            return false;
        }

        switch ($version) {
            case 0:
                $acc=$sql->select('accounts','balance',1,array("id='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("id='".$dst."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:0 update false',0,true);
                    return false;
                }
                break;
            case 1:
                $acc=$sql->select('accounts','balance',1,array("id='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("id='".$dst."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:1 update1 false',0,true);
                    return false;
                }
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-$val-$fee),array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:1 update2 false',0,true);
                    return false;
                }
                break;
            case 2:
                $acc=$sql->select('accounts','balance',1,array("alias='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("alias='".$dst."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:2 update1 false',0,true);
                    return false;
                }
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-$val-$fee),array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:2 update2 false',0,true);
                    return false;
                }
                break;
            case 3:
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-10,'alias'=>$message),array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:3 update false',0,true);
                    return false;
                }
                break;
            case 4:
                $acc=$sql->select('accounts','balance',1,array("id='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("id='".$dst."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:4 update1 false',0,true);
                    return false;
                }
                $res=$sql->update('mn',array('last_won'=>$height),array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:4 update2 false',0,true);
                    return false;
                }
                break;
            case 5:
                break;
            case 100:
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-10000),array("public_key='".$public_key."'"));
                if (!$res) {    $this->log('update balance fails',1);   return false;   }
                $res=$sql->add('masternode',array(
                                                'public_key'=>$public_key,
                                                'height'=>$height,
                                                'ip'=>$message,
                                                'last_won'=>0,
                                                'blacklist'=>0,
                                                'fails'=>0,
                                                'status'=>1
                ));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:100 add mn false',0,true);
                    return false;
                }
                break;
            case 101:
                $res=$sql->update('masternode',array('status'=>0),array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:101 update false',0,true);
                    return false;
                }
                break;
            case 102:
                $res=$sql->update('masternode',array('status'=>1),array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:102 update false',0,true);
                    return false;
                }
                break;
            case 103:
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+10000),array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:103 update false',0,true);
                    return false;
                }
                $res=$sql->delete('mn',array("public_key='".$public_key."'"));
                if (!$res) {
                    $this->log('Transaction.inc->add_transactions_delete_mempool_from_block version:103 del mn false',0,true);
                    return false;
                }
                break;
            case 111:
                $Masternode=Masternodeinc::getInstance();
                $Masternode->blacklist_masternodes($message);
                break;
            default:
                # code...
                break;
        }

        if ($version!=111 and $version!=0 and $version!=4) {
            $res=$sql->delete('mem',array("id='".$id."'"));
            if (!$res) {
                $this->log('Transaction.inc->add_transactions_delete_mempool_from_block del mem trx false',0,true);
                return false;
            }
        }
        $this->log('Transaction.inc->add_transactions_delete_mempool_from_block true',0,true);
        return true;
    }


    // check the transaction for validity
    public function check($x){
        $sql=OriginSql::getInstance();
        $Account=Accountinc::getInstance();

        //id
        if ($x['id']=='') {
            $this->log('Transaction.inc->check trx hash is null false',0,true);
            return false;
        }
        if ($x['version']!=111) {
            $hash = $this->hasha($x['dst'],$x['val'],$x['fee'],$x['signature'],$x['version'],$x['message'],$x['date'],$x['public_key']);
            if ($x['id'] != $hash) {
                $this->log('Transaction.inc->check trx hash check false',0,true);
                return false;
            }
        }
        //height
        if ($x['height']<2) {
            $this->log('Transaction.inc->check height <2 false',0,true);
            return false;
        }
        //dst
        if ($x['version']!=2) {
            if (valid_len($x['dst'],70,128)==false) {
                $this->log('Transaction.inc->check dst address len false',0,true);
                return false;
            }
        }elseif($x['version']==2){
            if (valid_len($x['dst'],4,25)==false) {
                $this->log('Transaction.inc->check dst alias len false',0,true);
                return false;
            }
        }
        if (strlen($x['dst']) < 4) {
            $this->log('Transaction.inc->check dst or alias len false',0,true);
            return false;
        }
        if (blacklist::checkalias($x['dst'])) {
            $this->log('Transaction.inc->check alias blacklist false',0,true);
            return false;
        }
        if (blacklist::checkAddress($x['dst'])) {
            $this->log('Transaction.inc->check address blacklist false',0,true);
            return false;
        }

        //val
        if ($x['val']-0<0) {
            $this->log('Transaction.inc->check val<0 false',0,true);
            return false;
        }
        //fee
        if ($x['fee']-0<0) {
            $this->log('Transaction.inc->check fee<0 false',0,true);
            return false;
        }
        //signature
        if ($x['version']!=111 and $x['version']!=4) {
            if (!$this->check_signature($x['dst'],$x['val'],$x['fee'],$x['version'],$x['message'],$x['date'],$x['public_key'],$x['signature'])) {
                $this->log('Transaction.inc->check sign false',0,true);
                return false;
            }
        }
        //version
        if ($x['version']==0 or $x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==4 or $x['version']==5 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103 or $x['version']==111) {
        }else{
            $this->log('Transaction.inc->check version false',0,true);
            return false;
        }
        //message
        if (strlen($x['message']) > 128) {
            $this->log('Transaction.inc->check message must be less than 128 chars false',0,true);
            return false;
        }
        //date
        if ($x['date'] < 1511725068) {
            $this->log('Transaction.inc->check date1 false',0,true);
            return false;
        }
        /////// if ($x['date'] > time() + 86400) {  return false;    }
        //public
        // public key must be at least 15 chars / probably should be replaced with the validator function
        if (strlen($x['public_key']) < 15) {
            $this->log('Transaction.inc->check public key len false',0,true);
            return false;
        }
        //check from publickey
        if ($x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103) {
            if ($Account->public_key_alive_from_public($x['public_key'])==false) {
                $this->log('Transaction.inc->check public key not alive false',0,true);
                return false;}
        }
        
        $src=$Account->get_address_from_public_key($x['public_key']);
        //blacklist
        if (blacklist::checkPublicKey($x['public_key']) || blacklist::checkAddress($src)) {
            $this->log('Transaction.inc->check public key and address blacklist false',0,true);
            return false;
        }

        //register account
        $res=$Account->check_acc_pub_update_DB($x['public_key'],'',$x['block']);
        if (!$res) {
            $this->log('Transaction.inc->check regedit account is false',0,true);
            return false;
        }
        if ($x['version']!=2) {
            $Account->check_acc_pub_update_DB('',$x['dst'],$x['block']);
        }

        if ($x['version']==1) {
            // $res_account_to=$sql->select('acc','*',1,array("id='".$x['dst']."'"),'',1);
            // if (!$res_account_to or count($res_account_to)!=1) {
            //     $this->log("dst failed");
            //     return false;
            // }
        }elseif($x['version']==2){
            // $res_account_to=$sql->select('acc','*',1,array("alias='".san(strtolower($x['dst']))."'"),'',1);
            // if (!$res_account_to or count($res_account_to)!=1) {
            //     $this->log("alias failed");
            //     return false;
            // }
        } 

        //switch
        $res_account_from=$sql->select('acc','*',1,array("public_key='".$x['public_key']."'"),'',1);
        $this->log("it version check");
        switch ($x['version']) {
            case 0:
                if ($src!=$x['dst']) {
                    $this->log('Transaction.inc->check version:0 src false',0,true);
                    return false;
                }
                if (0-$x['fee']!=0) {
                    $this->log('Transaction.inc->check version:0 fee false',0,true);
                    return false;
                }
                if ($x['val']-0<=0) {
                    $this->log('Transaction.inc->check version:0 val false',0,true);
                    return false;
                }
                //message
                if ($x['message']!='') {
                    $this->log('Transaction.inc->check version:0 message false',0,true);
                    return false;
                }
                break;
            case 1:
                //message
                // if ($x['message']!='') {
                //     return false;
                // }
                //fee
                $fee = $x['val'] * 0.005;
                if (bccomp($fee, $x['fee'], 8)!=0) {
                    $this->log('Transaction.inc->check version:1 fee false',0,true);
                    return false;
                }
                if ($fee < 0.00000001) {   $fee = 0.00000001;  }

                //balance
                if ($res_account_from['balance']-$x['val']-$fee<0) {
                    $this->log('Transaction.inc->check version:1 balance not enought false',0,true);
                    return false;
                }
                break;
            case 2:
                //message
                // if ($x['message']!='') {
                //     return false;
                // }
                //fee
                $fee = $x['val'] * 0.005;
                if (bccomp($fee, $x['fee'], 8)!=0) {
                    $this->log('Transaction.inc->check version:2 fee false',0,true);
                    return false;
                }
                if ($fee < 0.00000001) {    $fee = 0.00000001;  }

                //alias
                if (san(strtolower($x['dst']))!=$x['dst']) {
                    $this->log('Transaction.inc->check version:2 dst false',0,true);
                    return false;
                }
                if ($Account->alias_check_blacklist($x['dst'])==true) {
                    $this->log('Transaction.inc->check version:2 dst is blacklist false',0,true);
                    return false;
                }
                if (strlen($x['dst'])<4||strlen($x['dst'])>25) {
                    $this->log('Transaction.inc->check version:2 dst len false',0,true);
                    return false;
                }
                if ($Account->alias_alive_from_alias(strtolower($x['dst']))==false) {
                    $this->log('Transaction.inc->check version:2 dst is not alive false',0,true);
                    return false;
                }
                //balance
                if ($res_account_from['balance']-$x['val']-$fee<0) {
                    $this->log('Transaction.inc->check version:2 balance not enought false',0,true);
                    return false;
                }
                break;
            case 3:
                //fee
                if (($x['fee']-0)!=10) {
                    $this->log('Transaction.inc->check version:3 fee false',0,true);
                    return false;
                }
                //val
                if (0-$x['val']!=0) {
                    $this->log('Transaction.inc->check version:3 val false',0,true);
                    return false;
                }

                //alias
                $alias=$x['message'];
                if (san(strtolower($alias))!=$alias) {
                    $this->log('Transaction.inc->check version:3 alias fails,alisa need strtolower false',0,true);
                    return false;
                }
                if ($Account->alias_check_blacklist($alias)==true) {
                    $this->log('Transaction.inc->check version:3 alias blacklist false',0,true);
                    return false;
                }
                if (strlen($alias)<4||strlen($alias)>25) {
                    $this->log('Transaction.inc->check version:3 alias len false',0,true);
                    return false;
                }
                //
                if ($Account->alias_alive_from_alias(strtolower($x['message']))==true) {
                    $this->log('Transaction.inc->check version:3 alias isnot alive from alias false',0,true);
                    return false;
                }
                if ($Account->alias_alive_from_public_key($x['public_key'])==true) {
                    $this->log('Transaction.inc->check version:3 alias isnot alive from pubkey false',0,true);
                    return false;
                }
                //balance
                if ($res_account_from['balance']-$x['val']-$fee<0) {
                    $this->log('Transaction.inc->check version:3 balance not enought false',0,true);
                    return false;
                }
                break;
            case 4:
                if ($src!=$x['dst']) {
                    $this->log('Transaction.inc->check version:4 src false',0,true);
                    return false;
                }

                if (0-$x['fee']!=0) {
                    $this->log('Transaction.inc->check version:4 fee false',0,true);
                    return false;
                }
                if ($x['val']-0<=0) {
                    $this->log('Transaction.inc->check version:4 val false',0,true);
                    return false;
                }
                //message
                if ($x['message']!='') {
                    $this->log('Transaction.inc->check version:4 message false',0,true);
                    return false;
                }
                //
                $Masternodeinc=Masternodeinc::getInstance();
                $res=$Masternodeinc->get_masternode($x['public_key']);
                if (!$res) {
                    $this->log('Transaction.inc->check version:4 mn select false',0,true);
                    return false;
                }
                if ($res['status']!=1) {
                    $this->log('Transaction.inc->check version:4 mn status!=1 false',0,true);
                    return false;
                }
                break;
            case 100:
                // fee
                if (0-$x['fee']!=0) {
                    $this->log('Transaction.inc->check version:100 fee false',0,true);
                    return false;
                }
                if (0-$x['val']!=0) {
                    $this->log('Transaction.inc->check version:100 val false',0,true);
                    return false;
                }
                //message
                if ($x['message']=='') {
                    $this->log('Transaction.inc->check version:100 message false',0,true);
                    return false;
                }
                //balance
                if ($res_account_from['balance']-10000-$x['fee']<0) {
                    $this->log('Transaction.inc->check version:100 balance not enought false',0,true);
                    return false;
                }

                //message
                if (strtolower($x['message'])!=$x['message']) {
                    $this->log('Transaction.inc->check version:100 message must a lowercase letter false',0,true);
                    return false;
                }
                //
                $res=$sql->select('mn','*',2,array("public_key='".$x['public_key']."'","height<=".$x['height']),'',1);
                if ($res!=0) {
                    $this->log('Transaction.inc->check version:100 Nodes already exist Cannot continue to add false',0,true);
                    return false;
                }
                //
                $res=$sql->select('trx','*',1,array("public_key='".$x['public_key']."'","height<=".$x['height'],'(version=100 or version=103)'),'height desc',1);
                if ($res and $res['version']!=103) {
                    $this->log('Transaction.inc->check version:100 this mem already add false',0,true);
                    return false;
                }
                if ($res and $res['version']!=100 and ($x['height']-$res['height']<10)) {
                    $this->log('Transaction.inc->check version:100 need 10 blocks false',0,true);
                    return false;
                }
                break;
            case 101:
                break;
            case 102:
                break;
            case 103:
                // fee
                if (0-$x['fee']!=0) {
                    $this->log('Transaction.inc->check version:103 fee false',0,true);
                    return false;
                }
                if (0-$x['val']!=0) {
                    $this->log('Transaction.inc->check version:103 val false',0,true);
                    return false;
                }
                //message
                if ($x['message']=='') {
                    $this->log('Transaction.inc->check version:103 message false',0,true);
                    return false;
                }

                //
                $res=$sql->select('mn','*',2,array("public_key='".$x['public_key']."'","height<=".$x['height']),'',1);
                if ($res!=1) {
                    $this->log('Transaction.inc->check version:103 Nodes Non-existent false',0,true);
                    return false;
                }
                //
                $res=$sql->select('trx','*',1,array("public_key='".$x['public_key']."'","height<=".$x['height'],'(version=100 or version=103)'),'height desc',1);
                if ($res and $res['version']!=100) {
                    $this->log('Transaction.inc->check version:103 mn not reg false',0,true);
                    return false;
                }
                if ($res and $res['version']!=103 and ($x['height']-$res['height']<10)) {
                    $this->log('Transaction.inc->check version:103 need 10 blocks false',0,true);
                    return false;
                }
                break;
            case 111:
                //
                if ($x['message']=='') {
                    $this->log('Transaction.inc->check version:104 message is empty false',0,true);
                    return false;
                }
                if (trim($x['message'])!=$x['message']) {
                    $this->log('Transaction.inc->check version:104 message1 false',0,true);
                    return false;
                }

                if (count(explode(",",$x['message']))!=2) {
                    $this->log('Transaction.inc->check version:104 message2 false',0,true);
                    return false;
                }

                break;
            default:
                return false;
                break;
        }

        //host
        if ($x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103) {
             if (san_host($x['message'])!=$x['message']) {
                $this->log('Transaction.inc->check message false',0,true);
                return false;
             }
        }
        // make sure  in mempool
        if ($x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==100 or $x['version']==103) {
            $res = $sql->select('mem','*',2,array("id='".$x['id']."'"),'',1);
            if (!$res) {
                $this->log('Transaction.inc->check select mem failed false',0,true);
                return false;
            }
        }

        // make sure the transaction is not already on the blockchain
        $res = $sql->select('trx','*',2,array("id='".$x['id']."'"),'',1);
        if ($res!=0) {
            $this->log('Transaction.inc->check mem already in trx db false',0,true);
            return false;
        }
        $this->log('Transaction.inc->check trx check true',1,true);
        return true;
    }
    // sign
    public function signature($dst,$val,$fee,$version,$message,$date,$public_key, $private_key){
        $val=number_format($val, 8, '.', '');
        $fee=number_format($fee, 8, '.', '');
        $info = "{$dst}-{$val}-{$fee}-{$version}-{$message}-{$date}-{$public_key}";
        $signature = ec_sign($info, $private_key);
        return $signature;
    }
    public function check_signature($dst,$val,$fee,$version,$message,$date,$public_key, $signature){
        $val=number_format($val, 8, '.', '');
        $fee=number_format($fee, 8, '.', '');
        return ec_verify("{$dst}-{$val}-{$fee}-{$version}-{$message}-{$date}-{$public_key}", $signature, $public_key);
    }
    // hash
    public function hasha($dst,$val,$fee,$signature,$version,$message,$date,$public_key){
        $val=number_format($val, 8, '.', '');
        $fee=number_format($fee, 8, '.', '');
        $info = $dst."-".$val."-".$fee."-".$signature."-".$version."-".$message."-".$date."-".$public_key;
        $hash = hash("sha512", $info);
        return hex2coin($hash);
    }

}
