<?php
// version: 20190128 test
// converts PEM key to hex
function pem2hex($data)
{
    $data = str_replace("-----BEGIN PUBLIC KEY-----", "", $data);
    $data = str_replace("-----END PUBLIC KEY-----", "", $data);
    $data = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $data);
    $data = str_replace("-----END EC PRIVATE KEY-----", "", $data);
    $data = str_replace("\n", "", $data);
    $data = base64_decode($data);
    $data = bin2hex($data);
    return $data;
}
// converts hex key to PEM
function hex2pem($data, $is_private_key = false)
{
    if ($is_private_key==='') {
        $is_private_key=false;
    }
    $data = hex2bin($data);
    $data = base64_encode($data);
    if ($is_private_key) {
        return "-----BEGIN EC PRIVATE KEY-----\n".$data."\n-----END EC PRIVATE KEY-----";
    }
    return "-----BEGIN PUBLIC KEY-----\n".$data."\n-----END PUBLIC KEY-----";
}
// converts PEM key to the base58 version used by ARO
function pem2coin($data)
{
    $data = str_replace("-----BEGIN PUBLIC KEY-----", "", $data);
    $data = str_replace("-----END PUBLIC KEY-----", "", $data);
    $data = str_replace("-----BEGIN EC PRIVATE KEY-----", "", $data);
    $data = str_replace("-----END EC PRIVATE KEY-----", "", $data);
    $data = str_replace("\n", "", $data);
    $data = base64_decode($data);


    return base58_encode($data);
}
// converts the key in base58 to PEM
function coin2pem($data, $is_private_key = false)
{
    if ($is_private_key==='') {
        $is_private_key=false;
    }
    $data = base58_decode($data);
    $data = base64_encode($data);

    $dat = str_split($data, 64);
    $data = implode("\n", $dat);

    if ($is_private_key) {
        return "-----BEGIN EC PRIVATE KEY-----\n".$data."\n-----END EC PRIVATE KEY-----\n";
    }
    return "-----BEGIN PUBLIC KEY-----\n".$data."\n-----END PUBLIC KEY-----\n";
}


// convers hex to base58
function hex2coin($hex){
    $data = hex2bin($hex);
    return base58_encode($data);
}

// converts base58 to hex
function coin2hex($data){
    $bin = base58_decode($data);
    return bin2hex($bin);
}

// sign data with private key
function ec_sign($data, $key){
    // transform the base58 key format to PEM
    $private_key = coin2pem($key, true);

    $pkey = openssl_pkey_get_private($private_key);

    $k = openssl_pkey_get_details($pkey);
    openssl_sign($data, $signature, $pkey, OPENSSL_ALGO_SHA256);

    // the signature will be base58 encoded
    return base58_encode($signature);
}
function ec_verify($data, $signature, $key){
    // transform the base58 key to PEM
    $public_key = coin2pem($key);

    $signature = base58_decode($signature);

    $pkey = openssl_pkey_get_public($public_key);

    $res = openssl_verify($data, $signature, $pkey, OPENSSL_ALGO_SHA256);


    if ($res === 1) {
        return true;
    }
    return false;
}



// Base58 encoding/decoding functions - all credits go to https://github.com/stephen-hill/base58php
function base58_encode($string){
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);
    // Type validation
    if (is_string($string) === false) {
        return false;
    }
    // If the string is empty, then the encoded string is obviously empty
    if (strlen($string) === 0) {
        return '';
    }
    // Now we need to convert the byte array into an arbitrary-precision decimal
    // We basically do this by performing a base256 to base10 conversion
    $hex = unpack('H*', $string);
    $hex = reset($hex);
    $decimal = gmp_init($hex, 16);
    // This loop now performs base 10 to base 58 conversion
    // The remainder or modulo on each loop becomes a base 58 character
    $output = '';
    while (gmp_cmp($decimal, $base) >= 0) {
        list($decimal, $mod) = gmp_div_qr($decimal, $base);
        $output .= $alphabet[gmp_intval($mod)];
    }
    // If there's still a remainder, append it
    if (gmp_cmp($decimal, 0) > 0) {
        $output .= $alphabet[gmp_intval($decimal)];
    }
    // Now we need to reverse the encoded data
    $output = strrev($output);
    // Now we need to add leading zeros
    $bytes = str_split($string);
    foreach ($bytes as $byte) {
        if ($byte === "\x00") {
            $output = $alphabet[0].$output;
            continue;
        }
        break;
    }
    return (string)$output;
}
function base58_decode($base58){
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);

    // Type Validation
    if (is_string($base58) === false) {
        return false;
    }
    // If the string is empty, then the decoded string is obviously empty
    if (strlen($base58) === 0) {
        return '';
    }
    $indexes = array_flip(str_split($alphabet));
    $chars = str_split($base58);
    // Check for invalid characters in the supplied base58 string
    foreach ($chars as $char) {
        if (isset($indexes[$char]) === false) {
            return false;
        }
    }
    // Convert from base58 to base10
    $decimal = gmp_init($indexes[$chars[0]], 10);
    for ($i = 1, $l = count($chars); $i < $l; $i++) {
        $decimal = gmp_mul($decimal, $base);
        $decimal = gmp_add($decimal, $indexes[$chars[$i]]);
    }
    // Convert from base10 to base256 (8-bit byte array)
    $output = '';
    while (gmp_cmp($decimal, 0) > 0) {
        list($decimal, $byte) = gmp_div_qr($decimal, 256);
        $output = pack('C', gmp_intval($byte)).$output;
    }
    // Now we need to add leading zeros
    foreach ($chars as $char) {
        if ($indexes[$char] === 0) {
            $output = "\x00".$output;
            continue;
        }
        break;
    }
    return $output;
}
?>