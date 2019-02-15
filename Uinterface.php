<?php
// version: 20190215 test
include __DIR__.'/class/base.php';
include __DIR__.'/include/account.inc.php';
include __DIR__.'/include/blacklist.inc.php';
include __DIR__.'/include/block.inc.php';
include __DIR__.'/include/config.inc.php';
include __DIR__.'/include/masternode.inc.php';
include __DIR__.'/include/mempool.inc.php';
include __DIR__.'/include/peer.inc.php';
include __DIR__.'/include/transaction.inc.php';
// include __DIR__.'/include/propagate.inc.php';
include __DIR__.'/class/MainSQLpdo.php';
include __DIR__.'/lib/OriginSql.lib.php';
include __DIR__.'/class/cache.php';
// include __DIR__.'/lib/PostThreads.lib.php';
include __DIR__.'/lib/Security.lib.php';
include __DIR__.'/function/function.php';
include __DIR__.'/function/core.php';

include __DIR__.'/commandlib/Blockchain.php';
include __DIR__.'/commandlib/Mining.php';
include __DIR__.'/commandlib/Network.php';
include __DIR__.'/commandlib/Wallet.php';
class Uinterface extends base{
    private $mode = 'cli';
    function __construct(){
        parent::__construct();
        if ($this->info['cli'] != true) {
            $this->mode='cgi';
        }else{
            $this->mode='cli';
        }
    }

    public function main(){
        if ($this->mode=='cli') {
            if (!isset($argv[1])) { exit;   }
            $method=$argv[1];

            $commandlib=array('Blockchain','Mining','Network','Wallet');

            foreach ($commandlib as $value) {
                $lib=$value::getInstance();
                if (method_exists($lib,$method)) {
                    $p = new ReflectionMethod($value,$method);
                    $params=$p->getParameters();
                    array_shift($params);
                    $fire_args=array();

                    foreach ($params as $keyy => $valuee) {
                        if (!isset($argv[$keyy+2])) {
                            echo_array(array('result' => '','error'=>'parameter is no found' ));
                            exit;
                        }
                        $fire_args[]=trim($argv[$keyy+2]);
                    }
                    //$fire_args_str=implode(",",$fire_args);
                    echo_array($lib->$method($this->mode,...$fire_args));
                    exit;
                }
            }
            echo_array(array('result' => '','error'=>'method is no found' ));
            exit;
        }elseif($this->mode=='cgi'){
            $ip = san_ip($_SERVER['REMOTE_ADDR']);
            $ip = filter_var($ip, FILTER_VALIDATE_IP);
            if ($this->config['public_api'] == false && !in_array($ip, $this->config['allowed_hosts'])) {
                echo json_encode(array('result' => '','error'=>'private-api' ));
            }

            $method = $_GET['m'];
            if (!empty($_POST['data'])) {
                $data = json_decode($_POST['data'], true);
            }elseif(empty($_POST['data']) and !empty($_POST)){
                $data = $_POST;
            }else {
                $data = $_GET;
                unset($data['m']);
            }

            $commandlib=array('Blockchain','Mining','Network','Wallet');

            foreach ($commandlib as $value) {
                $lib=$value::getInstance();
                if (method_exists($lib,$method)) {
                    $p = new ReflectionMethod($value,$method);
                    $params=$p->getParameters();

                    array_shift($params);        
                    $fire_args=array();

                    foreach ($params as $valuee) {
                        if (!isset($data[$valuee->name])) {
                            echo json_encode(array('result' => '','error'=>'parameter is no found'));
                            exit;
                        }
                        $fire_args[]=trim($data[$valuee->name]);
                        //$this->log($valuee->name.'  '.trim($data[$valuee->name]));
                    }

                    //$fire_args_str=implode(",",$fire_args);
                    //$this->log($method.'  '.$this->mode.','.$fire_args_str);
                    echo json_encode($lib->$method($this->mode,...$fire_args));
                    exit;
                }
            }
            echo json_encode(array('result' => '','error'=>'method is no found' ));
            exit;
        }


    }

}
$Uinterface=new Uinterface();
$Uinterface->main();