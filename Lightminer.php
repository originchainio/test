<?php
class Lightminer{
	public const Ver='1.0';
	public $node;
	public $public_key;
	public $private_key;
	public $youraddress;
	public $yourwork;
	function __construct(){
		$this->check();
	}

	public function solo(){
		$this->run('solo');

	}
	public function pool(){
		$this->run('pool');
	}
    public function update($mode){
        echo "--> Updating mining info\n";
        $extra = "";
        if ($mode == 'pool') {
            $extra = "&worker=".$this->yourwork."&address=".$this->youraddress;
            $res = file_get_contents($this->node."/Uinterface.php?m=getminingwork".$extra);
        }else{
        	$res = file_get_contents($this->node."/Uinterface.php?m=getminingwork");
        }
       echo 'Update:'.$this->node."/Uinterface.php?m=getminingwork\n";
        $info = json_decode($res, true);
        if (!isset($info['result']) or $info['error']!='') {
        	//echo_array($info);	exit;
        	return false;
        }

        $data = $info['result'];

        $re = array(
        	'block' => $data['block'],
        	'difficulty' => $data['difficulty'],
        	'limit' => 50,
        	'height'=>$data['height'],
        	'reward'=>$data['reward'],
		);
        if ($mode == 'pool') {
        	$re['limit']=$data['limit'];
        	$this->public_key=$data['public_key'];
        }

        return $re;
    }
	public function run($mode){
		$this->output();
		$allTime = microtime(true);
        $beginTime = time();
        $it = 0;
        $confirm=0;
        $counter = 0;
        $lastUpdate=0;
        $found=0;
        $speed=0;
        $avgSpeed=0;
        $start = microtime(true);
        $submit=0;
        $mininginfo=[];
        while (1) {
            $counter++;
            if (time() - $lastUpdate > 2) {
                echo "--> Hash: ".number_format($speed,2)." H/s   ".
                    "Average: ".number_format($avgSpeed,2)." H/s  ".
                    "Total hashes: ".$counter."  ".
                    "Mining Time: ".(time() - $beginTime)."  ".
                    "Shares: ".$confirm." ".
                    "Finds: ".$found."\n";
                    $res=$this->update($mode);
                    
                if ($res) {
                	$mininginfo=$res;
                }
                $lastUpdate=time();
            }

            $nonce = base64_encode(openssl_random_pseudo_bytes(32));
            $nonce = preg_replace("/[^a-zA-Z0-9]/", "", $nonce);

            $base = $this->public_key."-".$nonce."-".$mininginfo['block']."-".$mininginfo['difficulty'];
            $argon = password_hash(
                    $base,
                    PASSWORD_ARGON2I,
                    ['memory_cost' => 16384, "time_cost" => 4, "threads" => 4]
                );
            $hash = $base.$argon;
            $hash = hash("sha512", $hash, true);
            $hash = hash("sha512", $hash);
            $m = str_split($hash, 2);
            $duration = hexdec($m[10]).hexdec($m[15]).hexdec($m[20]).hexdec($m[23]).
                hexdec($m[31]).hexdec($m[40]).hexdec($m[45]).hexdec($m[55]);
            $duration = ltrim($duration, '0');
            $result = gmp_div($duration, $mininginfo['difficulty']);
            if ($result > 0 && $result <= $mininginfo['limit']) {
                if (!password_verify($base, $argon)) {
                   echo "verify failed"."\n";
                }else{
                    echo "verify success"."\n";
                }
                $argon=substr($argon, 29);
                $confirmed = $this->submit($mode,$nonce, $argon);
                //echo "\nARGON: $argon\n";
                //echo "\nBase: $base\n";
                if ($confirmed && $result <= 50) {
                    $found++;
                } elseif ($confirmed) {
                    $confirm++;
                }
                $submit++;
                //sleep(3);  //test
            }
            $it++;
            if ($it == 10) {
                $it = 0;
                $end = microtime(true);
                $speed = 10 / ($end - $start);
                $avgSpeed = $counter / ($end - $allTime);
                $start = $end;
            }
        }
	}
    private function submit($mode,$nonce,$argon){
        echo "--> Submitting nonce $nonce / $argon\n";
        if ($mode=='solo') {
	        $postData = http_build_query(
	            [
	                'argon'       => $argon,
	                'nonce'       => $nonce,
	                'private_key' => $this->private_key,
	                'public_key'  => $this->public_key,
	            ]
	        );
        }else{
	        $postData = http_build_query(
	            [
	                'argon'       => $argon,
	                'nonce'       => $nonce,
	                'public_key'  => $this->public_key,
	                'address'     => $this->youraddress,
	                'yourwork'     => $this->yourwork,
	            ]
	        );
        }

        $opts = [
            'http' =>
                [
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $postData,
                ],
        ];
        $context = stream_context_create($opts);
        $res = file_get_contents($this->node."/Uinterface.php?m=submitNonce", false, $context);
        if ($res==false) {
            echo "--> Time out.\n\n";
            return false;
        }
        $data = json_decode($res, true);
        if ($data['result'] == 'ok') {
            echo "\n--> Nonce confirmed.\n";
            return true;
        } else {
            echo "--> The nonce did not confirm.\n\n";
            return false;
        }
    }
    private function output(){
        echo "============================\n";
        echo "== Orc Miner ".self::Ver."  ==\n";
        echo "== www.originchain.io    ==\n";
        echo "============================\n\n";
    }
    public function help(){
            echo "Usage:\n\n";
            echo "Solo mining: ./Lightminer.php solo <node> <public_key> <private_key>\n";
            echo "Pool mining: ./Lightminer.php pool <pool> <your-address> <your-work>\n\n";
    }
    private function check(){
        if (!extension_loaded("gmp")) {
            die("The GMP PHP extension is missing.");
        }
        if (!extension_loaded("openssl")) {
            die("The OpenSSL PHP extension is missing.");
        }
        if (floatval(phpversion()) < 7.2) {
            die("The minimum PHP version required is 7.2.");
        }
        if (!defined("PASSWORD_ARGON2I")) {
            die("The PHP version is not compiled with argon2i support.");
        }
    }
    /**
     * @param array $source
     * @param mixed $source_base
     * @param mixed $target_base
     * @return array
     *
     * @author Mika Tuupola
     * @link   https://github.com/tuupola/base58
     */
    private function baseConvert(array $source, $source_base, $target_base){
        $result = [];
        while ($count = count($source)) {
            $quotient = [];
            $remainder = 0;
            for ($i = 0; $i != $count; $i++) {
                $accumulator = $source[$i] + $remainder * $source_base;
                $digit = (integer)($accumulator / $target_base);
                $remainder = $accumulator % $target_base;
                if (count($quotient) || $digit) {
                    array_push($quotient, $digit);
                };
            }
            array_unshift($result, $remainder);
            $source = $quotient;
        }
        return $result;
    }
    /**
     * @param mixed $data
     * @param bool  $integer
     * @return int|string
     *
     * @author Mika Tuupola
     * @link   https://github.com/tuupola/base58
     */
    private function base58Decode($data, $integer = false){
        $data = str_split($data);
        $data = array_map(function ($character) {
            $chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
            return strpos($chars, $character);
        }, $data);
        /* Return as integer when requested. */
        if ($integer) {
            $converted = $this->baseConvert($data, 58, 10);
            return (integer)implode("", $converted);
        }
        $converted = $this->baseConvert($data, 58, 256);
        return implode("", array_map(function ($ascii) {
            return chr($ascii);
        }, $converted));
    }
}
date_default_timezone_set('PRC');
$Lightminer=new Lightminer();
if (!isset($argv[1])) {
	$Lightminer->help();
	exit;
}
if ($type=='help') {
	$Lightminer->help();
	exit;
}
$type = trim($argv[1]);
switch ($type) {
	case 'solo':
		if (!isset($argv[2]) or !isset($argv[3]) or !isset($argv[4])) {
			$Lightminer->help();
			exit;
		}
		$Lightminer->node = trim($argv[2]);
		$Lightminer->public_key = trim($argv[3]);
		$Lightminer->private_key = trim($argv[4]);
		break;
	case 'pool':
		if (!isset($argv[2]) or !isset($argv[3]) or !isset($argv[4])) {
			$Lightminer->help();
			exit;
		}
		$Lightminer->node = trim($argv[2]);
		$Lightminer->youraddress = trim($argv[3]);
		$Lightminer->yourwork = trim($argv[4]);
		break;
	default:
		$Lightminer->help();
		break;
}


if ($type=='solo') {
	$Lightminer->solo();
}else{
	$Lightminer->pool();
}
function echo_array($a) { echo "<pre>"; print_r($a); echo "</pre>"; }

?>