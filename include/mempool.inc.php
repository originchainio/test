<?php
/*
The MIT License (MIT)
Copyright (C) 2019 OriginchainDev

originchain.io

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

// version: 20190110 test
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

        if ($x['id']=='') { $this->log('check id is empty [false]'); return false;   }
        if ($x['version']!=111) {
            $hash = $this->hasha($x['dst'],$x['val'],$x['fee'],$x['signature'],$x['version'],$x['message'],$x['date'],$x['public_key']);
            if ($x['id'] != $hash) {
                $this->log('check hash [false]');
                return false;
            }
        }
        //height
        if ($x['height']<2) {   $this->log('check height<2 [false]');   return false;   }
        //dst
        if ($x['version']!=2) {
            if (valid_len($x['dst'],70,128)==false) {   $this->log('check dst address len [false]');   return false;   }
        }elseif($x['version']==2){
            if (valid_len($x['dst'],4,25)==false) { $this->log('check dst alias len [false]');    return false;   }
        }
        if (strlen($x['dst']) < 4) {    $this->log('check dst alias len [false]');  return false;   }
        if (blacklist::checkalias($x['dst'])) { $this->log('check dst alias blacklist [false]');  return false;   }
        if (blacklist::checkAddress($x['dst'])) {   $this->log('check dst address blacklist [false]');    return false;   }

        //val
        if ($x['val']-0<0) {    $this->log('check val<0 [false]');  return false;   }
        //fee
        if ($x['fee']-0<0) {    $this->log('check fee<0 [false]');  return false;   }
        //signature
        if ($x['version']!=111 and $x['version']!=4) {
            if (!$this->check_signature($x['dst'],$x['val'],$x['fee'],$x['version'],$x['message'],$x['date'],$x['public_key'],$x['signature'])) {
                $this->log('check sign [false]');
                return false;
            }
        }
        //version
        if ($x['version']==0 or $x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==4 or $x['version']==5 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103 or $x['version']==111) {
        }else{
            $this->log('check version [false]');
            return false;
        }
        //message
        if (strlen($x['message']) > 128) {
            $this->log('The message must be less than 128 chars [false]');
            return false;
        }
        //date
        if ($x['date'] < 1511725068) {  $this->log('check date [false]');    return false;   }
        if ($x['date'] > time() + 86400) {  $this->log('check date [false]');    return false;    }
        //public
        // public key must be at least 15 chars / probably should be replaced with the validator function
        if (strlen($x['public_key']) < 15) {    $this->log('check public key len [false]');   return false;   }
        //统一检查发送方账号是否存在
        if ($x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103) {
            if ($Account->public_key_alive_from_public($x['public_key'])==false) {  return false;   }
        }
        $src=$Account->get_address_from_public_key($x['public_key']);
        //黑名单屏蔽付款方
        if (blacklist::checkPublicKey($x['public_key']) || blacklist::checkAddress($src)) {
            $this->log('check public key and address blacklist [false]');
            return false;
        }

        //peer

        
        /////////////switch
        //其他判断
        $res_account_from=$sql->select('acc','*',1,array("public_key='".$x['public_key']."'"),'',1);
        switch ($x['version']) {
            case 0:
                return false;
                break;
            case 1:
                //正常事务
                //检查余额够不够检查总额就行，因为打包还要循环检查一次扣一次款,fee是否正确 发送方地址和接收方地址是否存在
                //message
                // if ($x['message']!='') {
                //     return false;
                // }
                //fee
                $fee = $x['val'] * 0.005;
                if ($fee-$x['fee']!=0) {   return false;  }
                if ($fee < 0.00000001) {    $fee = 0.00000001;  }

                //检查余额
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-$x['val']-$fee<0) {
                    $this->log('Sorry,your credit is running low');
                    return false;
                }
                break;
            case 2:
                //寄给别名的付款
                //检查余额够不够检查总额就行，因为打包还要循环检查一次扣一次款,fee是否正确 发送方地址和接收方地址是否存在
                //message
                // if ($x['message']!='') {
                //     return false;
                // }
                //fee
                $fee = $x['val'] * 0.005;
                if ($fee-$x['fee']!=0) {   return false;  }
                if ($fee < 0.00000001) {    $fee = 0.00000001;  }

                //alias
                if (san(strtolower($x['dst']))!=$x['dst']) {
                    return false;
                }
                if ($Account->alias_check_blacklist($x['dst'])==true) {
                    return false;
                }
                if (strlen($x['dst'])<4||strlen($x['dst'])>25) {
                    return false;
                }
                if ($Account->alias_alive_from_alias(strtolower($x['dst']))==false) {  return false; }
                //检查余额
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-$x['val']-$fee<0) {
                    $this->log('Sorry,your credit is running low');
                    return false;
                }
                break;
            case 3:
                //增加alias
                //检查余额够不够检查总额就行，因为打包还要循环检查一次扣一次款,fee是否正确 发送方地址和接收方地址是否存在 alias是否合法
                //message不能为空且必须小写
                //fee
                if (0-$x['fee']!=10) {   return false;  }
                //检查余额
                if (0-$x['val']!=0) { return false;    }

                //alias
                if (san(strtolower($x['message']))!=$x['message']) {
                    return false;
                }
                if ($Account->alias_check_blacklist($alias)==true) {
                    return false;
                }
                if (strlen($alias)<4||strlen($alias)>25) {
                    return false;
                }
                //
                if ($Account->alias_alive_from_alias(strtolower($x['message']))==true) {  return false; }
                if ($Account->alias_alive_from_public_key($x['public_key'])==true) {  return false; }
                //检查余额
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-$x['val']-$fee<0) {
                    $this->log('Sorry,your credit is running low');
                    return false;
                }
                break;
            case 4:
                return false;
                break;
            case 100:
                //增加节点
                // fee
                if (0-$x['fee']!=0) {   return false;  }
                if (0-$x['val']!=0) { return false;    }

                //检查余额
                $vvv=$this->get_valfee_from_public_key($x['public_key']);
                if ($res_account_from['balance']-$vvv-10000-$fee<0) {
                    return false;
                }
                //message
                if (san_host(strtolower($x['message']))!=$x['message']) {
                    $this->log('Sorry,your message is not true');
                    return false;
                }
                //mn是否存在 只能有一个节点
                $res=$sql->select('mn','*',2,array("public_key='".$x['public_key']."'","height<=".$x['height']),'',1);
                if ($res!=0) {  $this->log('Nodes already exist Cannot continue to add');   return false;   }
                //需要等待10个块
                $res=$sql->select('trx','*',1,array("public_key='".$x['public_key']."'","height<=".$x['height'],'(version=100 or version=103)'),'height desc',1);
                if ($res and $res['version']!=103) {
                    return false;
                }
                if ($res['height']-$x['height']<10) {
                    return false;
                }
                

                break;
            case 101:
                //暂停节点
                //节点是否存在 需要至少间隔10个块才可操作
                break;
            case 102:
                //开启节点
                //节点是否存在 需要至少间隔10个块才可操作
                break;
            case 103:
                //删除节点
                //判断mn存在不存在
                //检查如果以前存在节点 需要等待至少10个块
                // fee
                if ($x['fee']!=0) {   return false;  }
                //message
                if ($x['message']!='') {   return false;   }

                //mn是否存在
                $res=$sql->select('mn','*',2,array("public_key='".$x['public_key']."'","height<=".$x['height']),'',1);
                if ($res!=1) {  $this->log('Nodes Non-existent');  return false;   }
                //需要等待10个块
                $res=$sql->select('trx','*',1,array("public_key='".$x['public_key']."'","height<=".$x['height'],'(version=100 or version=103)'),'height desc',1);
                if ($res and $res['version']!=100) {
                    return false;
                }
                if ($res['height']-$x['height']<10) {
                    return false;
                }
                break;
            case 111:
                //升级节点状态 不检查不打包
                return false;
                break;
            default:
                return false;
                break;
        }


        //host
        if ($x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103) {
             if (san_host($x['message'])!=$x['message']) {
                 return false;
             }
        }
        // make sure it's not already in mempool
        $res = $sql->select('mem','*',2,array("id='".$x['id']."'"),'',1);
        if ($res!=0) { return false;   }
        // make sure the transaction is not already on the blockchain
        $res = $sql->select('trx','*',2,array("id='".$x['id']."'"),'',1);
        if ($res!=0) { return false;   }

        $this->log('check mempool [true]',3);
        return true;

    }



    //export a mempool transaction
    public function get_mempool_from_id($id){
        $sql=OriginSql::getInstance();

        $res=$sql->select('mem','*',1,array('id="'.$id.'"'),'',1);
        if ($res) {
            $this->log('get a mempool data [true]');
            return $res;
        }else{
            $this->log('get a mempool data [false]');
            return false;
        }

    }


    // returns X  transactions from mempool
    public function get_mempool_transaction_for_news($height,$max){
        $sql=OriginSql::getInstance();
        $transactions = [];
        $this->log('returns X  transactions from mempool');
        $res=$sql->select('mem','*',0,array('height<='.$height),'fee/val DESC',0);
        //$res=$sql->select('mem','*',0,array('height<='.$height),'',$max + 50);
        if ($res) {
            $balance = [];
            $transaction=Transactioninc::getInstance();
            $Account=Accountinc::getInstance();
            foreach ($res as $x) {
                if (empty($x['public_key'])) {
                    continue;
                }
                if (!isset($balance[$x['public_key']])) {
                    $balance[$x['public_key']]=0;
                }
  
                $balance[$x['public_key']] = $balance[$x['public_key']]+$x['val'] + $x['fee'];

                $res=$transaction->get_id_istrue_from_id($x['id']);
                if ($res==true) {
                    $sql->delete('mem',array("id='".$x['id']."'"));
                    continue; //duplicate transaction
                }

                $res = $Account->get_balance_from_public_key($x['public_key']);
                if (!$res or $res<=0) {
                    continue;
                }
                if ($res and ($res-$balance[$x['public_key']]<0)) {
                    continue;
                }
                $transactions[] = $x;
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


    // add a new transaction to mempool and lock it with the current height
    public function add_mempool($height,$dst,$val,$fee,$signature,$version,$message,$public_key,$date, $peer = ""){
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
            $this->log('add a new transaction to mempool [true]',1);
            return true;
        }else{
            $this->log('add a new transaction to mempool [false]',1);
            return false;
        }
        
    }
    // sign a transaction
    public function signature($dst,$val,$fee,$version,$message,$date,$public_key, $private_key){
        $info = "{$dst}-{$val}-{$fee}-{$version}-{$message}-{$date}-{$public_key}";
        
        $signature = ec_sign($info, $private_key);

        return $signature;
    }
    // checks the ecdsa secp256k1 signature for a specific public key
    public function check_signature($dst,$val,$fee,$version,$message,$date,$public_key, $signature){

        return ec_verify("{$dst}-{$val}-{$fee}-{$version}-{$message}-{$date}-{$public_key}", $signature, $public_key);
    }
    //ok
    public function hasha($dst,$val,$fee,$signature,$version,$message,$date,$public_key){
        $info = $dst."-".$val."-".$fee."-".$signature."-".$version."-".$message."-".$date."-".$public_key;

        $hash = hash("sha512", $info);
        return hex2coin($hash);
    }

    //删除多少天以前的事务
    public function delete_than_days($days){
        $timee=time()-($days*3600*24);
        $sql=OriginSql::getInstance();
        $res=$sql->delete('mem',array("`date`<".$timee));
        if ($res) {
            $this->log('del mempool than days [true]');
            return true;
        }else{
            $this->log('del mempool than days [false]');
            return false;
        }
    }

    //计算支出
    public function get_valfee_from_public_key($public_key){
        $sql=OriginSql::getInstance();
        $this->log('get mempool val+fee');
        $res=$sql->sum('mem','val+fee',array('public_key="'.$public_key.'"'));
        if ($res) {
            return $res;
        }else{
            return 0;
        }
        // return number_format($res, 8, ".", "");
    }


}

?>