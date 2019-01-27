<?php
// version: 20190101 test
class Security{
	private static $_instance = null;
	function __construct(){
	}
    public static function getInstance(){
        if(self::$_instance === null)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
	public function cmd($command='php sanity.php',$parameter=[]){
		$parameter_str='';
		foreach ($parameter as $value) {
			$value=escapeshellarg(trim($value));
			$parameter_str=$parameter_str.' '.$value;
		}
		$command=escapeshellcmd($command.' '.$parameter_str);
		return $command."  > /dev/null 2>&1  &";
	}

	public function field($type,$value){
		switch ($type) {
			case 'san':
				$value=$this->san($value);
				break;
			case 'balance':
				//$value=$this->floatval($value);
				$value=number_format($value,8);
				break;
			case 'alias':
				$value=$this->san($value);
				$value=strtolower($value);
				break;
			case 'num':
				$value=$this->san_num($value);
				break;
			case 'hostname':
				$value=$this->san_host($value);
				while (substr($value,-1)=='/') {
					$value=substr($value,0,-1);
				}
				break;
			default:
				# code...
				break;
		}
		return $value;
	}

	public function san($a, $b = ""){
	    $a = preg_replace("/[^a-zA-Z0-9".$b."]/", "", $a);
	    return $a;
	}
	public function san_ip($a){
	    $a = preg_replace("/[^a-fA-F0-9\[\]\.\:]/", "", $a);
	    return $a;
	}
	public function san_host($a){
	    $a = preg_replace("/[^a-zA-Z0-9\.\-\:\/]/", "", $a);
	    return $a;
	}
	public function san_num_ip($a){
	    $a = preg_replace("/[^0-9\.]/", "", $a);
	    return $a;
	}
	public function san_num($a){
	    $a = preg_replace("/[^0-9]/", "", $a);
	    return $a;
	}
}

?>