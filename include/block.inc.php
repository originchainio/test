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
class Blockinc extends base{
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
    // creates a new block on this node
    public function forge($nonce, $argon, $miner_public_key,$reward_miner_private_key){

        $nonce=trim($nonce);
        $argon=trim($argon);
        $miner_public_key=san($miner_public_key);
        $reward_miner_private_key=san($reward_miner_private_key);

        $current=$this->current();
        
        $difficulty=$this->get_next_difficulty($current);
        $sql=OriginSql::getInstance();
        $Account=Accountinc::getInstance();
        $Transaction=Transactioninc::getInstance();
        $Masternode=Masternodeinc::getInstance();
        $Peerinc=Peerinc::getInstance();
        $Mempool = Mempoolinc::getInstance();

        // the block's date timestamp must be bigger than the last block
        $date = time();
        if ($date <= $current['date']) {
            $this->log('block.inc->forge Date older than last block false',0,true);
            return false;
        }

        $miner_address = $Account->get_address_from_public_key($miner_public_key);
        $blacklist=$current['height']+1;

        $mn_winner_public_key=$sql->select('mn','*',1,array("status=1","blacklist<".$blacklist),'last_won ASC',1);

        if (!$mn_winner_public_key or $mn_winner_public_key['public_key']=="") {
            $mn_winner_public_key='';

        }else{
            $mn_winner_address = $Account->get_address_from_public_key($mn_winner_public_key['public_key']);
            $mn_winner_ip=$mn_winner_public_key['ip'];
            $mn_winner_public_key=$mn_winner_public_key['public_key'];
        }

        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine($miner_public_key, $nonce, $argon, $difficulty, $current['id'], $current['height'], $date)) {
            $this->log('block.inc->forge Invalid argon false',0,true);
            return false;
        }
        // get the mempool transactions
        $data = $Mempool->get_mempool_transaction_for_news($current['height']+1,$this->max_transactions());
        if ($data===false) {
            $this->log('block.inc->forge Forge get the mempool transactions false',0,true);
            return false;
        }
        

        //reward
        $this->log("block.inc->forge get reward",0,true);
        $block_reward = $this->reward($current['height']+1, $data);
        $mn_reward=$block_reward['mn_reward'];
        $miner_reward=$block_reward['miner_reward'];
        //miner reward
        $tran = [
            "height"     => $current['height']+1,
            "dst"        => $miner_address,
            "val"        => $miner_reward,
            "version"    => 0,
            "date"       => $date,
            "message"    => '',
            "fee"        => "0.00000000",
            "public_key" => $miner_public_key,
        ];
        $miner_signature = $Transaction->signature($tran['dst'],$tran['val'],$tran['fee'],$tran['version'],$tran['message'],$tran['date'],$tran['public_key'],$reward_miner_private_key);
        $tran['signature']=$miner_signature;
        $tran['id'] = $Transaction->hasha($tran['dst'],$tran['val'],$tran['fee'],$miner_signature,$tran['version'],$tran['message'],$tran['date'],$tran['public_key']);
        array_unshift($data,$tran);
        
        $mn_signature='';
        if ($mn_winner_public_key!='') {
            //mn reward
            $tran = [
                "height"     => $current['height']+1,
                "dst"        => $mn_winner_address,
                "val"        => $mn_reward,
                "version"    => 4,
                "date"       => $date,
                "message"    => '',
                "fee"        => "0.00000000",
                "public_key" => $mn_winner_public_key,
            ];
            $mn_signature = $Transaction->signature($tran['dst'],$tran['val'],$tran['fee'],$tran['version'],$tran['message'],$tran['date'],$tran['public_key'],$reward_miner_private_key);
            $tran['signature']=$mn_signature;
            $tran['id'] = $Transaction->hasha($tran['dst'],$tran['val'],$tran['fee'],$mn_signature,$tran['version'],$tran['message'],$tran['date'],$tran['public_key']);
            array_unshift($data,$tran);

            // Verify whether Mn can ping and attach a TRX to data
            $response = $Peerinc->peer_post($mn_winner_ip.'/peer.php?q=ping', [], 5);
            // $response='success';
            if ($response == "success") {
                $tran = [
                    "height"     => $current['height']+1,
                    "dst"        => $mn_winner_address,
                    "val"        => "0.00000000",
                    "version"    => 111,
                    "date"       => $date,
                    "message"    => $mn_winner_public_key.',0',
                    "fee"        => "0.00000000",
                    "public_key" => $mn_winner_public_key,
                ];
            }else{
                $tran = [
                    "height"     => $current['height']+1,
                    "dst"        => $mn_winner_address,
                    "val"        => "0.00000000",
                    "version"    => 111,
                    "date"       => $date,
                    "message"    => $mn_winner_public_key.',1',
                    "fee"        => "0.00000000",
                    "public_key" => $mn_winner_public_key,
                ];
            }
            $mn_signature = $Transaction->signature($tran['dst'],$tran['val'],$tran['fee'],$tran['version'],$tran['message'],$tran['date'],$tran['public_key'],$reward_miner_private_key);
            $tran['signature']=$mn_signature;
            $tran['id'] = $Transaction->hasha($tran['dst'],$tran['val'],$tran['fee'],$mn_signature,$tran['version'],$tran['message'],$tran['date'],$tran['public_key']);
            array_unshift($data,$tran);
        }

        // block signature
        $block_signature = $this->signature($miner_address,$current['height']+1,$date,$nonce,$data,$difficulty, $argon,$reward_miner_private_key);

        // add the block to the blockchain
        $res = $this->add($miner_public_key,$current['height']+1, $nonce, $data, $date, $difficulty,$block_signature, $miner_signature,$mn_signature, $argon);
        if (!$res) {
            $this->log('block.inc->forge Forge block false',0,true);
            return false;
        }
        $this->log('block.inc->forge Forge block finished true',0,true);
        return true;
    }

    public function add($miner_public_key,$height, $nonce, $data=array(), $date, $difficulty,$block_signature, $miner_signature,$mn_signature,$argon){

        $miner_public_key=san($miner_public_key);
        $height=intval($height);
        $nonce=trim($nonce);
        $date=intval($date);
        $block_signature=san($block_signature);
        $miner_signature=san($miner_signature);
        $mn_signature=san($mn_signature);
        $argon=trim($argon);

        $Account=Accountinc::getInstance();
        $Transaction=Transactioninc::getInstance();
        $Masternode=Masternodeinc::getInstance();
        $sql=OriginSql::getInstance();



        $miner_address = $Account->get_address_from_public_key($miner_public_key);
        // $mn_winner=$BlockLib->select('public_key',1,array("status=1","blacklist<".$height,"height<".$height-360),'last_won ASC',1)
        $mn_winner_public_key=$sql->select('mn','public_key',1,array("status=1","blacklist<".$height),'last_won ASC',1);

        if (!$mn_winner_public_key or $mn_winner_public_key['public_key']=="") {
            $mn_winner_public_key='';
        }else{
            $mn_winner_public_key=$mn_winner_public_key['public_key'];
            $mn_winner_address = $Account->get_address_from_public_key($mn_winner_public_key); 
        }

        // block_hash
        if ($height>1) {
            $prv_id=$this->get_block_from_height($height-1);
        }else{
            $this->log('block.inc->add add block height <1 false',0,true);
            return false;
        }
        
        $block_hash = $this->hasha($miner_public_key, $height, $date, $nonce, $data, $block_signature, $difficulty, $argon);


        // reward
        $block_reward = $this->reward($height, $data);
        $mn_reward=$block_reward['mn_reward'];
        $miner_reward=$block_reward['miner_reward'];
        //  check signature
        // if ($Transaction->check_signature($mn_winner_address,$mn_reward,"0.00000000",4,'',$date,$mn_winner_public_key, $mn_signature)==false) {
        //     return false;
        // }
        if ($Transaction->check_signature($miner_address,$miner_reward,"0.00000000",0,'',$date,$miner_public_key, $miner_signature)==false) {
            $this->log('block.inc->add block check trx sign false',0,true);
            return false;
        }

        // lock table to avoid race conditions on blocks
        $sql->lock_tables();

        // insert the block into the db
        $sql->beginTransaction();
        // add block

        $res = $sql->add('block',array(
                                    'id'=>$block_hash,
                                    'generator'=>$miner_address,
                                    'height'=>$height,
                                    'date'=>$date,
                                    'nonce'=>$nonce,
                                    'signature'=>$block_signature,
                                    'difficulty'=>$difficulty,
                                    'argon'=>$argon,
                                    'transactions'=>count($data)
        ));

        if (!$res) {
            // rollback and exit if it fails
            $sql->rollback();
            $sql->unlock_tables();
            $this->log('block.inc->add block insert block to database roolback false',0,true);
            return false;
        }
        // Cyclic transaction
        foreach ($data as $value) {
            $value['block']=$block_hash;
            // Check the legality of transactions
            $this->log('check trx '.$value['id'],1);
            if ($Transaction->check($value)==true) {
                $res=$Transaction->add_transactions_delete_mempool_from_block($value['id'],$value['public_key'],$block_hash,$height,$value['dst'],$value['val'],$value['fee'],$value['signature'],$value['version'],$value['message'],$value['date']);
                if ($res == false) {
                    // rollback and exit if it fails
                    $sql->rollback();
                    $sql->unlock_tables();
                    $this->log('block.inc->add add block add trx del mempool false',0,true);
                    return false;
                }
            }else{
                $sql->rollback();
                $sql->unlock_tables();
                $this->log('block.inc->add add block Transaction check false',0,true);
                return false;
            }
        }
        //


        // relese the locking as everything is finished
        $sql->commit();
        $sql->unlock_tables();
        $this->log('block.inc->add add block finished true',0,true);
        return true;
    }

    // current block
    public function current(){
        $sql=OriginSql::getInstance();
        $current = $sql->select('block','*',1,'','height DESC',1);
        if (!$current) {
            return $this->genesis();
        }
        return $current;
    }

    // returns the previous block
    public function prev($height = 0){
        if ($height==='') {
            $height = 0;
        }
        $height=intval($height);
        $sql=OriginSql::getInstance();

        if ($height == 1) {
            $current = $sql->select('block','*',1,array("height=1"),'height DESC',1);
        }else{
            if ($height == 0) {
                $current =$this->current();
                $current = $sql->select('block','*',1,array("height<".$current['height']),'height DESC',1);
            }else{
                $current = $sql->select('block','*',1,array("height<".$height),'height DESC',1);
            }
        }


        if (!$current) {
            $this->log('block.inc->prev get prev block false',0,true);
            return false;
        }

        return $current;
    }
    public function valid_difficulty($height,$difficulty){
        $height=intval($height);
        $prev=$this->prev($height);
        $dif=$this->get_next_difficulty($prev);

        if ($difficulty!=$dif) {
            $this->log('block.inc->valid_difficulty valid block diff false',0,true);
            return false;
        }else{
            return true;
        }
    }
    // The difficulty algorithm comes from arionum https://github.com/arionum/node
    public function get_next_difficulty($current=[]){
        $sql=OriginSql::getInstance();

        // for the first 10 blocks, use the genesis difficulty
        if ($current['height'] < 10) {
            return 9223372036854775800;
        }

        $blk_block = $sql->select('block','`date`, height',0,array("height<=".$current['height']),'height DESC',10);

        $total_time=0;

        for ($i=0;$i<9;$i++) {
            $time=$blk_block[$i]['date']-$blk_block[$i+1]['date'];
            $total_time+=$time;
        }
        
        // $prev_block=$this->prev($current['height']);
        $result=ceil($total_time/10);
        if ($result > 264) {
            $dif = bcmul($current['difficulty'], 1.05);
        } elseif ($result < 216) {
            // if lower, decrease by 5%
            $dif = bcmul($current['difficulty'], 0.95);
        } else {
            // keep current difficulty
            $dif = $current['difficulty'];
        }

        if (strpos($dif, '.') != false) {
            $dif = substr($dif, 0, strpos($dif, '.'));
        }

        //minimum and maximum diff
        if ($dif < 0.00000001) {
            $dif = 0.00000001;
        }
        if ($dif > 9223372036854775800) {
            $dif = 9223372036854775800;
        }
        return $dif;
    }
    // calculates the maximum block size
    public function max_transactions(){
        return 100;
    }

    // calculate the reward for each block
    public function reward($height, $data = []){
        $this->log('block.inc->reward',0,true);
        $height=intval($height);
        // starting reward
        $reward_base = 100;
        if ($height==2) {
            return array('miner_reward' => 2800000, 'mn_reward'=>1,'max_reward'=>2800001,'destroy_reward'=>0);
        }
        if (2<$height and $height<=10) {
            return array('miner_reward' => 65, 'mn_reward'=>35,'max_reward'=>100,'destroy_reward'=>0);
        }

        $factor = floor($height / 10800) / 100;
        $reward_base = $reward_base-$reward_base * $factor;
        if ($reward_base < 0) {
            $reward_base = 0;
            $reward=0;
        }else{
            $base_f=$reward_base/20;

            $sql=OriginSql::getInstance();
            $blk_block = $sql->select('block','difficulty',0,array("height<".$height),'height DESC',9);
            $cumulative=0;
            for ($i=0;$i<8;$i++) {
                if ($blk_block[$i]['difficulty']<$blk_block[$i+1]['difficulty']) {
                    $cumulative=$cumulative+$base_f;
                }else{
                    $cumulative=$cumulative-$base_f;
                }
            }

            $reward = ($base_f*10)+$cumulative;

            if ($reward<=0) {
                $reward=0;
            }
            if ($reward>$reward_base) {
                $reward=$reward_base;
            }
        }

        // calculate the transaction fees
        $fees = 0;
        if (count($data) > 0) {
            foreach ($data as $x) {
                $fees += $x['fee'];
            }
        }
        $destroy_reward=$reward_base-$reward;
        $reward=$reward+$fees;

        $mn_reward=number_format(round(0.35*$reward, 8), 8, ".", "");
        $miner_reward=number_format(round($reward-$mn_reward, 8), 8, ".", "");
        $max_reward=$reward_base;
        $destroy_reward=number_format(round($destroy_reward, 8), 8, ".", "");

        // $this->log('block.inc->reward mn_reward:'.$mn_reward.' '.$height,0,true);
        // $this->log('block.inc->reward miner_reward:'.$miner_reward.' '.$height,0,true);
        return array('miner_reward' => $miner_reward, 'mn_reward'=>$mn_reward,'max_reward'=>$max_reward,'destroy_reward'=>$destroy_reward);
    }
    public function reward_nofee($height){
        $this->log('block.inc->reward',0,true);
        $height=intval($height);
        // starting reward
        $reward_base = 100;
        if ($height==2) {
            return array('miner_reward' => 2800000, 'mn_reward'=>1,'max_reward'=>2800001,'destroy_reward'=>0);
        }
        if (2<$height and $height<=10) {
            return array('miner_reward' => 65, 'mn_reward'=>35,'max_reward'=>100,'destroy_reward'=>0);
        }

        $factor = floor($height / 10800) / 100;
        $reward_base = $reward_base-$reward_base * $factor;
        if ($reward_base < 0) {
            $reward_base = 0;
            $reward=0;
        }else{
            $base_f=$reward_base/20;

            $sql=OriginSql::getInstance();
            $blk_block = $sql->select('block','difficulty',0,array("height<".$height),'height DESC',9);
            $cumulative=0;
            for ($i=0;$i<8;$i++) {
                if ($blk_block[$i]['difficulty']<$blk_block[$i+1]['difficulty']) {
                    $cumulative=$cumulative+$base_f;
                }else{
                    $cumulative=$cumulative-$base_f;
                }
            }

            $reward = ($base_f*10)+$cumulative;

            if ($reward<=0) {
                $reward=0;
            }
            if ($reward>$reward_base) {
                $reward=$reward_base;
            }
        }

        // calculate the transaction fees
        $fees = 0;
        // if (count($data) > 0) {
        //     foreach ($data as $x) {
        //         $fees += $x['fee'];
        //     }
        // }
        $destroy_reward=$reward_base-$reward;
        $reward=$reward+$fees;

        $mn_reward=number_format(round(0.35*$reward, 8), 8, ".", "");
        $miner_reward=number_format(round($reward-$mn_reward, 8), 8, ".", "");
        $max_reward=$reward_base;
        $destroy_reward=number_format(round($destroy_reward, 8), 8, ".", "");

        // $this->log('block.inc->reward mn_reward:'.$mn_reward.' '.$height,0,true);
        // $this->log('block.inc->reward miner_reward:'.$miner_reward.' '.$height,0,true);
        return array('miner_reward' => $miner_reward, 'mn_reward'=>$mn_reward,'max_reward'=>$max_reward,'destroy_reward'=>$destroy_reward);
    }
    // checks the validity of a block
    //ok
                // $x=>[
                //       'data' => [id,generator,height],
                //       'trx_data'=> [[arr1],[arr2],[arr3]],
                //       'miner_public_key'=>11,
                //       'miner_reward_signature'=>11,
                //       'mn_public_key'=>11,
                //       'mn_reward_signature'=>11,
                //       'from_host'=>''
                //     ],
    public function check($x){
        $data=$x['data'];
        $prv=$this->prev($data['height']);

        //hash
        if ($this->hasha($x['miner_public_key'],$data['height'],$data['date'],$data['nonce'],$x['trx_data'],$data['signature'], $data['difficulty'],$data['argon'])!=$data['id']) {
            $this->log('block.inc->check block hash false',0,true);
            return false;
        }


        // generator's public key must be valid
        //$Account = Accountinc::getInstance();

        //check the argon hash and the nonce to produce a valid block
        if (!$this->mine($x['miner_public_key'], $data['nonce'], $data['argon'], $data['difficulty'], $prv['id'], $prv['height'], $data['date'])) {
            $this->log('block.inc->check mine false',0,true);
            return false;
        }

        //height
        if ($data['height']-$prv['height']!=1) {
            $this->log('block.inc->check block height false',0,true);
            return false;
        }

        //date
        if ($data['date']-$prv['date']<=30) {
            $this->log('block.inc->check block date false',0,true);
            return false;
        }

        //nonce

        //signature
        if ($this->check_signature($data['generator'],$data['height'],$data['date'],$data['nonce'],$x['trx_data'],$x['miner_public_key'],$data['difficulty'], $data['argon'],$data['signature'])==false) {
            $this->log('block.inc->check block signature false',0,true);
            return false;
        }



        //difficulty
        if ($this->valid_difficulty($data['height'],$data['difficulty'])==false) {
            $this->log('block.inc->check block diff false',0,true);
            return false;
        }

        //argon
        if (strlen($data['argon']) < 20) {
            $this->log('block.inc->check block argon false',0,true);
            return false;
        }

        //transactions
        if (count($x['trx_data'])!=$data['transactions']) {
            $this->log('block.inc->check trx count false',0,true);
            return false;
        }

        //reward
        $my_trx_list=[];
        $miner_reward=0;
        $mn_reward=0;
        foreach ($x['trx_data'] as $value) {
            if ($value['version']!=0 and $value['version']!=4 and $value['version']!=111) {
                $my_trx_list[]=$value;
            }
            if ($value['version']==0) {
                $miner_reward=$value['val'];
            }
            if ($value['version']==4) {
                $mn_reward=$value['val'];
            }

        }
        // return array('miner_reward' => $miner_reward, 'mn_reward'=>$mn_reward,'max_reward'=>$max_reward,'destroy_reward'=>$destroy_reward);
        $get_reward=$this->reward($data['height'], $my_trx_list);
        
        if (bccomp($miner_reward, $get_reward['miner_reward'], 8)!=0) {
            $this->log('Block.inc->check miner_reward false',0,true);
            return false;
        }
        if ($mn_reward!==0) {
            if (bccomp($mn_reward, $get_reward['mn_reward'], 8)!=0) {
                $this->log('Block.inc->check mn_reward false',0,true);
                return false;
            }
        }


        $this->log('block.inc->check block finshed true',0,true);
        return true;
    }


    

    // Fork arionum https://github.com/arionum/node
    // check if the arguments are good for mining a specific block
    public function mine($public_key, $nonce, $argon, $difficulty, $block_current_hash, $block_current_height, $time){

        // invalid future blocks
        if ($time>time()+30) {
            $this->log('block.inc->mine time false',0,true);
            return false;
        }
        if ($block_current_height+1 <= 1 or $difficulty <= 0 or $time<=0) {
            $this->log('block.inc->mine height or diff or time false',0,true);
            return false;
        }
        
        if (empty($public_key)) {
            $this->log('block.inc->mine Empty public key false',0,true);
            return false;
        }

        //
        $this->log("Mining - ".($block_current_height+1), 0,true);
        $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;

        // the hash base for agon
        $base = "$public_key-$nonce-".$block_current_hash."-$difficulty";


        // check argon's hash validity
        if (!password_verify($base, $argon)) {
            $this->log('block.inc->mine password_verify false'.$base.'|'.$argon,0,true);
            return false;

        }

        // prepare the base for the hashing
        $hash = $base.$argon;

        // hash the base
        $hash = hash("sha512", $hash, true);
        $hash = hash("sha512", $hash);

        // split it in 2 char substrings, to be used as hex
        $m = str_split($hash, 2);

        // calculate a number based on 8 hex numbers - no specific reason, we just needed an algoritm to generate the number from the hash
        $duration = hexdec($m[10]).hexdec($m[15]).hexdec($m[20]).hexdec($m[23]).hexdec($m[31]).hexdec($m[40]).hexdec($m[45]).hexdec($m[55]);

        // the number must not start with 0
        $duration = ltrim($duration, '0');

        // divide the number by the difficulty and create the deadline
        $result = gmp_div_q($duration, $difficulty);

        // if the deadline >0 and <=50, the arguments are valid fora  block win
        if ($result > 0 && $result <= 50) {
            $this->log('block.inc->mine Mine block Success true',0,true);
            return true;
        }
        return false;
    }




    // initialize the blockchain, add the genesis block
    private function genesis(){
        $arrayName = array(
        'id'=>'5mEVo2RLoEZt4ZnL5qKaA5LiwLrPDS7xCngE9RyMTBMXBStBHfHKndSXj6b8gVmuKhR31oKV6gPfJxonS4Ghu2JD',
        'generator' =>'2j7bgeD9vZDsgG5z9vJEZ3qRFqaVCb3MizMgS3RF7b7Ynw2v3ZvUGRkm2LiAQZGptYJ1NNrcrn9TGzCwjM1kiHyi',
        'height'=> 1,
        'date'=> 1545988704,
        'nonce' =>'PhtMzC0qlOwHwDQh6I70X6w8a3ZYeNKIqDSnBqViW7M',
        'signature'=> '381yXZVxeQTVfrtugDTnhLKMvjcK5DaBeeaADoYMTQFjSnY6ED8eCyGfY6kG99CeCTEztZRB7xFuZcpMUqhW8xGSUGcAwj4f2',
        'difficulty'=> '9999999',
        'argon' =>'$QmxCckRCUUNFTVpibUVUUQ$WG2IidvaLRGORyZCj1sF/oIlLh8PrDhnz9krW55rl5I2',
        'transactions'=> 0,
        );

        $sql=OriginSql::getInstance();

        $res = $sql->add('block',array(
                                    'id'=>$arrayName['id'],
                                    'generator'=>$arrayName['generator'],
                                    'height'=>$arrayName['height'],
                                    'date'=>$arrayName['date'],
                                    'nonce'=>$arrayName['nonce'],
                                    'signature'=>$arrayName['signature'],
                                    'difficulty'=>$arrayName['difficulty'],
                                    'argon'=>$arrayName['argon'],
                                    'transactions'=>$arrayName['transactions']
        ));

        if (!$res) {
            return false;
        }else{
            return $arrayName;
        }
        
    }

    // delete last X blocks
    public function pop($no = 1){
        if ($no==='') {
            $no = 1;
        }
        $current = $this->current();
        if ($current['height']<2) {
            return false;
        }
        $start_height=$current['height']-$no;
        if ($start_height<2) {
            $start_height=1;
        }
        $this->log('block.inc->pop '.$no.' true',0,true);
        return $this->delete($start_height);
    }

    // delete all blocks >= height
    public function delete($height){
        if ($height < 2) {
            $height = 2;
        }

        $sql=OriginSql::getInstance();
        $r = $sql->select('block','*',0,array("height>".$height),'height DESC',0);
        if (count($r) == 0) {
            return true;
        }



        $Transaction = Transactioninc::getInstance();
        
        $sql->lock_tables();
        $sql->beginTransaction();
        foreach ($r as $x) {
            $res = $Transaction->delete_transactions_to_mempool_from_block($x['id']);
            if ($res === false) {
                $this->log('block.inc->delete A transaction could not be reversed. Delete block failed. false',0,true);
                $sql->rollback();
                $sql->unlock_tables();
                return false;
            }

            $res=$sql->delete('block',array("id='".$x['id']."'"));
            if ($res != 1) {
                $this->log('block.inc->delete Delete block failed false',0,true);
                $sql->rollback();
                $sql->unlock_tables();
                return false;
            }
        }

        $sql->commit();
        $sql->unlock_tables();
        $this->log('block.inc->delete del blocks'.$height.' true',0,true);
        return true;
    }


    // delete specific block
    public function delete_block_hash($id){

        $sql=OriginSql::getInstance();
        $r = $sql->select('block','*',1,array("id='".$id."'"),'',1);
        if (!$r) {  return true;  }

        $Transaction = Transactioninc::getInstance();
        
        $sql->lock_tables();
        $sql->beginTransaction();

        $res = $Transaction->delete_transactions_to_mempool_from_block($r['id']);
        if ($res === false) {
            $sql->rollback();
            $sql->unlock_tables();
            $this->log('block.inc->delete_block_hash '."A transaction could not be reversed. Delete block ".$id." false",0,true);
            return false;
        }

        $res=$sql->delete('block',array("id='".$r['id']."'"));
        if ($res != 1) {
            $sql->rollback();
            $sql->unlock_tables();
            $this->log('block.inc->delete_block_hash '."Delete block ".$id." false",0,true);
            return false;
        }


        $sql->commit();
        $sql->unlock_tables();
        $this->log('block.inc->delete_block_hash '."del blocks ".$id." true",0,true);
        return true;
    }


    // sign a new block, used when mining
    public function signature($generator,$height,$date,$nonce,$data=array(),$difficulty, $argon,$private_key){
        $trx_hash=[];
        foreach ($data as $value) {
            $trx_hash[]=$value['id'];
        }
        sort($trx_hash);
        $trx_hash = json_encode($trx_hash);
        return ec_sign("{$generator}-{$height}-{$date}-{$nonce}-{$trx_hash}-{$difficulty}-{$argon}", $private_key);
    }
    // checks the ecdsa secp256k1 signature for a specific public key
    public function check_signature($generator,$height,$date,$nonce,$data=array(),$public_key,$difficulty, $argon,$signature){
        $trx_hash=[];
        foreach ($data as $value) {
            $trx_hash[]=$value['id'];
        }
        sort($trx_hash);
        $trx_hash = json_encode($trx_hash);
        return ec_verify("{$generator}-{$height}-{$date}-{$nonce}-{$trx_hash}-{$difficulty}-{$argon}", $signature, $public_key);
    }


    // generate the sha512 hash of the block data and converts it to base58
    public function hasha($public_key, $height, $date, $nonce, $data=array(), $signature, $difficulty, $argon){
        $trx_hash=[];
        foreach ($data as $value) {
            $trx_hash[]=$value['id'];
        }
        sort($trx_hash);
        $trx_hash = json_encode($trx_hash);

        $hash = hash("sha512", "{$public_key}-{$height}-{$date}-{$nonce}-{$trx_hash}-{$signature}-{$difficulty}-{$argon}");
        return hex2coin($hash);
    }


    // exports the block data, to be used when submitting to other peers
    /* array(
         'status'=>'ok'
         'data' =>[id,generator,height],
         'trx_data'=> [[arr1],[arr2],[arr3]],
         'miner_public_key'=>11,
         'miner_reward_signature'=>11,
         'mn_public_key'=>11,
         'mn_reward_signature'=>11,
         'from_host'=>''

     );*/
    public function export_for_other_peers($id = "", $height = ""){
        if (empty($id) && empty($height)) {
            $this->log('block.inc->export_for_other_peers export block height and id is empty [false]',0,true);
            return false;
        }


        $sql=OriginSql::getInstance();
        if (!empty($height)) {
            $block = $sql->select('block','*',1,array("height=".$height),'',1);
        } else {
            $block = $sql->select('block','*',1,array("id='".$id."'"),'',1);
        }

        if (!$block) {
            $this->log('block.inc->export_for_other_peers export block [false]',0,true);
            return false;
        }

        $res=[];
        $res['status']='ok';
        $res['data']=$block;


        $r = $sql->select('trx','*',0,array("block='".$res['data']['id']."'"),'',0);

        $res['trx_data'] = $r;


        $gen = $sql->select('trx','*',1,array("block='".$res['data']['id']."'","version=0"),'',1);
        $res['miner_public_key'] = $gen['public_key'];
        $res['miner_reward_signature'] = $gen['signature'];


        $gen = $sql->select('trx','*',1,array("block='".$res['data']['id']."'","version=4"),'',1);
        $res['mn_public_key'] = $gen['public_key'];
        $res['mn_reward_signature'] = $gen['signature'];

        //$res['coin']='origin';
        if ($this->config['local_node']==false) {
            $res['from_host']=$this->config['hostname'];
        }else{
            $res['from_host']='';
        }
        $this->log('block.inc->export_for_other_peers export block [true]',0,true);
        return $res;
    }

    //return a specific block as array
    public function get_block_from_height($height){

        if ($height<1) {
            return false;
        }
        $sql=OriginSql::getInstance();

        $block = $sql->select('block','*',1,array("height=".$height),'',1);
        if ($block) {
            return $block;
        }else{
            return false;
        }
        
    }
    public function get_block_from_id($id){
        if ($id=='') {
            return false;
        }
        $sql=OriginSql::getInstance();

        $res=$sql->select('block','*',1,array("id='".$id."'"),'',1);
        if ($res) {
            return $res;
        }else{
            return false;
        }
    }


}
