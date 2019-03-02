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

// version: 20190301
class Mempoolinc extends base{
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

    public function check($x){
        $sql=OriginSql::getInstance();
        $Account=Accountinc::getInstance();

        //id
        if ($x['id']=='') { 
            $this->log('mempool.inc->check id false',0,true);
            return false;
        }
        if ($x['version']!=111) {
            $hash = $this->hasha($x['dst'],$x['val'],$x['fee'],$x['signature'],$x['version'],$x['message'],$x['date'],$x['public_key']);
            if ($x['id'] != $hash) {
                $this->log('mempool.inc->check hash false',0,true);
                return false;
            }
        }
        //height
        if ($x['height']<2) {   
            $this->log('mempool.inc->check height<2 false',0,true);
            return false;
        }
        //dst
        if ($x['version']!=2) {
            if (valid_len($x['dst'],70,128)==false) {
                $this->log('mempool.inc->check dst address len false',0,true);
                return false;
            }
        }elseif($x['version']==2){
            if (valid_len($x['dst'],4,25)==false) {
                $this->log('mempool.inc->check dst alias len false',0,true);
                return false;
            }
        }
        if (strlen($x['dst']) < 4) {
            $this->log('mempool.inc->check dst or alias len false',0,true);
            return false;
        }
        if (blacklist::checkalias($x['dst'])) {
            $this->log('mempool.inc->check alias blacklist false',0,true);
            return false;
        }
        if (blacklist::checkAddress($x['dst'])) {
            $this->log('mempool.inc->check address blacklist false',0,true);
            return false;
        }

        //val
        if ($x['val']-0<0) {
            $this->log('mempool.inc->check val<0 false',0,true);
            return false;
        }
        //fee
        if ($x['fee']-0<0) {
            $this->log('mempool.inc->check fee<0 false',0,true);
            return false;
        }
        //signature
        if ($x['version']!=111 and $x['version']!=4) {
            if (!$this->check_signature($x['dst'],$x['val'],$x['fee'],$x['version'],$x['message'],$x['date'],$x['public_key'],$x['signature'])) {
                $this->log('mempool.inc->check sign false',0,true);
                return false;
            }
        }
        //version
        if ($x['version']==0 or $x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==4 or $x['version']==5 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103 or $x['version']==111) {
        }else{
            $this->log('mempool.inc->check version false',0,true);
            return false;
        }
        //message
        if (strlen($x['message']) > 128) {
            $this->log('mempool.inc->check The message must be less than 128 chars false',0,true);
            return false;
        }
        //date
        if ($x['date'] < 1511725068) {
            $this->log('mempool.inc->check date1 false',0,true);
            return false;
        }
        if ($x['date'] > time() + 86400) {
            $this->log('mempool.inc->check date2 false',0,true);
            return false;
        }
        //public
        if (strlen($x['public_key']) < 15) {
            $this->log('mempool.inc->check public key len false',0,true);
            return false;
        }
        //check from publickey
        if ($x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103) {
            if ($Account->public_key_alive_from_public($x['public_key'])==false) {
                $this->log('mempool.inc->check public key not alive false',0,true);
                return false;
            }
        }
        $src=$Account->get_address_from_public_key($x['public_key']);
        //blacklist
        if (blacklist::checkPublicKey($x['public_key']) || blacklist::checkAddress($src)) {
            $this->log('mempool.inc->check public key and address blacklist false',0,true);
            return false;
        }
        //peer

        
        /////////////switch
        $res_account_from=$sql->select('acc','*',1,array("public_key='".$x['public_key']."'"),'',1);
        switch ($x['version']) {
            case 0:
                return false;
                break;
            case 1:
                //message
                // if ($x['message']!='') {
                //     return false;
                // }
                //fee
                $fee = $x['val'] * 0.005;
                $fee=number_format($fee, 8, '.', '');
                if (bccomp($fee, $x['fee'], 8)!=0) {
                    $this->log('mempool.inc->check version:1 fee false',0,true);
                    return false;
                }
                if ($fee < 0.00000001) {    $fee = 0.00000001;  }

                //balance
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-$x['val']-$fee<0) {
                    $this->log('mempool.inc->check version:1 balance not enought false',0,true);
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
                $fee=number_format($fee, 8, '.', '');
                if (bccomp($fee, $x['fee'], 8)!=0) {
                    $this->log('mempool.inc->check version:2 fee false',0,true);
                    return false;
                }
                if ($fee < 0.00000001) {    $fee = 0.00000001;  }

                //alias
                if (san(strtolower($x['dst']))!=$x['dst']) {
                    $this->log('mempool.inc->check version:2 dst false',0,true);
                    return false;
                }
                if ($Account->alias_check_blacklist($x['dst'])==true) {
                    $this->log('mempool.inc->check version:2 dst is blacklist false',0,true);
                    return false;
                }
                if (strlen($x['dst'])<4||strlen($x['dst'])>25) {
                    $this->log('mempool.inc->check version:2 dst len false',0,true);
                    return false;
                }
                if ($Account->alias_alive_from_alias(strtolower($x['dst']))==false) {
                    $this->log('mempool.inc->check version:2 dst is not alive false',0,true);
                    return false;
                }
                //balance
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-$x['val']-$fee<0) {
                    $this->log('mempool.inc->check version:2 balance not enought false',0,true);
                    return false;
                }
                break;
            case 3:
                //fee
                if (($x['fee']-0)!=10) {
                    $this->log('mempool.inc->check version:3 fee false',0,true);
                    return false;
                }
                //val
                if (0-$x['val']!=0) {
                    $this->log('mempool.inc->check version:3 val false',0,true);
                    return false;
                }

                //alias
                $alias=$x['message'];
                if (san(strtolower($alias))!=$alias) {
                    $this->log('mempool.inc->check version:3 alias fails,alisa need strtolower false',0,true);
                    return false;
                }
                if ($Account->alias_check_blacklist($alias)==true) {
                    $this->log('mempool.inc->check version:3 alias blacklist false',0,true);
                    return false;
                }
                if (strlen($alias)<4 or strlen($alias)>25) {
                    $this->log('mempool.inc->check version:3 alias len false',0,true);
                    return false;
                }
                //
                if ($Account->alias_alive_from_alias(strtolower($x['message']))==true) {
                    $this->log('mempool.inc->check version:3 alias isnot alive from alias false',0,true);
                    return false;
                }
                if ($Account->alias_alive_from_public_key($x['public_key'])==true) {
                    $this->log('mempool.inc->check version:3 alias isnot alive from pubkey false',0,true);
                    return false;
                }
                //balance
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-$x['val']-$x['fee']<0) {
                    $this->log('mempool.inc->check version:3 balance not enought false',0,true);
                    return false;
                }
                break;
            case 4:
                return false;
                break;
            case 100:
                // fee
                if (0-$x['fee']!=0) {
                    $this->log('mempool.inc->check version:100 fee false',0,true);
                    return false;
                }
                if (0-$x['val']!=0) {
                    $this->log('mempool.inc->check version:100 val false',0,true);
                    return false;
                }
                //message
                if ($x['message']=='') {
                    $this->log('mempool.inc->check version:100 message false',0,true);
                    return false;
                }

                //balance
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-10000-$x['fee']<0) {
                    $this->log('mempool.inc->check version:100 balance not enought false',0,true);
                    return false;
                }
                //message
                if (strtolower($x['message'])!=$x['message']) {
                    $this->log('mempool.inc->check version:100 message must a lowercase letter false',0,true);
                    return false;
                }
                //
                $res=$sql->select('mn','*',2,array("public_key='".$x['public_key']."'","height<=".$x['height']),'',1);
                if ($res!=0) {
                    $this->log('mempool.inc->check version:100 Nodes already exist Cannot continue to add false',0,true);
                    return false;
                }

                $res=$sql->select('mem','*',2,array("public_key='".$x['public_key']."'",'(version=100 or version=103)'),'',1);
                if ($res!=0) {
                    $this->log('mempool.inc->check version:100 this mem already add false',0,true);
                    return false;
                }

                //
                $res=$sql->select('trx','*',1,array("public_key='".$x['public_key']."'","height<=".$x['height'],'(version=100 or version=103)'),'height desc',1);
                if ($res and $res['version']!=103) {
                    $this->log('mempool.inc->check version:100 mn already reg false',0,true);
                    return false;
                }

                if ($res and $res['version']!=100 and ($x['height']-$res['height']<10)) {
                    $this->log('mempool.inc->check version:100 need 10 blocks false',0,true);
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
                    $this->log('mempool.inc->check version:103 fee false',0,true);
                    return false;
                }
                if (0-$x['val']!=0) {
                    $this->log('mempool.inc->check version:103 val false',0,true);
                    return false;
                }
                //message
                if ($x['message']=='') {
                    $this->log('mempool.inc->check version:103 message false',0,true);
                    return false;
                }
                //
                $res=$sql->select('mn','*',2,array("public_key='".$x['public_key']."'","height<=".$x['height']),'',1);
                if ($res!=1) {
                    $this->log('mempool.inc->check version:103 Nodes Non-existent false',0,true);
                    return false;
                }

                $res=$sql->select('mem','*',2,array("public_key='".$x['public_key']."'",'(version=100 or version=103)'),'',1);
                if ($res!=0) {
                    $this->log('mempool.inc->check version:103 this mem already add false',0,true);
                    return false;
                }
                //
                $res=$sql->select('trx','*',1,array("public_key='".$x['public_key']."'","height<=".$x['height'],'(version=100 or version=103)'),'height desc',1);
                if ($res and $res['version']!=100) {
                    $this->log('mempool.inc->check version:103 mn not reg false',0,true);
                    return false;
                }
                if ($res and $res['version']!=103 and ($x['height']-$res['height']<10)) {
                    $this->log('mempool.inc->check version:103 need 10 blocks false',0,true);
                    return false;
                }
                break;
            case 111:
                return false;
                break;
            default:
                return false;
                break;
        }


        //host
        if ($x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103) {
             if (san_host($x['message'])!=$x['message']) {
                $this->log('mempool.inc->check message false',0,true);
                return false;
             }
        }
        // make sure it's not already in mempool
        $res = $sql->select('mem','*',2,array("id='".$x['id']."'"),'',1);
        if ($res!=0) {
            $this->log('mempool.inc->check mem already in mempool false',0,true);
            return false;
        }
        // make sure the transaction is not already on the blockchain
        $res = $sql->select('trx','*',2,array("id='".$x['id']."'"),'',1);
        if ($res!=0) {
            $this->log('mempool.inc->check mem already in trx db false',0,true);
            return false;
        }
        $this->log('mempool.inc->check check mempool true',1,true);
        return true;
    }



    //export a mempool transaction
    public function get_mempool_from_id($id){
        $sql=OriginSql::getInstance();

        $res=$sql->select('mem','*',1,array('id="'.$id.'"'),'',1);
        if ($res) {
            return $res;
        }else{
            $this->log('mempool.inc->get_mempool_from_id false',0,true);
            return false;
        }

    }


    // returns X  transactions from mempool
    public function get_mempool_transaction_for_news($height,$max){
        $sql=OriginSql::getInstance();
        
        $res=$sql->select('mem','*',0,array('height<='.$height),'height ASC',0);
        if ($res===false) {
            $this->log('mempool.inc->get_mempool_transaction_for_news returns a mempool data false',0,true);
            return false;
        }

        //$res=$sql->select('mem','*',0,array('height<='.$height),'',$max + 50);
        $transactions = [];
        if ($res) {
            $balance = [];
            $transaction=Transactioninc::getInstance();
            $Account=Accountinc::getInstance();

            foreach ($res as $x) {
                if (empty($x['public_key'])) {
                    $this->log('mempool.inc->get_mempool_transaction_for_news public_key is empty false',1,true);
                    continue;
                }

                if (!isset($balance[$x['public_key']])) {
                    $balance[$x['public_key']]=0;
                }

                $balance[$x['public_key']] = $balance[$x['public_key']]+$x['val'] + $x['fee'];

                $res=$transaction->get_id_istrue_from_id($x['id']);

                if ($res==true) {
                    $sql->delete('mem',array("id='".$x['id']."'"));
                    continue;
                }

                $res = $Account->get_balance_from_public_key($x['public_key']);

                if (!$res or $res<=0) {
                    //$this->log('mempool.inc->get_mempool_transaction_for_news get_balance_from_public_key is 0 or fail false',1,true);
                    continue;
                }

                if ($res and ($res-$balance[$x['public_key']]<0)) {
                    //$this->log('mempool.inc->get_mempool_transaction_for_news get_balance_from_public_key balance is <0 false',1,true);
                    continue;
                }
                
                $transactions[] = $x;
                // if ($this->check($x)==true) {
                //     $transactions[] = $x;
                // }else{
                //     continue;
                // }
                
                if (count($transactions)>=$max) {
                    break;
                }

            }

            if (count($transactions)>$max) {
                $need_del=count($transactions)-$max;
                for ($i=0; $i < $need_del; $i++) { 
                    array_pop($transactions);
                }
            }
            return $transactions;
        }else{
            return $transactions;
        }

    }
    public function add_mempool($height,$dst,$val,$fee,$signature,$version,$message,$public_key,$date, $peer = ''){
        $sql=OriginSql::getInstance();
        $id=$this->hasha($dst,$val,$fee,$signature,$version,$message,$date,$public_key);
        $res=$sql->add('mem',array(
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
                                'peer'=>$peer
        ));
        if ($res) {
            return true;
        }else{
            $this->log('mempool.inc->add_mempool add a new transaction to mempool false',1,true);
            return false;
        }
        
    }
    //sign
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
    //hash
    public function hasha($dst,$val,$fee,$signature,$version,$message,$date,$public_key){
        $val=number_format($val, 8, '.', '');
        $fee=number_format($fee, 8, '.', '');
        $info = $dst."-".$val."-".$fee."-".$signature."-".$version."-".$message."-".$date."-".$public_key;
        $hash = hash("sha512", $info);
        return hex2coin($hash);
    }
    //del than days
    public function delete_than_days($days){
        $timee=time()-($days*3600*24);
        $sql=OriginSql::getInstance();
        $res=$sql->delete('mem',array("`date`<".$timee));
        if ($res) {
            return true;
        }else{
            $this->log('mempool.inc->delete_than_days del mempool than days false',1,true);
            return false;
        }
    }
    //get val+fee
    public function get_valfee_from_public_key($public_key){
        $sql=OriginSql::getInstance();
        $this->log('get mempool val+fee');
        $res=$sql->sum('mem','val+fee',array('public_key="'.$public_key.'"'));
        if ($res) {
            return $res;
        }else{
            return 0;
        }
    }


}

?>