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

// version: 20190226
class Peerinc extends base{
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
    //set fails
    public function update_peer_fails($hostname,$fails){
        $sql=OriginSql::getInstance();

        $res=$sql->update('peer',array('fails'=>$fails),array("hostname='".$hostname."'"));
        if ($res) {
            return $res;
        }else{
            $this->log('peer.inc->update_peer_fails false',0,true);
            return false;
        }
    }
    //set stuckfail
    public function update_peer_stuckfail($hostname,$stuckfail,$blacklisted=''){
        $sql=OriginSql::getInstance();

        if ($blacklisted==='') {
            $res=$sql->update('peer',array('stuckfail'=>$stuckfail),array("hostname='".$hostname."'"));
        }else{
            $res=$sql->update('peer',array('stuckfail'=>$stuckfail,'blacklisted'=>$blacklisted),array("hostname='".$hostname."'")); 
        }

        if ($res) {
            return $res;
        }else{
            $this->log('peer.inc->update_peer_stuckfail false',0,true);
            return false;
        }
    }

    public function get_more_peer($peer_list=array(),$maxpeer){
        $peer=0;
        foreach ($peer_list as $ve) {
            $url = $ve['hostname']."/peer.php?q=getPeers";
            $data = $this->peer_post($url."getPeers", [], 5);
            if ($data == false) {   continue;   }
            
            foreach ($data as $valuee) {
                if ($this->check($valuee['hostname'])==false) {
                    continue;
                }
                $res=$sql->select('peer','*',2,array("hostname='".$valuee['hostname']."'"),'',1);
                if ($res!=0) {
                    continue;
                }
                $this->add($valuee['hostname'],0,0,0,md5($valuee['hostname']),0,0);
                if ($this->config['local_node']==false) {
                    $res = $this->peer_post($valuee['hostname']."/peer.php?q=peer", ["hostname" => $this->config['hostname'], "repeer" => 1]);
                }

                $peer=$peer+1;

                if ($peer>=$maxpeer) {
                    break;
                }
            }  
            if ($peer>=$maxpeer) {
                break;
            }   

        }
        //
        return true;
    }
    public function check_bad_peer($hostname,$bad_peers=array()){
        $is_available=false;
        $tpeer=str_replace(["https://","http://","//"], "", $hostname);
        //check badpeer
        foreach ($bad_peers as $bp) {
            if (stripos($bp, $tpeer)!=false) {   $is_available=true;    break;   }
        }


        return $is_available;
    }
    public function delete_fails_peer(){
        $sql=OriginSql::getInstance();
        $res=$sql->delete('peer',array("fails>100 or stuckfail>100"));
        if ($res===false) {
            $this->log('peer.inc->delete_fails_peer false or Non-existent',0,true);
            return false;
        }else{
            return $res;
        }
    }
    //reserve=0 blacklisted<time()
    public function get_peer_max($max=10){
        if ($max==='') {
            $max=10;
        }
        $sql=OriginSql::getInstance();

        $res=$sql->select('peer','*',0,array("reserve=1","blacklisted<".time()),'',$max);
        if ($res) {
            return $res;
        }else{
            $this->log('peer.inc->get_peer_max false',0,true);
            return false;
        }
    }
    //peer db
    public function get_peer_count_from_hostname($hostname){
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',2,array("hostname='".$hostname."'"),'',1);
        if ($res!=0) {
            return true;
        }else{
            $this->log('peer.inc->get_peer_count_from_hostname false',0,true);
            return false;
        }
    }
    public function get_peer_all_count(){
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',2,'','',1);
        if ($res!=0) {
            return $res;
        }else{
            return 0;
        }
    }
    public function check($hostname){
        if (san_host($hostname)!=$hostname) {
            $this->log('peer.inc->check hostname san_host false',0,true);
            return false;
        }
        if (san_host($hostname)=='') {
            $this->log('peer.inc->check hostname is empty false',0,true);
            return false;
        }
        if (filter_var($hostname, FILTER_SANITIZE_URL)!=$hostname) {
            $this->log('peer.inc->check hostname filter_var1 false',0,true);
            return false;
        }
        if (!filter_var($hostname, FILTER_VALIDATE_URL)) {
            $this->log('peer.inc->check hostname filter_var2 false',0,true);
            return false;
        }

        if ($this->check_bad_peer($hostname,$this->config['bad_peers'])==true) {
            $this->log('peer.inc->check hostname check_bad_peer false',0,true);
            return false;
        }
        if (san_host($hostname)==$this->config['hostname'] or $hostname==$this->config['hostname']) {
            $this->log('peer.inc->check hostname san_host != config false',0,true);
            return false;
        }

        return true;
    }
    public function install_config_peer(){
        $sql=OriginSql::getInstance();
        foreach ($this->config['initial_peer_list'] as $value) {
            if ($this->check($value)==false) {
                continue;
            }
            $res=$sql->select('peer','*',2,array("hostname='".$value."'"),'',1);
            if ($res!=0) {
                continue;
            }
            $this->add($value,0,0,1,md5($value),0,0);

            if ($this->config['local_node']==false) {
                $res = $this->peer_post($value."/peer.php?q=peer", ["hostname" => $this->config['hostname'], "repeer" => 1]);
            }
        }
        return true;
    }


    public function ping($hostname,$timeout=5){
        if ($timeout==='') {
            $timeout=5;
        }
        $response = $this->peer_post($hostname.'/peer.php?q=ping', [], $timeout);
        if ($response == false) {
            $this->log('peer.inc->ping false',0,true);
            return false;
        }else{
            return true;
        }

    }

    public function add($hostname,$blacklisted,$ping,$reserve,$ip,$fails,$stuckfail){
        $sql=OriginSql::getInstance();
        $res=$sql->add('peer',array(
                        'hostname'=>$hostname,
                        'blacklisted'=>$blacklisted,
                        'ping'=>$ping,
                        'reserve'=>$reserve,
                        'ip'=>$ip,
                        'fails'=>$fails,
                        'stuckfail'=>$stuckfail
        ));
        if ($res) {
            return $res;
        }else{
            $this->log('peer.inc->add false',0,true);
            return false;
        }
    }
    public function delete_peer($hostname){
        $sql=OriginSql::getInstance();
        $res=$sql->delete('peer',array("hostname='".$hostname."'"));
        if ($res) {
            return true;
        }else{
            $this->log('peer.inc->delete_peer false',0,true);
            return false;
        }
    }
    public function peer_post($url, $data = [], $timeout = 60){
        if ($timeout==='') {
            $timeout=60;
        }
        $postdata = http_build_query(
            [
                'data' => json_encode($data),
                "coin" => 'origin',
            ]
        );

        $opts = [
            'http' =>
                [
                    'timeout' => $timeout,
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postdata,
                ],
        ];

        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        if ($result==false) {
            $this->log('peer.inc->peer_post result false'.$url,0,true);
            return false;
        }
        $res = json_decode($result, true);

        // the function will return false if something goes wrong
        if ($res['status'] == "ok" || $res['coin'] == 'origin') {
            return $res['data'];
        }else{
            $this->log('peer.inc->peer_post false',0,true);
            return false;
        }  
    }

    public function random_peer_check($max_test_peers){
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',0,array("blacklisted<".time(),"reserve=0"),'',$max_test_peers);

        foreach ($res as $x) {
            $url = $x['hostname']."/peer.php?q=ping";
            $data = $this->peer_post($url, [], 5);
            if ($data == 'success') {
                $sql->update('peer',array('fails'=>0),array("id=".$x['id']));
            } else {
                $sql->update('peer',array('fails'=>$x['fails']+1,'blacklisted'=>time()+($x['fails']+1)*60),array("id=".$x['id']));         
            }
        }

    }

}



?>