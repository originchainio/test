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
class Propagateinc extends base{
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

    public function block($id='current',$to_hostname='all',$linear=true){
        if ($id==='') {
            $id='current';
        }
        if ($to_hostname==='') {
            $to_hostname='all';
        }
        if ($linear==='') {
            $linear=true;
        }
    	$sql=OriginSql::getInstance();
    	$r=[];
    	if ($to_hostname=='all' or $to_hostname=='') {
	    	if ($linear==true) {
	    		$orderby='RAND()';
	    		$limit=intval($this->config['block_propagation_peers']);
	    	}else{
	    		$orderby='';
	    		$limit=0;	
	    	}

	    	$r=$sql->select('peer','hostname',0,array("blacklisted<".time()),$orderby,$limit);
    	}else{
    		$r[]=array('hostname'=>$to_hostname);
    	}
    	//id
    	$block=Blockinc::getInstance();
    	if ($id=='current') {
    		$current = $block->current();
    		$data = $block->export_for_other_peers($current['id']);
    	}else{
    		$data = $block->export_for_other_peers($id);
    	}
    	if (!$data) {	echo "Invalid Block data";	exit;	}
    	//send
    	foreach ($r as $key => $value) {
    		echo "Block sent to ".$value['hostname']."\n";
		    if ($this->config['local_node']==false) {
		        $data['from_host']=$config['hostname'];
		    }
		    $t[$key]=new postthreads($value['hostname']."/peer.php?q=submitBlock",json_encode($data));
		    $t[$key]->start();
    	}
    	//

    }

    public function transaction($mem_id){
    	$sql=OriginSql::getInstance();
    	$mem = Mempoolinc::getInstance();
    	//select
    	$data = $mem->get_mempool_from_id($mem_id);
	    if (!$data) {
	        echo "Invalid transaction id\n";
	        exit;
	    }
    	if ($data['peer'] == "local") {
    		$data['peer']=$this->config['hostname'];
    		$orderby='';
    		$limit=0;
    	}else{
    		$orderby='RAND()';
    		$limit=intval($this->config['transaction_propagation_peers']);	
    	}

    	$r=$sql->select('peer','hostname',0,array("blacklisted<".time()),$orderby,$limit);

	    foreach ($r as $key => $value) {
	    	echo "Transaction sent to ".$value['hostname']."\n";
		    $t[$key]=new postthreads($value['hostname']."/peer.php?q=submitTransaction",json_encode($data));
		    $t[$key]->start();
	    }
    }




}
?>