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

// version: 20190128 test
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
            return false;
        }
    }
    // 



    // reverse and remove all transactions from a block
    // 从块中反转事务
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
                    // 奖励事务 回退
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("id='".$value['dst']."'"));
                    //if (!$res) {    $this->log('miner reward failed',1);    return false;   }
                    break;
                case 1:
                    // 正常事务 回退
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("id='".$value['dst']."'"));
                    //if (!$res) {    $this->log('account balance return to failed',1);    return false;   }
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+$value['val']+$value['fee']),array("id='".$src."'"));
                    //if (!$res) {    $this->log('account balance return from failed',1);  return false;   }
                    break;
                case 2:
                    // 寄给别名的付款 回退
                    $acc=$sql->select('accounts','balance',1,array("alias='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("alias='".$value['dst']."'"));
                    //if (!$res) {    $this->log('alias balance return to failed',1);  return false;   }
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+$value['val']+$value['fee']),array("id='".$src."'"));
                    //if (!$res) {    $this->log('alias balance return from failed',1);    return false;   }
                    break;
                case 3:
                    //增加alias 回退
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+10,'alias'=>NULL),array("id='".$value['dst']."'"));
                    //if (!$res) {    $this->log('add alias return failed',1);   return false;   }
                    break;
                case 4:
                    //masternode奖励事务
                    $acc=$sql->select('accounts','balance',1,array("id='".$value['dst']."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-$value['val']),array("id='".$value['dst']."'"));
                    //if (!$res) {    $this->log('mn return failed',1);    return false;   }
                    $res=$sql->update('mn',array('last_won'=>0),array("public_key='".$public_key."'"));
                    //if (!$res) {    $this->log('mn last_won return failed',1);   return false;   }
                    break;
                case 5:
                    // fee燃烧事务
                    # code...
                    break;
                case 100:
                    //增加节点  回退
                    $res=$sql->delete('masternode',array("public_key='".$value['public_key']."'"));
                    //if (!$res) {    return false;   }
                    //升级账号返还100000
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']+10000),array("id='".$src."'"));
                    //if (!$res) {    return false;   }
                    # code...
                    break;
                case 101:
                    //暂停节点 回退
                    $res=$sql->update('masternode',array('status'=>1),array("public_key='".$value['public_key']."'"));
                    //if (!$res) {    return false;   }
                    break;
                case 102:
                    //开启节点 回退
                    $res=$sql->update('masternode',array('status'=>0),array("public_key='".$value['public_key']."'"));
                    //if (!$res) {    return false;   }
                    break;
                case 103:
                    //删除节点  回退
                    //查找节点当初增加时的高度
                    $res=$sql->select('transactions','*',1,array("public_key='".$value['public_key']."'","version=100"),'height DESC',1);
                    //if (!$res) {    return false;   }

                    $start_height=$res['height'];
                    $start_ip=$res['message'];
                    //查找最后节点的状态 是 开启还是 关闭
                    $ress=$sql->select('transactions','*',1,array("public_key='".$value['public_key']."'","(version=101 or version=102)"),'height DESC',1);
                    if ($ress['version']==101) {
                        $status=0;
                    }else{
                        $status=1;
                    }
                    //查找fails这些状态
                    $ress=$sql->select('transactions','*',1,array("public_key='".$value['public_key']."'","version=111"),'height DESC',1);
                    if (!$ress) {
                        $last_won=0;    $blacklist=0;   $fails=0;      
                    }else{
                        $m=explode(",", $ress['message']);
                        $blacklist=$m[0];
                        $last_won=$m[1];
                        $fails=$m[2];
                    }

                    //插入
                    $res=$sql->add('masternode',array('public_key' => $value['public_key'],'height'=>$start_height,'ip'=>$start_ip,'last_won'=>$last_won,'blacklist'=>$blacklist,'fails'=>$fails,'status'=>$status));
                    //if (!$res) {    return false;   }
                    //升级账号扣除100000
                    $acc=$sql->select('accounts','balance',1,array("id='".$src."'"),'',1);
                    $res=$sql->update('accounts',array('balance'=>$acc['balance']-10000),array("id='".$src."'"));
                    //if (!$res) {    return false;   }
                    break;
                case 111:
                    //升级节点状态 取回上一次的节点升级状态
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
                                $this->log('re mn select fails',1);
                                return false;
                            }

                        }
                        $last_won=$ress['height'];
                    }
                    $res=$sql->update('masternode',array('last_won'=>$last_won,'blacklist'=>$blacklist,'fails'=>$fails),array("public_key='".$pub."'"));
                    //if (!$res) {    $this->log('re up mn 111 fails',1); return false;   }
                    break;
                default:
                    $res=true;
                    break;
            }
            //将事务重新添加到mempool 后删除这条记录
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
                if (!$res) {    $this->log('re add mem fails',1); return false;   }
            }

            
            $res=$sql->delete('transactions',array("id='".$value['id']."'"));
            if (!$res) {    $this->log('re del trx fails',1);    return false;   }
        }
        //删除这个块登记的所有account
        $res=$sql->delete('acc',array("`block`='".$block_hash."'"));
        //if ($res==false) {    $this->log('re del acc fails',1);    return false;   }
        return true;

    }


    // add a new transaction to the blockchain
    // 增加transaction 然后删除 mempool记录
    public function add_transactions_delete_mempool_from_block($id,$public_key,$block_hash,$height,$dst,$val,$fee,$signature,$version,$message,$date){
        $sql=OriginSql::getInstance();

        //不检查账号 都放到check里边
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
        if (!$res) {return false;}

        switch ($version) {
            case 0:
                //奖励事务
                $acc=$sql->select('accounts','balance',1,array("id='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("id='".$dst."'"));
                if (!$res) {    return false;   }
                break;
            case 1:
                //正常事务
                $acc=$sql->select('accounts','balance',1,array("id='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("id='".$dst."'"));
                if (!$res) {    return false;   }
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-$val-$fee),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                break;
            case 2:
                //寄给别名的付款
                $acc=$sql->select('accounts','balance',1,array("alias='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("alias='".$dst."'"));
                if (!$res) {    return false;   }
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-$val-$fee),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                break;
            case 3:
                //增加alias
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-10,'alias'=>$message),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                break;
            case 4:
                //mn奖励事务
                $acc=$sql->select('accounts','balance',1,array("id='".$dst."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+$val),array("id='".$dst."'"));
                if (!$res) {    return false;   }
                $res=$sql->update('mn',array('last_won'=>$height),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                break;
            case 5:
                # code...
                break;
            case 100:
                //增加节点
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']-10000),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                $res=$sql->add('masternode',array(
                                                'public_key'=>$public_key,
                                                'height'=>$height,
                                                'ip'=>$message,
                                                'last_won'=>0,
                                                'blacklist'=>0,
                                                'fails'=>0,
                                                'status'=>1
                ));
                if (!$res) {    return false;   }
                break;
            case 101:
                //暂停节点
                $res=$sql->update('masternode',array('status'=>0),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                break;
            case 102:
                //开启节点
                $res=$sql->update('masternode',array('status'=>1),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                break;
            case 103:
                //删除节点
                $acc=$sql->select('accounts','balance',1,array("public_key='".$public_key."'"),'',1);
                $res=$sql->update('accounts',array('balance'=>$acc['balance']+10000),array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                $res=$sql->delete('mn',array("public_key='".$public_key."'"));
                if (!$res) {    return false;   }
                break;
            case 111:
                //升级节点状态
                $Masternode=Masternodeinc::getInstance();
                $Masternode->blacklist_masternodes($message);
                break;
            default:
                # code...
                break;
        }

        if ($version!=111 and $version!=0 and $version!=4) {
            $res=$sql->delete('mem',array("id='".$id."'"));
            if (!$res) {    return false;   }
        }

        return true;
    }


    // check the transaction for validity
    // 检查交易的有效性
    public function check($x){
        $sql=OriginSql::getInstance();
        $Account=Accountinc::getInstance();

        //id
        if ($x['id']=='') { $this->log("trx hash is null"); return false; }
        if ($x['version']!=111) {
            $hash = $this->hasha($x['dst'],$x['val'],$x['fee'],$x['signature'],$x['version'],$x['message'],$x['date'],$x['public_key']);
            if ($x['id'] != $hash) {
                $this->log("trx hash check failed");
                return false;
            }
        }
        //height
        if ($x['height']<2) { return false;   }
        //dst
        if ($x['version']!=2) {
            if (valid_len($x['dst'],70,128)==false) {   $this->log("dst failed");     return false;   }
        }elseif($x['version']==2){
            if (valid_len($x['dst'],4,25)==false) { $this->log("dst failed");   return false;   }
        }
        if (strlen($x['dst']) < 4) {   $this->log("dst failed"); return false;   }
        if (blacklist::checkalias($x['dst'])) {  $this->log("dst failed"); return false;    }
        if (blacklist::checkAddress($x['dst'])) {  $this->log("dst failed"); return false;   }

        //val
        if ($x['val']-0<0) {    $this->log("val failed"); return false;   }
        //fee
        if ($x['fee']-0<0) {   $this->log("val failed"); return false;   }
        //signature
        if ($x['version']!=111 and $x['version']!=4) {
            if (!$this->check_signature($x['dst'],$x['val'],$x['fee'],$x['version'],$x['message'],$x['date'],$x['public_key'],$x['signature'])) {
                $this->log("check_signature failed");
                return false;
            }
        }
        //version
        if ($x['version']==0 or $x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==4 or $x['version']==5 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103 or $x['version']==111) {
        }else{
            $this->log("check_version failed");
            return false;
        }
        //message
        //message
        if (strlen($x['message']) > 128) {
            $this->log('The message must be less than 128 chars [false]');
            return false;
        }
        //date
        if ($x['date'] < 1511725068) {  $this->log("check_date failed"); return false;   }
        /////// if ($x['date'] > time() + 86400) {  return false;    }
        //public
        // public key must be at least 15 chars / probably should be replaced with the validator function
        if (strlen($x['public_key']) < 15) {   $this->log("check_public_key failed");  return false;}
        //统一检查发送方账号是否存在
        if ($x['version']==1 or $x['version']==2 or $x['version']==3 or $x['version']==100 or $x['version']==101 or $x['version']==102 or $x['version']==103) {
            if ($Account->public_key_alive_from_public($x['public_key'])==false) { return false;}
        }
        
        $src=$Account->get_address_from_public_key($x['public_key']);
        //黑名单屏蔽付款方
        if (blacklist::checkPublicKey($x['public_key']) || blacklist::checkAddress($src)) {
            $this->log("check_public_key failed");
            return false;
        }



        //block

        //注册账号
        $res=$Account->check_acc_pub_update_DB($x['public_key'],'',$x['block']);
        if (!$res) {
            $this->log("regedit account is failed");
            return false;
        }
        if ($x['version']!=2) {
            $Account->check_acc_pub_update_DB('',$x['dst'],$x['block']);
        }

        if ($x['version']==1) {
            $res_account_to=$sql->select('acc','*',1,array("id='".$x['dst']."'"),'',1);
            if (!$res_account_to or count($res_account_to)!=1) {
                $this->log("dst failed");
                return false;
            }
        }elseif($x['version']==2){
            $res_account_to=$sql->select('acc','*',1,array("alias='".san(strtolower($x['dst']))."'"),'',1);
            if (!$res_account_to or count($res_account_to)!=1) {
                $this->log("alias failed");
                return false;
            }
        } 

        //其他判断
        $res_account_from=$sql->select('acc','*',1,array("public_key='".$x['public_key']."'"),'',1);
        switch ($x['version']) {
            case 0:
                //public_key和dst是否一致
                if ($src!=$x['dst']) {
                    $this->log("src failed");
                    return false;
                }

                if (0-$x['fee']!=0) { $this->log("fee failed");  return false;  }
                if ($x['val']-0<=0) { $this->log("val failed"); return false;    }
                //奖励事务
                //检查地址是否存在 还差 检查 是不是 应该得到奖励的miner  还有奖励的费用对不对
                //message
                if ($x['message']!='') {
                    $this->log("message failed");
                    return false;
                }
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
                if ($res_account_from['balance']-$x['val']-$fee<0) {
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
                if ($res_account_from['balance']-$x['val']-$fee<0) {
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
                if ($res_account_from['balance']-$x['val']-$fee<0) {
                    return false;
                }
                break;
            case 4:
                //public_key和dst是否一致
                if ($src!=$x['dst']) {
                    $this->log("src failed");
                    return false;
                }

                if (0-$x['fee']!=0) {  $this->log("fee failed"); return false;  }
                if ($x['val']-0<=0) {   $this->log("val failed"); return false;    }
                //mn奖励事务
                //检查mn地址是否存在  检查mn是不是在线状态  还差 检查 mn是不是 应该得到奖励的mn  还有奖励的费用对不对
                //message
                if ($x['message']!='') {
                    $this->log("message failed");
                    return false;
                }
                //检查mn在线状态
                $Masternodeinc=Masternodeinc::getInstance();
                $res=$Masternodeinc->get_masternode($x['public_key']);
                if (!$res) {
                    $this->log("mn nohave failed");
                    return false;
                }
                if ($res['status']!=1) {
                    $this->log("mn status is not ok");
                    return false;
                }
                break;
            case 100:
                //增加节点
                //检查余额够不够检查总额就行，因为打包还要循环检查一次扣一次款,fee是否正确 发送方地址和接收方地址是否存在
                //检查如果以前存在节点删除了 需要等待至少10个块
                //message不能为空且必须小写

                // fee
                if (0-$x['fee']!=0) {   return false;  }
                if (0-$x['val']!=0) { return false;    }

                //检查余额
                if ($res_account_from['balance']-10000-$fee<0) {
                    return false;
                }

                //message
                if (san_host(strtolower($x['message']))!=$x['message']) {
                    return false;
                }
                //mn是否存在
                $res=$sql->select('mn','*',2,array("public_key='".$x['public_key']."'","height<=".$x['height']),'',1);
                if ($res!=0) {  return false;   }
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
                if ($res!=1) {  return false;   }
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
                //升级节点状态
                if ($x['message']=='') {  $this->log("message failed"); return false;   }
                if (trim($x['message'])!=$x['message']) {  $this->log("message failed"); return false;}

                if (count(explode(",",$x['message']))!=2) {
                    $this->log("message failed");
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
                $this->log("message6 failed");
                return false;
             }
        }
        // make sure it's not already in mempool
        $res = $sql->select('mem','*',2,array("id='".$x['id']."'"),'',1);
        if ($res!=0) {  $this->log("mem failed");   return false; }
        // make sure the transaction is not already on the blockchain
        $res = $sql->select('trx','*',2,array("id='".$x['id']."'"),'',1);
        if ($res!=0) {  $this->log("trx failed");   return false; }

        return true;
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
    // hash the transaction's most important fields and create the transaction ID
    // hash字段创建 transaction ID
    public function hasha($dst,$val,$fee,$signature,$version,$message,$date,$public_key){
        $info = $dst."-".$val."-".$fee."-".$signature."-".$version."-".$message."-".$date."-".$public_key;

        $hash = hash("sha512", $info);
        return hex2coin($hash);
    }

}
