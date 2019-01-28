<?php
// version: 20190128 test
class postthreads extends Thread{
	public $res='';
	public $url='';
	public $json_post_data='';
	public function __construct($url,$json_post_data=''){
		if (php_sapi_name() != 'cli') {
			echo "\nneed to run cli modle";	exit;
		}
		$this->url=$url;
		$this->json_post_data=$json_post_data;
	}

	public function run() {
        $this->res=postT_peer_post($this->url, $this->json_post_data, 5);	
	}
}
function postT_peer_post($url, $json_post_data, $timeout = 60){
        if ($timeout=='') {
            $timeout = 60;
        }
        $postdata = http_build_query(
            [
                'data' => $json_post_data,
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
        $res = json_decode($result, true);

        // the function will return false if something goes wrong
        if ($res['status'] == "ok" || $res['coin'] == 'origin') {
            return $res['data'];
        }else{
            return false;
        }  
}


?>