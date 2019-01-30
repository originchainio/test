<?php
// version: 20190128 test
function echo_array($a) { echo "<pre>"; print_r($a); echo "</pre>"; }
//preg
function san($a, $b = ""){
    $a = preg_replace("/[^a-zA-Z0-9".$b."]/", "", $a);
    return $a;
}
function san_ip($a){
    $a = preg_replace("/[^a-fA-F0-9\[\]\.\:]/", "", $a);
    return $a;
}
function san_host($a){
    $a = preg_replace("/[^a-zA-Z0-9\.\-\:\/]/", "", $a);
    return $a;
}
function san_num_ip($a){
    $a = preg_replace("/[^0-9\.]/", "", $a);
    return $a;
}
// 检查地址的有效性。目前，长度为>=70和<=128
function valid_len($str,$minlen=70,$maxlen=128){
    if ($minlen==='') {
        $minlen=70;
    }
    if ($maxlen==='') {
        $maxlen=128;
    }
    if (strlen($str) < $minlen || strlen($str) > $maxlen) {
        return false;
    }else{
        return true;
    }
}
// check the validity of a base58 encoded key. At the moment, it checks only the characters to be base58.
// 检查base58编码密钥的有效性。目前，它只检查字符是否为base58
function valid_base58($str){
    $chars = str_split("123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz");
    for ($i = 0; $i < strlen($str);
         $i++) {
        if (!in_array($str[$i], $chars)) {
            return false;
        }
    }
    return true;
}



?>