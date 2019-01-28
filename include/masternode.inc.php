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
class Masternodeinc extends base{
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


    public function get_masternode($public_key){
        $sql=OriginSql::getInstance();
        $res = $sql->select('mn','*',1,array("public_key='".$public_key."'"),'',1);

        if ($res) {
            return $res;
        }else{
            return false;
        }
        
    }

    public function valid_masternode_from_db($public_key){
        $sql=OriginSql::getInstance();

        $mm = $sql->select('mn','*',1,array("public_key='".$public_key."'"),'',1);
        
        if ($mm) {
            return true;
        }else{
            return false;
        }
    }

    // $msg=>$check_public_key.','.$fails;
    public function blacklist_masternodes($msg){
        $sql=OriginSql::getInstance();
        $Blockinc=Blockinc::getInstance();
        $current=$Blockinc->current();

        $vvv=explode(",",$msg);

        $res = $sql->select('mn','*',1,array("public_key='".$vvv[0]."'"),'',1);
        if ($vvv[1]==0) {
            $sql->update('mn',array(
                'fails'=>0,
                'blacklist'=>$current['height'],
                'status'=>1
            ),array("public_key='".$vvv[0]."'"));
        }else{
            if ($res['fails']+$vvv[1]>=20) {
                $sql->update('mn',array(
                    'fails'=>100,
                    'blacklist'=>$current['height']+(20*180),
                    'status'=>0
                ),array("public_key='".$vvv[0]."'"));
            }else{
                $sql->update('mn',array(
                    'fails'=>$res['fails']+$vvv[1],
                    'blacklist'=>$current['height']+(($res['fails']+$vvv[1])*180),
                    'status'=>1
                ),array("public_key='".$vvv[0]."'"));    
            }

        }
    }
    
    public function check($public_key,$height,$ip,$last_won,$blacklist,$fails,$status){
        $sql=OriginSql::getInstance();
        $res = $sql->select('acc','*',2,array("public_key='".$public_key."'"),'',1);
        if ($res==0) {
            $this->log('check masternode public key Non-existent [false]',3);
            return false;
        }

        if ($height<2) {
            $this->log('check masternode block height<2 [false]',3);
            return false;
        }

        if (san_host($ip)!=$ip) {  $this->log('check masternode ip [false]',3); return false;   }
        if (san_host($ip)=='') { $this->log('check masternode ip [false]',3); return false;    }

        $this->log('check masternode [true]',3);
        return true;

    }



}

?>