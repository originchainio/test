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
    public function addnode($node,$type='add'){
        switch ($type) {
            case 'add':
                $sql=OriginSql::getInstance();
                if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)==false) {
                    $res=$sql->add('peer',array('hostname'=>$node,'blacklisted'=>0,'ping'=>0,'reserve'=>1,'ip'=>md5($node),'fails'=>0,'stuckfail'=>0));
                    if ($res) {
                        return true;
                    }
                }
                return false;
                break;
            case 'remove':
                $sql=OriginSql::getInstance();
                if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)==false) {
                    $res=$sql->delete('peer',array("hostname='".$node."'"));
                    if ($res) {
                        return true;
                    }
                }
                return false;
                break;
            case 'check':
                $Peerinc=Peerinc::getInstance();
                if ($Peerinc->check($node)==false) {
                    return false;
                }
                return true;
                break;
            default:
                # code...
                break;
        }
    }
    public function clearbanned(){
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->delete_fails_peer()==false) {
            return false;
        }
        return true;
    }

    public function disconnectnode($node){
        $sql=OriginSql::getInstance();
        if ($sql->select('peer','*',2,array("hostname='".$node."'"),'',1)) {
            $res=$sql->update('peer',array('reserve' => 0),array("hostname='".$node."'"));
            if ($res) {
                return true;
            }
        }
        return false;
    }
    public function getaddednodeinfo($node){
        $Peerinc=Peerinc::getInstance();
        if ($Peerinc->ping($node,5)==false) {
            return false;
        }else{
            return $peer->peer_post($node."/peer.php?q=currentBlock", [], 5);
        }
    }
    public function getconnectioncount(){
        $sql=OriginSql::getInstance();
        $all_count=$sql->select('peer','*',2,array("reserve=1"),'',1);
        if ($all_count>=$this->config['max_peer']) {
            return $this->config['max_peer'];
        }else{
            return $all_count;
        }
    }
    public function getpeerinfo(){
        $sql=OriginSql::getInstance();
        return $sql->select('peer','*',0,array("reserve=1"),'',0);
    }
    public function listbanned(){
        $sql=OriginSql::getInstance();
        return $sql->select('peer','*',0,array("reserve=0"),'',0);
    }
    public function ping($node){
        $Peerinc=Peerinc::getInstance();
        return $Peerinc->ping($node,5);
    }
}

?>