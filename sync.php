<?php
// version: 20190227
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
include __DIR__.'/class/cache.php';
include __DIR__.'/lib/OriginSql.lib.php';
// include __DIR__.'/lib/PostThreads.lib.php';
include __DIR__.'/lib/Security.lib.php';
include __DIR__.'/function/function.php';
include __DIR__.'/function/core.php';

class sync extends base{
	function __construct(){
		parent::__construct();
        if ($this->info['cli'] != true) {
            echo "\nneed to run cli modle";
            exit;
        }
	}

	private function check_lock(){
		$res=cache::get('sync_lock');
		if ($res=='lock') {
			die("Sync lock\n");
		}
	}
	private function set_new_lock(){
		cache::set('sync_lock','lock',900);
		
	}

	private function un_lock(){
		cache::set('sync_lock','unlock',900);
	}

	public function main(){
		//check lock
		$this->check_lock();
		//set new lock
		$this->set_new_lock();
		$this->log('sync->main Starting sync',0,true);

		$peer=Peerinc::getInstance();
		$block = Blockinc::getInstance();
		$transaction = Transactioninc::getInstance();
		$mempool = Mempoolinc::getInstance();
		$sec=Security::getInstance();
		$sql=OriginSql::getInstance();
		// checking peers

		// delete the dead peers
		echo "delete_fails_peer\n";
		$peer->delete_fails_peer();
		
		//install peer
		$peer->install_config_peer();

		//get more peer
		$totla_peer_count=$peer->get_peer_all_count();
		if ($totla_peer_count < $this->config['db_max_peers']) {
			$needpeernum=$this->config['db_max_peers']-$totla_peer_count;
			$r=$peer->get_peer_max($this->config['max_peer']);

			$peer->get_more_peer($r,$needpeernum);
		}


		///////////////////////////Get the most reasonable maximum height of common blocks
		$current = $block->current();
		$r=$peer->get_peer_max($this->config['max_peer']);


		$max_height=0;
		$record_p=[];
		$most_comm_peer=[];
		$most_comm_num=[];
		
		foreach ($r as $key => $value) {
			echo 'loop peer:'.$key.'  '.$value['hostname']."\n";
			//get data
			$data = $peer->peer_post($value['hostname']."/peer.php?q=currentBlock", [], 5);

			//set fails
			if ($data==false) {
				$peer->update_peer_fails($value['hostname'],$value['fails']+1);
				continue;
			}else{
				if (($value['fails']-1)>0) {
					$peer->update_peer_fails($value['hostname'],$value['fails']-1);
				}
			}
			//set fails end
			//
			if (isset($data['from_host'])) {
				$from_host=$data['from_host'];
			}else{
				continue;
			}
			$data_block=$data['data'];
			$data_trx=$data['trx_data'];
			$miner_public_key=$data['miner_public_key'];
			$miner_reward_signature=$data['miner_reward_signature'];
			$mn_public_key=$data['mn_public_key'];
			$mn_reward_signature=$data['mn_reward_signature'];

			//sec
			$data_block['id'] = $sec->field('san',$data_block['id']);
			$data_block['height'] = $sec->field('num',$data_block['height']);

			//set stuckfail
			if ($data_block['height']<$current['height']) {
				if (($current['height']-$data_block['height'])>=500) {
					$peer->update_peer_stuckfail($value['hostname'],($value['stuckfail']+1),time()+7200);
				}
				continue;
			}else{
				if (($value['stuckfail']-1)>0) {
					$peer->update_peer_stuckfail($value['hostname'],($value['stuckfail']-1),'');
				}
			}
			//
			//active peer
			$active_peer = array('alike' => [], 'high'=>[]);
			if ($data_block['height']==$current['height']) {
				$active_peer['alike'][]=$value['hostname'];
			}elseif ($data_block['height']>$current['height']) {
				$active_peer['high'][]=$value['hostname'];
			}
			//
			//record height hash and peer
			if (!isset($most_comm_num[$data_block['id']])) {
				$most_comm_num[$data_block['id']]=0;
			}
			if (!isset($most_comm_peer[$data_block['id']])) {
				$most_comm_peer[$data_block['id']]=[];
			}
			$most_comm_num[$data_block['id']]++;
			$most_comm_peer[$data_block['id']][]=$value['hostname'];

			//
			if (!isset($record_p[$data_block['height']])) {
				$record_p[$data_block['height']][]=array(
								'hash' => $data_block['id'],
								'diff'=>$data_block['difficulty'],
								'trx_numbet'=>count($data_trx),
								'peer'=>[$value['hostname']],
								'peer_number'=>1
								);
			}else{
				$is_t=false;
				foreach ($record_p[$data_block['height']] as $keyy=>$valuee) {
					if ($valuee['hash']==$data_block['id']) {
						$record_p[$data_block['height']][$keyy]['peer_number']++;
						$record_p[$data_block['height']][$keyy]['peer'][]=$value['hostname'];
						$is_t=true;
						break;
					}
				}
				if ($is_t==false) {
					$record_p[$data_block['height']][]=array(
						'hash' => $data_block['id'],
						'diff'=>$data_block['difficulty'],
						'trx_numbet'=>count($data_trx),
						'peer'=>[$value['hostname']],
						'peer_number'=>1
					);
				}

			}
			//max height

			if ($data_block['height']>$max_height) {
				$max_height=$data_block['height'];
				
			}
		}
		//loop screen block
		$bblock=array(	'hash' => '',
						'diff'=>0,
						'trx_numbet'=>0,
						'peer'=>[],
						'peer_number'=>0);

		foreach ($record_p[$max_height] as $key => $value) {
			if ($value['peer_number']>$bblock['peer_number']) {
				$bblock['hash']=$value['hash'];
				$bblock['diff']=$value['diff'];
				$bblock['trx_numbet']=$value['trx_numbet'];
				$bblock['peer']=$value['peer'];
				$bblock['peer_number']=$value['peer_number'];
			}

			if ($value['peer_number']==$bblock['peer_number'] and $value['diff']<$bblock['diff']) {
				$bblock['hash']=$value['hash'];
				$bblock['diff']=$value['diff'];
				$bblock['trx_numbet']=$value['trx_numbet'];
				$bblock['peer']=$value['peer'];
				$bblock['peer_number']=$value['peer_number'];
			}
			if ($value['peer_number']==$bblock['peer_number'] and $value['diff']==$bblock['diff'] and $value['trx_numbet']>$bblock['trx_numbet']) {
				$bblock['hash']=$value['hash'];
				$bblock['diff']=$value['diff'];
				$bblock['trx_numbet']=$value['trx_numbet'];
				$bblock['peer']=$value['peer'];
				$bblock['peer_number']=$value['peer_number'];
			}
		}

		// $most_comm_num[$data_block['hash']]++;
		// $most_comm_peer[$data_block['hash']][]=$value['hostname'];
		$ii=0;	$most_comm_hash='';
		foreach ($most_comm_num as $key => $value) {
			if ($value>$ii) {
				$ii=$value;
				$most_comm_hash=$key;
			}
		}
		$most_comm_hash_peer=$most_comm_peer[$most_comm_hash];

		echo 'current height:'.$current['height']."\n";
		echo 'Max height:'.$max_height."\n";
		echo 'Max hash:'.$bblock['hash']."\n";
		echo 'Most hash:'.$most_comm_hash."\n";
		//start sync
		cache::set('sync_synchronization_time',time(),0);
		if ($max_height==$current['height']) {
			echo 'No synchronization required';
			$this->un_lock();
			exit;
		}
		//
		cache::set('bestblockhash',$most_comm_hash,0);

		foreach ($bblock['peer'] as $value) {
			$current=$block->current();
			$star_height=$current['height']+1;
			$end_height=$max_height;
			for ($i=$star_height; $i <= $end_height; $i++) {
				$current=$block->current();
				$data =$peer->peer_post($value."/peer.php?q=getBlock", ["height" => $i],60);
				echo 'get height start:'.($i)."\n";
				if ($data === false) {
					echo 'get block fails height:'.$data['data']['height']."\n";
					break;
				}

	            if (!$block->mine(
	                $data['miner_public_key'],
	                $data['data']['nonce'],
	                $data['data']['argon'],
	                $data['data']['difficulty'],
	                $current['id'],
	                $current['height'],
	                $data['data']['date']
	            )) {
	            	echo 'check miner fails height:'.$data['data']['height']."\n";
	                break;
	            }
				//check block
				if ($block->check($data)==false) {
					echo 'check fails height:'.$data['data']['height']."\n";
					break;
				}

				//check trx
				foreach ($data['trx_data'] as $valueee) {
					if ($valueee['height']!=$data['data']['height']) {
						echo 'check trx height fails height:'.$data['data']['height']."\n";
						break 2;
					}
					if ($valueee['block']!=$data['data']['id']) {
						echo 'check trx id fails height:'.$data['data']['height']."\n";
						break 2;
					}
					if ($transaction->check($valueee)==false) {
						echo 'check trx check fails height:'.$data['data']['height']."\n";
						break 2;
					}
				}
				// add block;
				echo "add block ".$data['data']['height']."\n";
		        $sql->add('block',array(
	                                    'id'=>$data['data']['id'],
	                                    'generator'=>$data['data']['generator'],
	                                    'height'=>$data['data']['height'],
	                                    'date'=>$data['data']['date'],
	                                    'nonce'=>$data['data']['nonce'],
	                                    'signature'=>$data['data']['signature'],
	                                    'difficulty'=>$data['data']['difficulty'],
	                                    'argon'=>$data['data']['argon'],
	                                    'transactions'=>$data['data']['transactions']
		        ));
				foreach ($data['trx_data'] as $valueee) {
					$transaction->add_transactions_delete_mempool_from_block($valueee['id'],$valueee['public_key'],$data['data']['id'],$valueee['height'],$valueee['dst'],$valueee['val'],$valueee['fee'],$valueee['signature'],$valueee['version'],$valueee['message'],$valueee['date']);
				}
					//
				cache::set('sync_last_time',time(),0);
			}
		}
		$current=$block->current();
		
		// deleting mempool transactions older than 10 days
		$mempool->delete_than_days(10);

		//re send local mem
		$res=$sql->select('mem','id',0,array("peer='local'"),'height asc',120);
		$this->log('sync->main rebroadcast locals mempool',0,true);
		foreach ($res as $key => $value) {
			$sql->update('mem',array('height'=>$current['height']),array("id='".$value['id']."'"));
	        $cmd=$sec->cmd($this->config['php_path'].'php send.php',['transaction',$value['id']]);
	        system($cmd);
		}
		//re send no local mem
		$res=$sql->select('mem','id',0,array("peer!='local'"),'height asc',120);
		$this->log('sync->main rebroadcast peer mempool',0,true);
		foreach ($res as $key => $value) {
			$sql->update('mem',array('height'=>$current['height']),array("id='".$value['id']."'"));
	        $cmd=$sec->cmd($this->config['php_path'].'php send.php',['transaction',$value['id']]);
	        system($cmd);
		}

		//random peer check
		$peer->random_peer_check($this->config['max_test_peers']);


		//clean tmp files

		//recheck the last blocks
		$this->log('sync->main Rechecking blocks',0,true);
		
		echo "All checked blocks are ok\n";
		$this->log('sync->main Finishing sync',0,true);
		$this->un_lock();
	}

	// The Microrectification code comes from arionum https://github.com/arionum/node
	// Modify partial logic
	public function Microrectification($from_host){
		$Peerinc=Peerinc::getInstance();
        if ($Peerinc->get_peer_count_from_hostname($from_host)==false) {
            echo "Invalid node\n";
		    exit;
        }
        $block = Blockinc::getInstance();
		$current=$block->current();
		//check lock
		$this->check_lock();
		//set new lock
		$this->set_new_lock();

        //post
        $data = $Peerinc->peer_post($from_host."/peer.php?q=getBlock",["height" => $current['height']]);
        if (!$data) {
            echo "Invalid getBlock result\n";
		    $this->un_lock();
		    exit;
        }
        $data['data']['id'] = san($data['data']['id']);
        $data['data']['height'] = san($data['data']['height']);
        // nothing to be done, same blockchain
        if ($data['data']['id'] == $current['id']) {
            echo "Same block\n";
		    $this->un_lock();
		    exit;
        }

        //
        if ($data['data']['transactions']<$current['transactions']) {
            echo "Block hex larger than current\n";
		    $this->un_lock();
		    exit;
        }

        //
        if ($data['data']['transactions']==$current['transactions']) {
        	$r=$peer->get_peer_max($this->config['max_peer']);


        	$block_id_arr=[];
        	$block_host_arr=[];
        	$max_block_id='';
        	$max_block_id_num=0;
        	foreach ($r as $key => $value) {
        		$dataa = $Peerinc->peer_post($value['hostname']."/peer.php?q=getBlock",["height" => $current['height']]);
        		if ($dataa) {

        			if (!isset($block_id_arr[$dataa['data']['id']])) {
        				$block_id_arr[$dataa['data']['id']]=0;
        			}else{
        				$block_id_arr[$dataa['data']['id']]++;
        			}

        			if (!isset($block_host_arr[$dataa['data']['id']])) {
        				$block_host_arr[$dataa['data']['id']]=[$value['hostname']];
        			}else{
        				$block_host_arr[$dataa['data']['id']][]=$value['hostname'];
        			}

        			if ($block_id_arr[$dataa['data']['id']]>=$max_block_id_num) {
        				$max_block_id_num=$block_id_arr[$dataa['data']['id']];
        				$max_block_id=$dataa['data']['id'];
        			}
        		}
        	}
        	//
        	if ($current['id']==$max_block_id) {
	            echo "no need pop\n";
			    $this->un_lock();
			    exit;
        	}

        	if ($current['id']!=$max_block_id and $max_block_id!=$data['data']['id']) {
        		foreach ($block_host_arr[$max_block_id] as $value) {
        			$dataaa = $Peerinc->peer_post($value['hostname']."/peer.php?q=getBlock",["height" => $current['height']]);
        			if ($dataaa) {
        				$data=$dataaa;
        				break;
        			}
        		}
        	}

        	if ($current['id']!=$max_block_id and $max_block_id==$data['data']['id']) {
        		
        	}


        }
        //check
        if (!$block->check($data)) {
            echo "block check false\n";
		    $this->un_lock();
		    exit;
        }

        // add the new block
        echo "Starting to sync last block from ".$from_host."\n";


        //check trx
        foreach ($data['trx_data'] as $valueee) {
            if ($valueee['height']!=$data['data']['height']) {
            	$this->log('sync->Microrectification check trx height is false',0,true);
                $this->echo_display_json(false,'check trx height is false');
                exit;
            }
            if ($valueee['block']!=$data['data']['id']) {
            	$this->log('sync->Microrectification check trx block is false',0,true);
                $this->echo_display_json(false,'check trx block is false');
                exit;
            }
        }

        // delete the last block
        $block->pop(1);
        cache::set('validfork',$data['data']['height'],0);
        
        $res = $block->add(
            $data['miner_public_key'],
            $data['data']['height'],
            $data['data']['nonce'],
            $data['trx_data'],
            $data['data']['date'],
            $data['data']['difficulty'],
            $data['data']['signature'],
            $data['miner_reward_signature'],
            $data['mn_reward_signature'],
            $data['data']['argon']
        );

        if (!$res) {
        	$this->log('sync->Microrectification add block false',0,true);
	    	$this->un_lock();
	    	exit;
        }
        $this->log('sync->Microrectification Synced block from '.$from_host.'-'.$data['data']['height'],0,true);
	    $this->un_lock();
	    exit;
	}
	// The Microsynchronization code comes from arionum https://github.com/arionum/node
	// Modify partial logic
	public function Microsynchronization($from_host,$end_height){
		$Peerinc=Peerinc::getInstance();
        // 
        if ($Peerinc->get_peer_count_from_hostname($from_host)==false) {
            echo "Invalid node\n";
		    exit;
        }
		$block = Blockinc::getInstance();
		$current=$block->current();
		//check lock
		$this->check_lock();
		//set new lock
		$this->set_new_lock();
		$start=$current['height']+1;
		$end=$end_height+1;


		for ($i=$start; $i < $end; $i++) { 

			$data = $Peerinc->peer_post($from_host."/peer.php?q=getBlock",["height" => $i]);
			//echo_array($data);
	        if (!$data) {
	        	$this->log('sync->Microsynchronization Invalid getBlock result false',0,true);
	            echo "Invalid getBlock result\n";
	            break;
	        }

        	$data['data']['id'] = san($data['data']['id']);
        	$data['data']['height'] = san($data['data']['height']);

	        if (!$block->check($data)) {
	        	echo "block check false\n";
	        	$this->log('sync->Microsynchronization Synced block block check false',0,true);
	            break;
	        }

	        echo "Starting to sync last block ".$data['data']['height']."\n";

	        //check trx
	        foreach ($data['trx_data'] as $valueee) {
	            if ($valueee['height']!=$data['data']['height']) {
	            	$this->log('sync->Microsynchronization check trx height is false',0,true);
	                $this->echo_display_json(false,'check trx height is false');
	                exit;
	            }
	            if ($valueee['block']!=$data['data']['id']) {
	            	$this->log('sync->Microsynchronization check trx block is false',0,true);
	                $this->echo_display_json(false,'check trx block is false');
	                exit;
	            }
	        }
	        // add the block to the blockchain
	        $res = $block->add(
	            $data['miner_public_key'],
	            $data['data']['height'],
	            $data['data']['nonce'],
	            $data['trx_data'],
	            $data['data']['date'],
	            $data['data']['difficulty'],
	            $data['data']['signature'],
	            $data['miner_reward_signature'],
	            $data['mn_reward_signature'],
	            $data['data']['argon']
	        );

	        if (!$res) {
	        	$this->log('sync->Microsynchronization Block add: could not add block - false'.$data['data']['height'],0,true);
	            break;
	        }else{
	        	$this->log('sync->Microsynchronization Synced block from $from_host'.$data['data']['height'],0,true);
	        }
		}
	    if (!$this->un_lock()) {
	    	$this->log('sync->Microsynchronization del lock false',0,true);
	    }else{
	    	$this->log('sync->Microsynchronization del lock true',0,true);
	    }
	    
	    exit;
	}
}

date_default_timezone_set("UTC");
// set_time_limit(0);
// error_reporting(0);

$sync=new sync;

if (!isset($argv[1])) {
	$sync->main();
	exit;
}
$q = trim($argv[1]);
if($q=='Microrectification'){
	if (isset($argv[2])) {
		$from_host=trim($argv[2]);
	}else{
		exit;
	}
	$sync->Microrectification($from_host);
}elseif($q=='Microsynchronization'){
	if (isset($argv[2])) {
		$from_host=trim($argv[2]);
	}else{
		exit;
	}
	if (isset($argv[3])) {
		$end_height=trim($argv[3]);
	}else{
		exit;
	}	
	$sync->Microsynchronization($from_host,$end_height);
}

?>