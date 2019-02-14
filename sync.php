<?php
// version: 20190214 test
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
		if (cache::get('sync_lock')!=false) {
			die("Sync lock\n");
		}
	}
	private function set_new_lock(){
		cache::set('sync_lock',time(),900)
	}

	private function un_lock(){
		cache::delete('sync_lock');
	}

	public function main(){
		//check lock
		$this->check_lock();
		//set new lock
		$this->set_new_lock();
		echo "Sleeping for 3 seconds\n";
		sleep(3);
		$this->log("Starting sync");

		// update the last time sanity ran, to set the execution of the next run
		$Configinc=Configinc::getInstance();
		$Configinc->settime();

		$peer=Peerinc::getInstance();
		$block = Blockinc::getInstance();
		$transaction = Transactioninc::getInstance();
		$mempool = Mempoolinc::getInstance();
		$config=Configinc::getInstance();
		$sec=Security::getInstance();
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

		//get more peer end
		//contact all start
		$peer_block=[];
		$active_peers=[];
		$most_common_id = "";
		$most_common_num = 0;
		$largest_height=1;
		///////////////////////////Get the most reasonable maximum height of common blocks
		$current = $block->current();
		$r=$peer->get_peer_max($this->config['max_peer']);
		foreach ($r as $value) {
			$data = $peer->peer_post($value['hostname']."/peer.php?q=currentBlock", [], 5);
			// set fails
			if ($data === false) {
				$peer->update_peer_fails($value['hostname'],$value['fails']+1);
				continue;
			}else{
				if ($value['fails']!=0) {
					$peer->update_peer_fails($value['hostname'],0);
				}
			}
			//
			$data=$data['data'];

			$data['id'] = $sec->field('san',$data['id']);
			$data['height'] = $sec->field('num',$data['height']);

		    //Contrast height setting stuckfail
		    if ($data['height'] < $current['height'] - 500) {
		    	$peer->update_peer_stuckfail($value['hostname'],$value['stuckfail']+1,time()+7200);
		        continue;
		    } else {
				if ($value['stuckfail']!=0) {
					$peer->update_peer_stuckfail($value['hostname'],0,'');
				}
		    }
		    //Join the active peer list
		    $active_peers[]=$value;

		    $peer_block['peer'][$data['id']][] = $value['hostname'];

		    if (!isset($peer_block['count'][$data['id']])) {
		    	$peer_block['count'][$data['id']]=0;
		    }
		    $peer_block['count'][$data['id']]++;

		    $peer_block['data'][$data['id']] = $data;

		    //peer common 
		    if ($peer_block['count'][$data['id']] > $most_common_num) {
		        $most_common_id = $data['id'];
		        $most_common_num = $peer_block['count'][$data['id']];
		    }
		    //peer max height
		    if ($data['height'] > $largest_height) {
		        $largest_height = $data['height'];
		        $largest_height_block = $data['id'];  	
		    }elseif($data['height'] == $largest_height && $data['id'] != $largest_height_block){

		    	if ($data['difficulty'] == $peer_block['data'][$largest_height_block]['difficulty']) {
		            //diff identical
		            if ($most_common_id == $data['id']) {
		                $largest_height = $data['height'];
		                $largest_height_block = $data['id'];
		            } else {
		            	
		                $no1 = hexdec(substr(coin2hex($largest_height_block), 0, 12));
		                $no2 = hexdec(substr(coin2hex($data['id']), 0, 12));
		                if (gmp_cmp($no1, $no2) == 1) {
		                    $largest_height = $data['height'];
		                    $largest_height_block = $data['id'];
		                }
		            }

		    	}elseif($data['difficulty'] < $peer_block['data'][$largest_height_block]['difficulty']){
		            //diff min
		            $largest_height = $data['height'];
		            $largest_height_block = $data['id'];	
		    	}
		    }

		}

		cache::set('bestblockhash',$most_common_id,0);
		//////////////////////////////////
		//contact all end
		echo "Most common bock: $most_common_id\n";
		echo "Most common num: $most_common_num\n";
		echo "Max height: $largest_height\n";
		echo "Current block height: $current[height]\n";

		$block_parse_failed=false;
		//if no max height
		if ($current['height'] < $largest_height && $largest_height > 1) {
			//sync
			$block_parse_failed=$this->current2largest($peer_block['peer'][$largest_height_block],$largest_height,$largest_height_block,$most_common_id,$most_common_num,count($active_peers));

			$current=$block->current();
		    $config->set_val('sanity_sync',time());
		}
		//

		// deleting mempool transactions older than 10 days
		$mempool->delete_than_days(10);

		$Security=Security::getInstance();
		$sql=OriginSql::getInstance();
		//re send local mem
		$res=$sql->select('mem','id',0,array("peer='local'"),'height asc',120);
		$this->log('rebroadcast locals mempool');
		foreach ($res as $key => $value) {
			$sql->update('mem',array('height'=>$current['height']),array("id='".$value['id']."'"));
	        $cmd=$Security->cmd($this->config['php_path'].'php send.php',['transaction',$value['id']]);
	        system($cmd);
		}
		//re send no local mem
		$res=$sql->select('mem','id',0,array("peer!='local'"),'height asc',120);
		$this->log('rebroadcast peer mempool');
		foreach ($res as $key => $value) {
			$sql->update('mem',array('height'=>$current['height']),array("id='".$value['id']."'"));
	        $cmd=$Security->cmd($this->config['php_path'].'php send.php',['transaction',$value['id']]);
	        system($cmd);
		}

		//random peer check
		$peer->random_peer_check($this->config['max_test_peers']);


		//clean tmp files

		//recheck the last blocks
		$this->log("Rechecking blocks");
		$config->set_val('sanity_last',time());
		echo "All checked blocks are ok\n";
		$this->log("Finishing sanity");

		$this->un_lock();
	}

	// if no last height
	private function current2largest($peers,$largest_height,$largest_height_block,$most_common_id,$most_common_num,$total_active_peers){
		$sec=Security::getInstance();
		$peer=Peerinc::getInstance();
		//check local end block
		$block=Blockinc::getInstance();
		$current=$block->current();
		$transaction=Transactioninc::getInstance();

		foreach ($peers as $value) {
			//
			echo 'peer:'.$value."\n";
			$data = $peer->peer_post($value."/peer.php?q=getBlock", ["height" => $current['height']], 60);
			//echo_array($data);

			if ($data === false) {	continue;	}
			$data=$data['data'];
			$data['id'] = $sec->field('san',$data['id']);
			$data['height'] = $sec->field('num',$data['height']);

			echo 'get data:'.$data['height']."\n";
        	if ($data['id'] != $current['id'] && $data['id'] == $most_common_id && ($most_common_num / $total_active_peers) > 0.90) {
        		//如果已经是常见块 但是和我们数据库中的id不一样
        		$block->delete($current['height'] - 3);
        		$current = $block->current();
        		$data = $peer->peer_post($value."/peer.php?q=getBlock", ["height" => $current['height']]);
	            if ($data === false) {break;}
				$data=$data['data'];
				$data['id'] = $sec->field('san',$data['id']);
				$data['height'] = $sec->field('num',$data['height']);

        	}elseif($data['id'] != $current['id'] && $data['id'] != $most_common_id){
        		$invalid = false;
        		$last_good = $current['height'];
	            for ($i = $current['height'] - 30; $i < $current['height']; $i++) {
	                $data = $peer->peer_post($value."/peer.php?q=getBlock", ["height" => $i]);
	                if ($data === false) {	$invalid = true;	break;	}
					$data=$data['data'];
					$data['id'] = $sec->field('san',$data['id']);
					$data['height'] = $sec->field('num',$data['height']);


	                $ext = $block->get_block_from_height($i);
	                if ($i == $current['height'] - 30 && $ext['id'] != $data['id']) {
	                    $invalid = true;
	                    break;
	                }

	                if ($ext['id'] == $data['id']) {
	                    $last_good = $i;
	                }
	            }
	            if ($last_good==$current['height']-1) {
	            	$block->pop(1);
	            }
	            if ($invalid == false) {
	                $cblock = [];
	                for ($i = $last_good; $i <= $largest_height; $i++) {
	                    $data = $peer->peer_post($value."/peer.php?q=getBlock", ["height" => $i]);
	                    if ($data === false) {
	                        $invalid = true;
	                        break;
	                    }
						// $data=$data['data'];
						// $data['id'] = $sec->field('san',$data['id']);
						// $data['height'] = $sec->field('num',$data['height']);

	                    $cblock[$i] = $data;
	                }
	                // check if the block mining data is correct
	                for ($i = $last_good + 1; $i <= $largest_height; $i++) {
	                    if (!$block->mine(
	                        $cblock[$i]['miner_public_key'],
	                        $cblock[$i]['data']['nonce'],
	                        $cblock[$i]['data']['argon'],
	                        $cblock[$i]['data']['difficulty'],
	                        $cblock[$i - 1]['data']['id'],
	                        $cblock[$i - 1]['data']['height'],
	                        $cblock[$i]['data']['date']
	                    )) {
	                        $invalid = true;
	                        break;
	                    }
	                }
		            // if the blockchain proves ok, delete until the last block
		            if ($invalid == false) {
		            	echo "Changing fork, deleting $last_good"."\n";
		                $this->log("Changing fork, deleting $last_good", 1);
		                $block->delete($last_good);
		                $current = $block->current();
		                $data = $current;
		            }
	            }

        	}
	        // if current still doesn't match the data, something went wrong
	        if ($data['id'] != $current['id']) {
	            continue;
	        }

		}

		//while peer sync blocks

		while ($current['height']<$largest_height) {

			foreach ($peers as $value) {
				//要重新获取本机块
				$current=$block->current();
				$data =$peer->peer_post($value."/peer.php?q=getBlocks", ["height" => $current['height']+1],60);
				//echo_array($data);
				echo 'get height start:'.($current['height']+1)."\n";

				//获取失败
				if ($data === false) {
					// $peer->update_peer_fails($value,$value['fails']+1);
					continue;
				}
				$sql=OriginSql::getInstance();
				foreach ($data as $valuee) {
					if ($valuee['data']['height']<=$current['height']) {
						continue;
					}

					//check block
					if ($block->check($valuee)==false) {
						break;
					}
					//check trx
					foreach ($valuee['trx_data'] as $valueee) {
						if ($valueee['height']!=$valuee['data']['height']) {
							break 2;
						}
						if ($valueee['block']!=$valuee['data']['id']) {
							break 2;
						}
						if ($transaction->check($valueee)==false) {
							break 2;
						}
					}
					// add block;
					echo "add block ".$valuee['data']['height']."\n";
			        $sql->add('block',array(
		                                    'id'=>$valuee['data']['id'],
		                                    'generator'=>$valuee['data']['generator'],
		                                    'height'=>$valuee['data']['height'],
		                                    'date'=>$valuee['data']['date'],
		                                    'nonce'=>$valuee['data']['nonce'],
		                                    'signature'=>$valuee['data']['signature'],
		                                    'difficulty'=>$valuee['data']['difficulty'],
		                                    'argon'=>$valuee['data']['argon'],
		                                    'transactions'=>$valuee['data']['transactions']
			        ));
					foreach ($valuee['trx_data'] as $valueee) {
						$transaction->add_transactions_delete_mempool_from_block($valueee['id'],$valueee['public_key'],$valuee['data']['id'],$valueee['height'],$valueee['dst'],$valueee['val'],$valueee['fee'],$valueee['signature'],$valueee['version'],$valueee['message'],$valueee['date']);
					}

					///////////////
				}
			}
			//
			sleep(5);
		}
		return true;
	}


	public function dev(){
	    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
	    ini_set("display_errors", "on");
	}


	public function Microrectification($from_host){
		$block = Blockinc::getInstance();
		$Peerinc=Peerinc::getInstance();
		$current=$block->current();
		//check lock
		$this->check_lock();
		//set new lock
		$this->set_new_lock();
        // the microsanity runs only against 1 specific peer
        if ($Peerinc->get_peer_count_from_hostname($from_host)==false) {
            echo "Invalid node - $arg2\n";
		    $this->un_lock();
		    exit;
        }

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

        // transform the first 12 chars into an integer and choose the blockchain with the biggest value
        $no1 = hexdec(substr(coin2hex($current['id']), 0, 12));
        $no2 = hexdec(substr(coin2hex($data['data']['id']), 0, 12));

        if (gmp_cmp($no1, $no2) != -1) {
            echo "Block hex larger than current\n";
		    $this->un_lock();
		    exit;
        }
        
        if (!$block->check($data)) {
            echo "block check false\n";
		    $this->un_lock();
		    exit;
        }

        // delete the last block
        $block->pop(1);
        cache::set('validfork',$data['data']['height'],0);

        // add the new block
        echo "Starting to sync last block from $x[hostname]\n";
        $sql=OriginSql::getInstance();
        $res = $sql->add('block',array(
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
        if (!$res) {
            $this->log("Block add: could not add block - ".$data['data']['height']);
	    	$this->un_lock();
	    	exit;
        }
        $this->log("Synced block from $host - ".$data['data']['height']);
	    $this->un_lock();
	    exit;
	}
	public function Microsynchronization($from_host,$end_height){
		$block = Blockinc::getInstance();
		$Peerinc=Peerinc::getInstance();
		$current=$block->current();
		//check lock
		$this->check_lock();
		//set new lock
		$this->set_new_lock();
		$start=$current['height']+1;
		$end=$end_height+1;


		for ($i=$start; $i < $end; $i++) { 

			$data = $Peerinc->peer_post($from_host."/peer.php?q=getBlock",["height" => $i]);
	        if (!$data) {
	        	$this->log("Invalid getBlock result");
	            echo "Invalid getBlock result\n";
	            break;
	        }

        	$data['data']['id'] = san($data['data']['id']);
        	$data['data']['height'] = san($data['data']['height']);

	        if (!$block->check($data)) {
	        	echo "block check false\n";
	        	$this->log("Synced block block check false");
	            break;
	        }

	        echo "Starting to sync last block ".$data['data']['height']."\n";

	        //check trx
	        foreach ($data['trx_data'] as $valueee) {
	            if ($valueee['height']!=$data['data']['height']) {
	                $this->log('check trx height is false',1);
	                $this->echo_display_json(false,'check trx height is false');
	                exit;
	            }
	            if ($valueee['block']!=$data['data']['id']) {
	                $this->log('check trx block is false',1);
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
	        // $sql=OriginSql::getInstance();
	        // $res = $sql->add('block',array(
	        //                             'id'=>$data['data']['id'],
	        //                             'generator'=>$data['data']['generator'],
	        //                             'height'=>$data['data']['height'],
	        //                             'date'=>$data['data']['date'],
	        //                             'nonce'=>$data['data']['nonce'],
	        //                             'signature'=>$data['data']['signature'],
	        //                             'difficulty'=>$data['data']['difficulty'],
	        //                             'argon'=>$data['data']['argon'],
	        //                             'transactions'=>$data['data']['transactions']
	        // ));
	        if (!$res) {
	            $this->log("Block add: could not add block - ".$data['data']['height']);
	            break;
	        }else{
	        	$this->log("Synced block from $from_host - ".$data['data']['height']);
	        }
		}
	    $this->un_lock();
	    exit;
	}
}

date_default_timezone_set('PRC');
// set_time_limit(0);
// error_reporting(0);

$sync=new sync;

if (!isset($argv[1])) {
	$sync->main();
	exit;
}
$q = trim($argv[1]);
if ($q=='dev') {
	$sync->dev();
	$sync->main();
}elseif($q=='Microrectification'){
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