<?php

// version: 20190128 test
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
include __DIR__.'/lib/OriginSql.lib.php';
// include __DIR__.'/lib/PostThreads.lib.php';
include __DIR__.'/lib/Security.lib.php';
include __DIR__.'/function/function.php';
include __DIR__.'/function/core.php';
/**
 * @api {get} /api.php 01. Basic Information
 * @apiName Info
 * @apiGroup API
 * @apiDescription Each API call will return the result in JSON format.
 * There are 2 objects, "status" and "data".
 *
 * The "status" object returns "ok" when the transaction is successful and "error" on failure.
 *
 * The "data" object returns the requested data, as sub-objects.
 *
 * The parameters must be sent either as POST['data'], json encoded array or independently as GET.
 *
 * @apiSuccess {String} status "ok"
 * @apiSuccess {String} data The data provided by the api will be under this object.
 *
 *
 *
 * @apiSuccessExample {json} Success-Response:
 *{
 *   "status":"ok",
 *   "data":{
 *      "obj1":"val1",
 *      "obj2":"val2",
 *      "obj3":{
 *         "obj4":"val4",
 *         "obj5":"val5"
 *      }
 *   }
 *}
 *
 * @apiError {String} status "error"
 * @apiError {String} result Information regarding the error
 *
 * @apiErrorExample {json} Error-Response:
 *     {
 *       "status": "error",
 *       "data": "The requested action could not be completed."
 *     }
 */

$ip = san_ip($_SERVER['REMOTE_ADDR']);
$ip = filter_var($ip, FILTER_VALIDATE_IP);
$config = include __DIR__ . '/config/config.php';
if ($config['public_api'] == false && !in_array($ip, $config['allowed_hosts'])) {
    echo_display_json(false,"private-api");
}


$q = $_GET['q'];
if (!empty($_POST['data'])) {
    $data = json_decode($_POST['data'], true);
} else {
    $data = $_GET;
}



if ($q == "getAddress") {
    /**
     * @api {get} /api.php?q=getAddress  02. getAddress
     * @apiName getAddress
     * @apiGroup API
     * @apiDescription Converts the public key to an ORIGIN address.
     *
     * @apiParam {string} public_key The public key
     *
     * @apiSuccess {string} data Contains the address
     */
    $public_key = $data['public_key'];
    if (strlen($public_key) < 32) {
        echo_display_json(false,"Invalid public key");
    }
    $acc=Accountinc::getInstance();
    $address=$acc->get_address_from_public_key($public_key);
    if ($address==false) {
        echo_display_json(false,"Invalid public key");
    }else{
        echo_display_json(true,$address);
    }
    
} elseif ($q == "base58") {
    /**
     * @api {get} /api.php?q=base58  03. base58
     * @apiName base58
     * @apiGroup API
     * @apiDescription Converts a string to base58.
     *
     * @apiParam {string} data Input string
     *
     * @apiSuccess {string} data Output string
     */
    $str=base58_encode($data['data']);
    if ($str==false) {
        echo_display_json(false,"base58 fail");
    }else{
        echo_display_json(true,$str);
    }
} elseif ($q == "getBalance") {
    /**
     * @api {get} /api.php?q=getBalance  04. getBalance
     * @apiName getBalance
     * @apiGroup API
     * @apiDescription Returns the balance of a specific account or public key.
     *
     * @apiParam {string} [public_key] Public key
     * @apiParam {string} [account] Account id / address
     * @apiParam {string} [alias] alias
     *
     * @apiSuccess {string} data The ORIGIN balance
     */

    $public_key = san($data['public_key']);
    $account = san($data['account']);
    $alias = san($data['alias']);

    $sql=OriginSql::getInstance();
    
    if (!empty($public_key)) {
        $res=$sql->select('acc','balance',1,array("public_key='".$public_key."'"),'',1);
    }elseif (!empty($account)) {
        $res=$sql->select('acc','balance',1,array("id='".$account."'"),'',1);
    }elseif (!empty($alias)) {
        $res=$sql->select('acc','balance',1,array("alias='".$alias."'"),'',1);
    }else{
        $res=false;
    }
    if ($res) {
        $balance=number_format($res['balance'],8);
        echo_display_json(true,$balance);
    }else{
        echo_display_json(false,'get balance fail or balance is 0');
    }
} elseif ($q == "getPendingBalance") {
    /**
     * @api {get} /api.php?q=getPendingBalance  05. getPendingBalance
     * @apiName getPendingBalance
     * @apiGroup API
     * @apiDescription Returns the pending balance, which includes pending transactions, of a specific account or public key.
     *
     * @apiParam {string} [public_key] Public key
     * @apiParam {string} [account] Account id / address
     * @apiParam {string} [alias] alias
     *
     * @apiSuccess {string} data The ORIGIN balance
     */
    $public_key = san($data['public_key']);
    $account = san($data['account']);
    $alias = san($data['alias']);

    $sql=OriginSql::getInstance();

    if (!empty($public_key)) {
        $ba=$sql->sum('mem',['val','fee'],"public_key='".$public_key."'");
    }elseif (!empty($account)) {
        $res=$sql->select('acc','public_key',1,array("id='".$account."'"),'',1);
        if ($res) {
            $ba=$sql->sum('mem',['val','fee'],"public_key='".$res['public_key']."'");
        }else{
            $ba=0;
        }

    }elseif (!empty($alias)) {
        $res=$sql->select('acc','public_key',1,array("alias='".$alias."'"),'',1);
        if ($res) {
            $ba=$sql->sum('mem',['val','fee'],"public_key='".$res['public_key']."'");
        }else{
            $ba=0;
        }
    }else{
        $ba=0;
    }
    $balance=number_format($ba,8);
    echo_display_json(true,$balance);

} elseif ($q == "getTransactions") {
    /**
     * @api {get} /api.php?q=getTransactions  06. getTransactions
     * @apiName getTransactions
     * @apiGroup API
     * @apiDescription Returns the latest transactions of an account.
     *
     * @apiParam {string} [public_key] Public key
     * @apiParam {string} [account] Account id / address
     * @apiParam {string} [alias] alias
     * @apiParam {numeric} [limit] Number of confirmed transactions, max 1000, min 1
     *
     * @apiSuccess {string} block  Block ID
     * @apiSuccess {numeric} confirmation Number of confirmations
     * @apiSuccess {numeric} date  Transaction's date in UNIX TIMESTAMP format
     * @apiSuccess {string} dst  Transaction destination
     * @apiSuccess {numeric} fee  The transaction's fee
     * @apiSuccess {numeric} height  Block height
     * @apiSuccess {string} id  Transaction ID/HASH
     * @apiSuccess {string} message  Transaction's message
     * @apiSuccess {string} signature  Transaction's signature
     * @apiSuccess {string} public_key  Account's public_key
     * @apiSuccess {string} src  Sender's address
     * @apiSuccess {string} type  "debit", "credit" or "mempool"
     * @apiSuccess {numeric} val Transaction value
     * @apiSuccess {numeric} version Transaction version
     */

    $account = san($data['account']);
    $public_key = san($data['public_key']);
    $alias = san($data['alias']);
    $limit = intval($data['limit']);

    $sql=OriginSql::getInstance();

    if (!empty($public_key)) {
        
    }elseif (!empty($account)) {
        $res=$sql->select('acc','public_key',1,array("id='".$account."'"),'',1);
        if ($res) {
            $public_key=$res['public_key'];
        }

    }elseif (!empty($alias)) {
        $res=$sql->select('acc','public_key',1,array("alias='".$alias."'"),'',1);
        if ($res) {
            $public_key=$res['public_key'];
        }
    }
    if (!empty($public_key)) {
        $ress=$sql->select('mem','*',1,array("public_key='".$public_key."'"),'',1);
        $transactions = $sql->select('trx','*',1,array("public_key='".$public_key."'"),'height DESC',$limit);
    }else{
        $ress=[];
        $transaction=[];
    }

    $transactions = array_merge($ress, $transactions);
    echo_display_json(true,$transactions);
} elseif ($q == "getTransaction") {
    /**
     * @api {get} /api.php?q=getTransaction  07. getTransaction
     * @apiName getTransaction
     * @apiGroup API
     * @apiDescription Returns one transaction.
     *
     * @apiParam {string} transaction Transaction ID
     *
     * @apiSuccess {string} block  Block ID
     * @apiSuccess {numeric} confirmation Number of confirmations
     * @apiSuccess {numeric} date  Transaction's date in UNIX TIMESTAMP format
     * @apiSuccess {string} dst  Transaction destination
     * @apiSuccess {numeric} fee  The transaction's fee
     * @apiSuccess {numeric} height  Block height
     * @apiSuccess {string} id  Transaction ID/HASH
     * @apiSuccess {string} message  Transaction's message
     * @apiSuccess {string} signature  Transaction's signature
     * @apiSuccess {string} public_key  Account's public_key
     * @apiSuccess {string} src  Sender's address
     * @apiSuccess {string} type  "debit", "credit" or "mempool"
     * @apiSuccess {numeric} val Transaction value
     * @apiSuccess {numeric} version Transaction version
     */

    $id = san($data['transaction']);

    $sql=OriginSql::getInstance();

    $res=$sql->select('trx','*',1,array("id='".$id."'"),'',1);
    if ($res == false) {
        $res=$sql->select('mem','*',1,array("id='".$id."'"),'',1);
    }
    if ($res == false) {
        echo_display_json(false,'invalid transaction');
    }else{
        echo_display_json(true,$res);
    }

} elseif ($q == "getPublicKey") {
    /**
     * @api {get} /api.php?q=getPublicKey  08. getPublicKey
     * @apiName getPublicKey
     * @apiGroup API
     * @apiDescription Returns the public key of a specific account.
     *
     * @apiParam {string} account Account id / address
     *
     * @apiSuccess {string} data The public key
     */

    $account = san($data['account']);
    if (empty($account)) {
        echo_display_json(false,'Invalid account id');
    }

    $sql=OriginSql::getInstance();
    $res=$sql->select('acc','public_key',1,array("id='".$account."'"),'',1);
    if ($res) {
        echo_display_json(true,$res['public_key']);
    } else {
        echo_display_json(false,'No public key found for this account');
    }
} elseif ($q == "generateAccount") {
    /**
     * @api {get} /api.php?q=generateAccount  09. generateAccount
     * @apiName generateAccount
     * @apiGroup API
     * @apiDescription Generates a new account. This function should only be used when the node is on the same host or over a really secure network.
     *
     * @apiSuccess {string} address Account address
     * @apiSuccess {string} public_key Public key
     * @apiSuccess {string} private_key Private key
     */
    $acc=Accountinc::getInstance();
    $res = $acc->generate_account();
    if ($res) {
        echo_display_json(true,$res);
    }else{
        echo_display_json(false,$res);
    }
    
} elseif ($q == "currentBlock") {
    /**
     * @api {get} /api.php?q=currentBlock  10. currentBlock
     * @apiName currentBlock
     * @apiGroup API
     * @apiDescription Returns the current block.
     *
     * @apiSuccess {string} id Blocks id
     * @apiSuccess {string} generator Block Generator
     * @apiSuccess {numeric} height Height
     * @apiSuccess {numeric} date Block's date in UNIX TIMESTAMP format
     * @apiSuccess {string} nonce Mining nonce
     * @apiSuccess {string} signature Signature signed by the generator
     * @apiSuccess {numeric} difficulty The base target / difficulty
     * @apiSuccess {string} argon Mining argon hash
     */
    $block=Blockinc::getInstance();
    $current = $block->current();
    echo_display_json(true,$current);
} elseif ($q == "getBlock") {
    /**
     * @api {get} /api.php?q=getBlock  11. getBlock
     * @apiName getBlock
     * @apiGroup API
     * @apiDescription Returns the block.
     *
     * @apiParam {numeric} height Block Height
     *
     * @apiSuccess {string} id Block id
     * @apiSuccess {string} generator Block Generator
     * @apiSuccess {numeric} height Height
     * @apiSuccess {numeric} date Block's date in UNIX TIMESTAMP format
     * @apiSuccess {string} nonce Mining nonce
     * @apiSuccess {string} signature Signature signed by the generator
     * @apiSuccess {numeric} difficulty The base target / difficulty
     * @apiSuccess {string} argon Mining argon hash
     */
    $height = san($data['height']);
    $block=Blockinc::getInstance();
    $res=$block->get_block_from_height($height);

    if ($res == false) {
        echo_display_json(false,"Invalid block");
    } else {
        echo_display_json(true,$res);
    }
} elseif ($q == "getBlockTransactions") {
    /**
     * @api {get} /api.php?q=getBlockTransactions  12. getBlockTransactions
     * @apiName getBlockTransactions
     * @apiGroup API
     * @apiDescription Returns the transactions of a specific block.
     *
     * @apiParam {numeric} [height] Block Height
     * @apiParam {string} [block] Block id
     *
     * @apiSuccess {string} block  Block ID
     * @apiSuccess {numeric} confirmations Number of confirmations
     * @apiSuccess {numeric} date  Transaction's date in UNIX TIMESTAMP format
     * @apiSuccess {string} dst  Transaction destination
     * @apiSuccess {numeric} fee  The transaction's fee
     * @apiSuccess {numeric} height  Block height
     * @apiSuccess {string} id  Transaction ID/HASH
     * @apiSuccess {string} message  Transaction's message
     * @apiSuccess {string} signature  Transaction's signature
     * @apiSuccess {string} public_key  Account's public_key
     * @apiSuccess {string} src  Sender's address
     * @apiSuccess {string} type  "debit", "credit" or "mempool"
     * @apiSuccess {numeric} val Transaction value
     * @apiSuccess {numeric} version Transaction version
     */
    $height = san($data['height']);
    $block = san($data['block']);

    $sql=OriginSql::getInstance();

    if (!empty($height)) {
        $res=$sql->select('trx','*',0,array("height=".$height),'',0);
    }else{
        $res=$sql->select('trx','*',0,array("block='".$block."'"),'',0);
    }
    if ($res == false) {
        echo_display_json(false,"Invalid block");
    } else {
        echo_display_json(true,$res);
    }
} elseif ($q == "version") {
    /**
     * @api {get} /api.php?q=version  13. version
     * @apiName version
     * @apiGroup API
     * @apiDescription Returns the node's version.
     *
     *
     * @apiSuccess {string} data  Version
     */
    echo_display_json(true,'');
} elseif ($q == "send") {
    /**
     * @api {get} /api.php?q=send  14. send
     * @apiName send
     * @apiGroup API
     * @apiDescription Sends a transaction.
     *
     * @apiParam {numeric} val Transaction value (without fees)
     * @apiParam {string} dst Destination address
     * @apiParam {string} public_key Sender's public key
     * @apiParam {string} [signature] Transaction signature. It's recommended that the transaction is signed before being sent to the node to avoid sending your private key to the node.
     * @apiParam {string} [private_key] Sender's private key. Only to be used when the transaction is not signed locally.
     * @apiParam {numeric} [date] Transaction's date in UNIX TIMESTAMP format. Requried when the transaction is pre-signed.
     * @apiParam {string} [message] A message to be included with the transaction. Maximum 128 chars.
     * @apiParam {numeric} [version] The version of the transaction. 1 to send coins.
     *
     * @apiSuccess {string} data  Transaction id
     */
    $block=Blockinc::getInstance();
    $current = $block->current();
    $acc=Accountinc::getInstance();
    $mem=Mempoolinc::getInstance();

    $version = intval($data['version']);
    $dst = san($data['dst']);
    $public_key = san($data['public_key']);
    $private_key = san($data['private_key']);
    $signature = san($data['signature']);
    //
    if (empty($public_key) and empty($private_key)) {
        echo_display_json(false,"Either the private key or the public key must be sent");
        exit;
    }
    if (empty($private_key) and empty($signature)) {
        echo_display_json(false,"Either the private_key or the signature must be sent");
        exit;
    }
    //
    if (empty($public_key)) {
        $pk = coin2pem($private_key, true);
        $pkey = openssl_pkey_get_private($pk);
        $pub = openssl_pkey_get_details($pkey);
        $public_key = pem2coin($pub['key']);
    }

    if ($version!=1 and $version!=2 and $version!=3) {
        echo_display_json(false,"version fail");
        exit;
    }

    //
    if ($version==1) {
        if (!$acc->address_alive_from_address($dst)) {
            echo_display_json(false,"Invalid destination address");
            exit;
        }
        $dst_b = base58_decode($dst);
        if (strlen($dst_b) != 64) {
            echo_display_json(false,"Invalid destination address");
            exit;
        }
    } elseif ($version==2) {
        $dst=strtolower($dst);
        $dst = san($dst);
        if (!$acc->alias_alive_from_alias($dst)) {
            echo_display_json(false,"Invalid destination alias");
            exit;
        }
    }
    if (!$acc->public_key_alive_from_public($public_key)) {
        echo_display_json(false,"Invalid public key");
        exit;
    }
    //
    if (Blacklist::checkPublicKey($public_key)) {
        echo_display_json(false,"Blacklisted public key");
        exit;
    }
    if (Blacklist::checkAddress($dst)) {
        echo_display_json(false,"Blacklisted address");
        exit;
    }
    if (Blacklist::checkalias($dst)) {
        echo_display_json(false,"Blacklisted alias");
        exit;
    }

    $date = $data['date'] + 0;
    if ($date == 0) {
        $date = time();
    }
    if ($date < time() - (3600 * 24 * 48)) {
        echo_display_json(false,"The date is too old");
        exit;
    }
    if ($date > time() + 86400) {
        echo_display_json(false,"Invalid Date");
        exit;
    }
    $message=$data['message'];
    if (strlen($message) > 128) {
        echo_display_json(false,"The message must be less than 128 chars");
        exit;
    }
    //
    
    if ($version==3) {
        $val = $data['val'] + 0;
        if ($val!=0) {
            echo_display_json(false,"The val must be 0");
            exit;
        }
        if ($fee != 10) {
            echo_display_json(false,"The fee must be 10");
            exit;
        } 
    }else{
        $val = $data['val'] + 0;
        $fee = $val * 0.005;
        if ($fee < 0.00000001) {
            $fee = 0.00000001;
        }   
    }

    //
    // set alias
    if ($version==3) {
        $message = san($message);
        $message=strtolower($message);
        if ($acc->alias_alive_from_alias($message)==true) {
            echo_display_json(false,"Invalid alias");
            exit;
        }
        if ($acc->alias_alive_from_public_key($public_key)==true) {
            echo_display_json(false,"This account already has an alias");
            exit;
        }
    }

    if (empty($signature)) {
        $signature=$mem->signature($dst,$val,$fee,$version,$message,$date,$public_key, $private_key);
    }

    $hash=$mem->hasha($dst,$val,$fee,$signature,$version,$message,$date,$public_key);


    $transaction = [
        "id"         => $hash,
        "height"     => $current['height']+1,
        "val"        => $val,
        "fee"        => $fee,
        "dst"        => $dst,
        "public_key" => $public_key,
        "date"       => $date,
        "version"    => $version,
        "message"    => $message,
        "signature"  => $signature,
        "peer"       => 'local'
    ];


    if (!$mem->check($transaction)) {
        echo_display_json(false,"check is failed");
        exit;
    }
    $res=$mem->add_mempool($transaction['height'],$transaction['dst'],$transaction['val'],$transaction['fee'],
        $transaction['signature'],$transaction['version'],$transaction['message'],$transaction['public_key'],
        $transaction['date'], $transaction['peer']);

    if ($res==false) {
        echo_display_json(false,"add in mempool is fail");
        exit;
    }else{
        $Security=Security::getInstance();
        $cmd=$Security->cmd('php propagate.php',['transaction',$hash]);
        system($cmd);
        echo_display_json(true,$hash);
    }


} elseif ($q == "mempoolSize") {
    /**
     * @api {get} /api.php?q=mempoolSize  15. mempoolSize
     * @apiName mempoolSize
     * @apiGroup API
     * @apiDescription Returns the number of transactions in mempool.
     *
     * @apiSuccess {numeric} data  Number of mempool transactions
     */
    $sql=OriginSql::getInstance();
    $res = $sql->select(mem,'*',2,array(),'',0);
    if ($res) {
        echo_display_json(true,$res);
    }else{
        echo_display_json(true,'0');
    }

} elseif ($q == 'randomNumber') {
    /**
     * @api {get} /api.php?q=randomNumber 16. randomNumber
     * @apiName randomNumber
     * @apiGroup API
     * @apiDescription Returns a random number based on an ORIGIN block id.
     *
     * @apiParam {numeric} height The height of the block on which the random number will be based on (should be a future block when starting)
     * @apiParam {numeric} min Minimum number (default 1)
     * @apiParam {numeric} max Maximum number
     * @apiParam {string} seed A seed to generate different numbers for each use cases.
     * @apiSuccess {numeric} data  The random number
     */

    $height = san($_GET['height']);
    $max = intval($_GET['max']);
    $min=$_GET['min'];
    $seed=$_GET['seed'];
    if (empty($min)) {
        $min = 1;
    } else {
        $min = intval($min);
    }
    $sql=OriginSql::getInstance();
    $blk = $sql->select('block','id',1,array("height=".$height),'',1);
    if ($blk === false) {
        echo_display_json(false,"Unknown block. Future?");
        exit;
    }
    $base = hash("sha256", $blk.$seed);

    $seed1 = hexdec(substr($base, 0, 12));
    // generate random numbers based on the seed
    mt_srand($seed1, MT_RAND_MT19937);
    $res = mt_rand($min, $max);
    echo_display_json(true,$res);
} elseif ($q == "checkSignature") {
    /**
     * @api {get} /api.php?q=checkSignature  17. checkSignature
     * @apiName checkSignature
     * @apiGroup API
     * @apiDescription Checks a signature against a public key
     *
     * @apiParam {string} [public_key] Public key
     * @apiParam {string} [signature] signature
     * @apiParam {string} [data] signed data
     *
     *
     * @apiSuccess {boolean} data true or false
     */

    $public_key=san($data['public_key']);
    $signature=san($data['signature']);
    $data=$data['data'];
    $res=ec_verify($data, $signature, $public_key);
    if ($res==true) {
        echo_display_json(true,'true');
    }else{
        echo_display_json(false,'false');
    }
    
} elseif ($q == "masternodes") {
    /**
     * @api {get} /api.php?q=masternodes  18. masternodes
     * @apiName masternodes
     * @apiGroup API
     * @apiDescription Returns all the masternode data
     *
     *
     *
     * @apiSuccess {boolean} data masternode date
     */
    $sql=OriginSql::getInstance();
    $res=$sql->select('mn','*',0,array(),'public_key ASC',0);

    echo_display_json(true,$res);
} elseif ($q == "getAlias") {
    /**
     * @api {get} /api.php?q=getAlias  19. getAlias
     * @apiName getAlias
     * @apiGroup API
     * @apiDescription Returns the alias of an account
     *
     * @apiParam {string} [public_key] Public key
     * @apiParam {string} [account] Account id / address
     *
     *
     * @apiSuccess {string} data alias
     */

    $public_key = san($data['public_key']);
    $account = san($data['account']);

    $sql=OriginSql::getInstance();


    if (!empty($public_key)) {
        $res=$sql->select('acc','alias',1,array("public_key='".$public_key."'"),'',1);
    }elseif (!empty($account)) {
        $res=$sql->select('acc','alias',1,array("id='".$account."'"),'',1);
    }
    if ($res) {
        echo_display_json(true,$res['alias']);
    }else{
        echo_display_json(false,'alias fail');
    }

} elseif ($q === 'sanity') {
    /**
     * @api            {get} /api.php?q=sanity  20. sanity
     * @apiName        sanity
     * @apiGroup       API
     * @apiDescription Returns details about the node's sanity process.
     *
     * @apiSuccess {object}  data A collection of data about the sanity process.
     * @apiSuccess {boolean} data.sanity_running Whether the sanity process is currently running.
     * @apiSuccess {number}  data.last_sanity The timestamp for the last time the sanity process was run.
     * @apiSuccess {boolean} data.sanity_sync Whether the sanity process is currently synchronising.
     */
    $sanity = file_exists(__DIR__.'/tmp/sanity-lock');
    if ($sanity) {
        $sanity='true';
    }else{
        $sanity='false';
    }

    $config=Configinc::getInstance();
    $sanity_last=$config->get_val('sanity_last');
    $sanity_sync=$config->get_val('sanity_sync');

    $arrayName = array('sanity_running' => $sanity, 'sanity_last' => $sanity_last, 'sanity_sync' => $sanity_sync);
    echo_display_json(true,$arrayName);

} elseif ($q === 'node-info') {
    /**
     * @api            {get} /api.php?q=node-info  21. node-info
     * @apiName        node-info
     * @apiGroup       API
     * @apiDescription Returns details about the node.
     *
     * @apiSuccess {object}  data A collection of data about the node.
     * @apiSuccess {string} data.hostname The hostname of the node.
     * @apiSuccess {string} data.version The current version of the node.
     * @apiSuccess {string} data.dbversion The database schema version for the node.
     * @apiSuccess {number} data.accounts The number of accounts known by the node.
     * @apiSuccess {number} data.transactions The number of transactions known by the node.
     * @apiSuccess {number} data.mempool The number of transactions in the mempool.
     * @apiSuccess {number} data.masternodes The number of masternodes known by the node.
     * @apiSuccess {number} data.peers The number of valid peers.
     */
    $config=Configinc::getInstance();
    $dbVersion = $config->get_val('version');
    $hostname = $config['hostname'];

    $sql=OriginSql::getInstance();


    $acc = $sql->select('acc','*',2,array(),'',1);
    $tr = $sql->select('trx','*',2,array(),'',1);
    $masternodes = $sql->select('mn','*',2,array(),'',1);
    $mempool = $sql->select('mem','*',2,array(),'',1);
    $peers = $sql->select('peer','*',2,array("blacklisted<".time()),'',1);
    $arrayName = [
        'hostname'     => $hostname,
        'version'      => '',
        'dbversion'    => $dbVersion,
        'accounts'     => $acc,
        'transactions' => $tr,
        'mempool'      => $mempool,
        'masternodes'  => $masternodes,
        'peers'        => $peers
    ];
    echo_display_json(true,$arrayName);
} elseif ($q === 'checkAddress') {
    /**
     * @api            {get} /api.php?q=checkAddress  22. checkAddress
     * @apiName        checkAddress
     * @apiGroup       API
     * @apiDescription Checks the validity of an address.
     *
     * @apiParam {string} account Account id / address
     * @apiParam {string} [public_key] Public key
     *
     * @apiSuccess {boolean} data True if the address is valid, false otherwise.
     */

    $address=san($data['account']);
    $public_key=san($data['public_key']);


    if (!valid_len($address)) {
        echo_display_json(false,'false');
    }

    $dst_b = base58_decode($address);
    if (strlen($dst_b) != 64) {
        echo_display_json(false,'false');
    }

    $acc=Accountinc::getInstance();
    if (!empty($public_key)) {
        if($acc->get_address_from_public_key($public_key)!=$address){
            echo_display_json(false,'false');
        }
    }
    echo_display_json(true,'true');
} else {

    echo_display_json(false,"Invalid request");
}


function echo_display_json($status = true, $data){
    if (headers_sent() == false) {
        header('Content-Type: application/json');
    }
    if ($status == true) {
        echo json_encode(["status" => "ok", "data" => $data, "coin" => 'origin']);
    } else {
        echo json_encode(["status" => "error", "data" => $data, "coin" => 'origin']);
    }
}

?>