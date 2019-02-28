<?php
// version: 20190128 test
class OriginSql{
	private static $_instance = null;
	private static $_connect = null;
	private static $initt=array(
								'accounts'=>array(
												'id' => 'string',
												'public_key' => 'string',
												'block' => 'string',
												'balance' => 'num',
												'alias' => 'string',
												),
								'blocks'=>array(
												'id' => 'string',
												'generator' => 'string',
												'height' => 'num',
												'date' => 'num',
												'nonce' => 'string',
												'signature' => 'string',
												'difficulty' => 'string',
												'argon' => 'string',
												'transactions' => 'num',
												),
								'config'=>array(
												'cfg' => 'string',
												'val' => 'string',
												),
								'masternode'=>array(
												'public_key' => 'string',
												'height' => 'num',
												'ip' => 'string',
												'last_won' => 'num',
												'blacklist' => 'num',
												'fails' => 'num',
												'status' => 'num',
												),
								'mempool'=>array(
												'id' => 'string',
												'height' => 'num',
												'dst' => 'string',
												'val' => 'num',
												'fee' => 'num',
												'signature' => 'string',
												'version' => 'num',
												'message' => 'string',
												'date' => 'num',
												'public_key' => 'string',
												'peer' => 'string',
												),
								'peers'=>array(
												'id' => 'num',
												'hostname' => 'string',
												'blacklisted' => 'num',
												'ping' => 'num',
												'reserve' => 'num',
												'ip' => 'string',
												'fails' => 'num',
												'stuckfail' => 'num',
												),
								'transactions'=>array(
												'id' => 'string',
												'height' => 'num',
												'dst' => 'string',
												'val' => 'num',
												'fee' => 'num',
												'signature' => 'string',
												'version' => 'num',
												'message' => 'string',
												'date' => 'num',
												'public_key' => 'string',
												'block' => 'string',
												),
								 );
	private static $tb = array(
			'acc' =>'accounts',
			'block'=>'blocks',
			'config'=>'config',
			'mn'=>'masternode',
			'mem'=>'mempool',
			'peer'=>'peers',
			'trx'=>'transactions'
			 );
	function __construct(){
		# code...
	}
    public static function getInstance(){
    	if (self::$_connect === null) {
    		$config=include(__DIR__.'../../config/config_db.php');
	        $db_config = array(
	            'host'=>$config['db_host'],
	            'dbname'=>$config['db_dbname'],
	            'user'=>$config['db_user'],
	            'pwd'=>$config['db_pass']
	        );
	        $db = MainSQLpdo::getInstance();
	        $db->connect($db_config);
	        if (!$db) {   die("Could not connect to the DB backend.");    }else{	self::$_connect=$db;	}
    	}
        if(self::$_instance === null){
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function close(){
    	$db=MainSQLpdo::getInstance();
    	$db->close();
    	self::$_connect=null;
    	self::$_instance=null;
    }
	public function exec($sql){
		$db=MainSQLpdo::getInstance();
		return $db->exec($sql);
	}
    public function beginTransaction(){
    	$db=MainSQLpdo::getInstance();
        $db->beginTransaction();
    }

    public function rollback(){
    	$db=MainSQLpdo::getInstance();
        $db->rollback();
    }
    public function commit(){
    	$db=MainSQLpdo::getInstance();
        $db->commit();
    }
    public function lock_tables($tables='blocks,accounts,transactions,mempool,masternode,peers',$model='WRITE'){
    	if ($tables==='') {
    		$tables='blocks,accounts,transactions,mempool,masternode,peers';
    	}
    	if ($model==='') {
    		$model='WRITE';
    	}
    	$tables_str = explode(',', $tables);
    	$set='';
    	foreach ($tables_str as $value) {
    		$set=$set.','.$value.' '.$model;
    	}
		if (substr($set,0,1)==',') {
			$set=substr($set,1);
		}

		$db=MainSQLpdo::getInstance();
		$this->exec('LOCK TABLES '.$set);
    }
    public function unlock_tables(){
    	$db=MainSQLpdo::getInstance();
    	$this->exec('UNLOCK TABLES');
    }
	private function rename_tables($table){
		if ($table==='' or $table===NULL) {
			return 'XXX';
		}
		if (isset(self::$tb[$table])) {
			return self::$tb[$table];
		}else{
			return $table;
		}
	}

/*
	$data = array(
		'field1' => 'value1',
		'field2' => 'value2',
		'field3' => 'value3',
	 );

*/

	public function add($table,$data=array()){

		$table=$this->rename_tables($table);

		if (!isset(self::$initt[$table])) {
			return false;
		}
		if (!is_array($data)) {
			return false;
		}
		$set='';
		foreach ($data as $key => $value) {
			if (!isset(self::$initt[$table][$key])) {
				return false;
			}
			if (self::$initt[$table][$key]=='num') {
				$set=$set.',`'.$key.'`='.$value;
			}elseif(self::$initt[$table][$key]=='string'){
				if ($value=='NULL' or $value==NULL) {
					$set=$set.',`'.$key.'`=NULL';
				}else{
					$set=$set.',`'.$key.'`='."'".$value."'";
				}
			}elseif(self::$initt[$table][$key]=='deci'){
				$value=number_format($value, 8, ".", "");
				$set=$set.',`'.$key.'`='."'".$value."'";
			}
			
		}
		if (substr($set,0,1)==',') {
			$set=substr($set,1);
		}

		$db=MainSQLpdo::getInstance();
		$canshu = array(
		    'debug' => 0,
		    'mode' => 1,
		    'table' => $table,
		    'set' => $set,
		 );
		return $db->insert($canshu);
	}

	// 'mode' => 1, //0=more 1=one 2=line
	// 'fields' => '*',
	// 'limit' => 1,  
	public function select($table,$fields='*',$mode=1,$sqlwhere=array(),$orderby='',$limit=1){
		if ($fields==='') {
			$fields='*';
		}
		if ($mode==='') {
			$mode=1;
		}
		if ($limit==='') {
			$limit=1;
		}
		$table=$this->rename_tables($table);
		if (!isset(self::$initt[$table])) {
			return false;
		}

		$db=MainSQLpdo::getInstance();
		$canshu = array(
		    'debug' => 0,
		    'mode' => $mode,
		    'table' => $table,
		    'fields' => $fields,
		    'sqlwhere' => $sqlwhere,
		    'orderby' =>$orderby,
		    'limit' => $limit,

		 );
		return $db->select($canshu);
	}
/*
	$data = array(
		'field1' => 'value1',
		'field2' => 'value2',
		'field3' => 'value3',
	 );

*/
	public function update($table,$data=array(),$sqlwhere=array()){
		$table=$this->rename_tables($table);
		if (!isset(self::$initt[$table])) {
			return false;
		}
		if (!is_array($data)) {
			return false;
		}
		$set='';
		foreach ($data as $key => $value) {
			if (!isset(self::$initt[$table][$key])) {
				return false;
			}
			if (self::$initt[$table][$key]=='num') {
				$set=$set.',`'.$key.'`='.$value;
			}elseif(self::$initt[$table][$key]=='string'){
				if ($value=='NULL' or $value==NULL) {
					$set=$set.',`'.$key.'`=NULL';
				}else{
					$set=$set.',`'.$key.'`='."'".$value."'";
				}
				
			}elseif(self::$initt[$table][$key]=='deci'){
				$value=number_format($value, 8, ".", "");
				$set=$set.',`'.$key.'`='."'".$value."'";
			}
			
		}
		if (substr($set,0,1)==',') {
			$set=substr($set,1);
		}


		$db=MainSQLpdo::getInstance();
		$canshu = array(
		    'debug' => 0,
		    'mode' => 1,
		    'table' => $table,
		    'set' => $set,
		    'sqlwhere' => $sqlwhere,

		 );
		return $db->update($canshu);
	}
	public function delete($table,$sqlwhere=array()){
		$table=$this->rename_tables($table);
		if (!isset(self::$initt[$table])) {
			return false;
		}


		$db=MainSQLpdo::getInstance();
		$canshu = array(
		    'debug' => 0,
		    'mode' => 1,
		    'table' => $table,
		    'sqlwhere' => $sqlwhere,

		 );

		return $db->delete($canshu);
	}

	public function sum($table,$fileds,$sqlwhere){
		$table=$this->rename_tables($table);
		if (!isset(self::$initt[$table])) {
			return false;
		}

		if (is_array($fileds)) {
			$fileds=implode('+', $fileds);
		}

        if(is_array($sqlwhere)){
            $sqlwhere = ' and '.implode(' and ', $sqlwhere);
        }else{
        	$sqlwhere = ' and '.$sqlwhere;
        }

		$sql='SELECT SUM('.$fileds.') as keyy FROM '.$table.' WHERE 1=1 '.$sqlwhere;

		$db=MainSQLpdo::getInstance();
		if ($db->query($sql)==true) {
			$row=$db->fetch();
			if ($row['keyy']==NULL or $row['keyy']=='') {
				return 0;
			}else{
				return $row['keyy'];
			}	
		}else{
			return false;
		}
		
	}

}


?>