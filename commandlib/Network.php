<?php
/**
 * 
 */
// version: 20190212 test
class Network extends base{
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

    //"node" "add|remove|check"
    public function addnode($mode='cli',$node,$type='add'){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        switch ($type) {
            case 'add':
                $sql=OriginSql::getInstance();
                if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)==false) {
                    $res=$sql->add('peer',array('hostname'=>$node,'blacklisted'=>0,'ping'=>0,'reserve'=>1,'ip'=>md5($node),'fails'=>0,'stuckfail'=>0));
                    if ($res) {
                        return array('result' => 'ok', 'error'=>'');
                    }
                }
                return array('result' => '', 'error'=>'add fail');
                break;
            case 'remove':
                $sql=OriginSql::getInstance();
                if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)==false) {
                    $res=$sql->delete('peer',array("hostname='".$node."'"));
                    if ($res) {
                        return array('result' => 'ok', 'error'=>'');
                    }
                }
                return array('result' => '', 'error'=>'remove fail');
                break;
            case 'check':
                $Peerinc=Peerinc::getInstance();
                if ($Peerinc->check($node)==false) {
                    return array('result' => '', 'error'=>'check fail');
                }
                return array('result' => 'ok', 'error'=>'');
                break;
            default:
                return array('result' => '', 'error'=>'method fail');
                break;
        }
    }
    public function checknodeping($mode='cli',$node=''){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        if ($node=='') {
            $result=[];
            $sql=OriginSql::getInstance();
            $res=$sql->select('peer','*',0,array(),'',0);
            foreach ($res as $x) {
                $a = peer_post($x['hostname']."/peer.php?q=ping");
                if ($a == "success") {
                    $s['node']=$x['hostname'];
                    $s['result']='ok';
                    $result[]=$s;
                } else {
                    $s['node']=$x['hostname'];
                    $s['result']='error';
                    $result[]=$s;
                }
            }
        }else{
            $result=[];
            $a = peer_post($x['hostname']."/peer.php?q=ping");
                if ($a == "success") {
                    $result['node']=$x['hostname'];
                    $result['result']='ok';
                } else {
                    $result['node']=$x['hostname'];
                    $result['result']='error';
                }
        }
        return array('result' => $result, 'error'=>'');
    }
    public function clearbanned($mode='cli'){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->delete_fails_peer()==false) {
            return array('result' => '', 'error'=>'fail');
        }
        return array('result' => 'ok', 'error'=>'');
    }

    public function disconnectnode($mode='cli',$node){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)) {
            $res=$sql->update('peer',array('reserve' => 0),array("hostname='".$node."'"));
            if ($res) {
                return array('result' => 'ok', 'error'=>'');
            }
        }
        return array('result' => '', 'error'=>'fail');
    }
    public function getaddednodeinfo($mode='cli',$node){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->ping($node,5)==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            $res=$peer->peer_post($node."/peer.php?q=currentBlock", [], 5)
            if ($res==false) {
                return array('result' => '', 'error'=>'fail');
            }else{
                return array('result' => $res, 'error'=>'');
            }
        }
    }
    public function getconnectioncount($mode='cli'){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        $all_count=$sql->select('peer','*',2,array("reserve=1"),'',1);
        if ($all_count>=$this->config['max_peer']) {
            return array('result' => $this->config['max_peer'], 'error'=>'');
        }else{
            return array('result' => $all_count, 'error'=>'');
        }
    }
    public function getpeerinfo($mode='cli'){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',0,array("reserve=1"),'',0);
        if ($res) {
            return array('result' => $res, 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'fail');
        }
    }
    public function listbanned($mode='cli'){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $sql=OriginSql::getInstance();
        $res=$sql->select('peer','*',0,array("reserve=0"),'',0);
        if ($res) {
            return array('result' => $res, 'error'=>'');
        }else{
            return array('result' => '', 'error'=>'fail');
        }
    }
    public function ping($mode='cli',$node){
        if ($mode!=='cli') {
            return array('result' => '', 'error'=>'fail');
        }
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->ping($node,5)==false) {
            return array('result' => '', 'error'=>'fail');
        }else{
            return array('result' => 'ok', 'error'=>'');
        }
    }
    public function getversion($mode='all'){
        return array('result' => $this->info['version'], 'error'=>'');
    }
}

?>