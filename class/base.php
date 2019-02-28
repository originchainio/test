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

// version: 20190225 test
class base
{
    public $config = array();
    public $info = array();
    function __construct()
    {
        $this->config = include __DIR__ . '../../config/config.php';
        if ($this->config['init']==false) {
            echo 'init is false';
            exit;
        }
        $this->info['cli'] = $this->is_cli();
        //$this->info['hostname']=$this->get_hostname();
        $this->info['version'] = 'Version 1.0 Build 20190227';
        $this->CheckBase();
    }
    private function __clone()
    {
    }
    private function CheckBase(){
        //init
        if ($this->config['init'] == false) {
            echo 'under-maintenance';
            exit;
        }
        // if ($this->info['cli'] != true) {
        //     echo "\nneed to run cli modle";
        //     exit;
        // }
        if (!extension_loaded("openssl") && !defined("OPENSSL_KEYTYPE_EC")) {
            echo "\nOpenssl php extension missing";
            exit;
        }
        if (!extension_loaded("gmp")) {
            echo "\ngmp php extension missing";
            exit;
        }
        if (!extension_loaded('PDO')) {
            echo "\npdo php extension missing";
            exit;
        }
        if (!extension_loaded("bcmath")) {
            echo "\nbcmath php extension missing";
            exit;
        }
        if (!defined("PASSWORD_ARGON2I")) {
            echo "\nThe php version is not compiled with argon2i support";
            exit;
        }
        if (floatval(phpversion()) < 7.2) {
            echo "\nThe minimum php version required is 7.2";
            exit;
        }
        // if (!extension_loaded("pthreads") == false) {
        //     echo "pthreads php extension missing";
        //     exit;
        // }
    }
    public final function echo_display_json($status = true, $data){
        if ($status==='') {
            $status=true;
        }
        if (headers_sent() == false) {
            header('Content-Type: application/json');
        }
        if ($status == true) {
            echo json_encode(["status" => "ok", "data" => $data, "coin" => $this->config['coin_name']]);
        } else {
            echo json_encode(["status" => "error", "data" => $data, "coin" => $this->config['coin_name']]);
        }
    }

    private final function is_cli(){
        if (php_sapi_name() == 'cli' or php_sapi_name() == 'cli_server') {
            return true;
        } else {
            return false;
        }
    }
    private final function get_hostname(){
        $hostname = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . san_host($_SERVER['HTTP_HOST']);
        return $hostname;
    }
    // The log code comes from arionum https://github.com/arionum/node
    public function log($data,$verbosity = 0,$result=true){
        if ($verbosity==='') {
            $verbosity = 0;
        }
        if ($this->config['log_verbosity'] > $verbosity) {
            return;
        }
        if ($result==false) {
            $data='error: '.$data;
        }
        $date = date("[Y-m-d H:i:s]");

        $trace = debug_backtrace();
        $loc = count($trace) - 1;
        $file = substr($trace[$loc]['file'], strrpos($trace[$loc]['file'], "/") + 1);
        $res = "{$date} " . $file . ":" . $trace[$loc]['line'];
        if (!empty($trace[$loc]['class'])) {
            $res .= "-" . $trace[$loc]['class'];
        }
        if (!empty($trace[$loc]['function']) && $trace[$loc]['function'] != '_log') {
            $res .= '->' . $trace[$loc]['function'] . '()';
        }
        $res .= " {$data} \n";
        //echo $res;
        @file_put_contents(__DIR__ . '../../log/'.$this->config['log_file'], $res, FILE_APPEND);
        if ($result==false) {
            @file_put_contents(__DIR__ . '../../log/'.$this->config['log_file_error'], $res, FILE_APPEND);
        }

    }
    public function return_json($result,$error=''){
        return json_encode(array(
            'result' => $result,
            'error'=>$error,
            ));
    }
}