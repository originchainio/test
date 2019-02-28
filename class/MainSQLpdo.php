<?php
class MainSQLpdo{
    private static $_instance = null;
    private $_db;
    protected $res;
    private function __construct(){
    }
	private function __clone()  
	{  
	}
    public static function getInstance()
    {
        if(self::$_instance === null)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    public function connect($config){

        $this->_db = new PDO('mysql:host='.$config['host'].';dbname='.$config['dbname'].';charset=utf8mb4', $config['user'], $config['pwd']);
        // $this->_db->query('set names utf8;');
        //$this->_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    
    }
    public function close(){
        $this->_db = null;
    }
     
    public function query($sql){
        $res = $this->_db->query($sql);
        if($res){
            $this->res = $res;
            return true;
        }
        return false;
    }
    public function exec($sql){
        $res = $this->_db->exec($sql);
        if($res){
            $this->res = $res;
            return true;
        }
        return false;
    }

    public function beginTransaction(){
        $this->_db->beginTransaction();
    }

    public function rollback(){
        $this->_db->rollback();
    }
    
    public function commit(){
        $this->_db->commit();
    }
    public function fetchAll(){
        $this->res->setFetchMode(PDO::FETCH_ASSOC);
        return $this->res->fetchAll();
    }
    public function fetch(){
        $this->res->setFetchMode(PDO::FETCH_ASSOC);
        return $this->res->fetch();
    }
    public function fetchColumn(){
        return $this->res->fetchColumn();
    }
    public function lastInsertId(){
        return $this->res->lastInsertId();
    }

    /**
     * 
     * int              $debug      debug
     *                              0   not open
     *                              1   open
     *                              2   open and exit
     * int              $mode       return mode
     *                              0   return more
     *                              1   return one
     *                              2   return count
     * string/array     $table      table
     *                              normal
     *                              'tb_member, tb_money'
     *                              arr mode
     *                              array('tb_member', 'tb_money')
     * string/array     $fields     
     *                              normal
     *                              'username, password'
     *                              arr mode
     *                              array('username', 'password')
     * string/array     $sqlwhere   
     *                              normal
     *                              'and type = 1 and username like "%os%"'
     *                              arr mode
     *                              array('type = 1', 'username like "%os%"')
     * string           $orderby    orderby :id desc
     */
    public function select($canshu){
        $debug=$canshu['debug'];
        $mode=$canshu['mode'];
        $table=$canshu['table'];
        $fields=$canshu['fields'];
        $sqlwhere=$canshu['sqlwhere'];
        $orderby=$canshu['orderby'];
        if($mode==0){
            if($canshu['limit']==0){
                $limit="";
            }else{
                $limit=" limit ".$canshu['limit'];
            }
            if (strpos($canshu['limit'], ',')) {
                $limit=" limit ".$canshu['limit'];
            }
        }else{
            $limit="";
        }
        if(is_array($table)){
            $table = implode(', ', $table);
        }
        if(is_array($fields)){
            $fields = implode(', ', $fields);
        }
        if(is_array($sqlwhere) and count($sqlwhere)>=1){
            $sqlwhere = ' and '.implode(' and ', $sqlwhere);
        }else{
            $sqlwhere ='';
        }

        if($orderby!==""){
            $orderby=' order by '.$orderby;
        }
        if($debug === 0){
            if($mode === 2){
                if ($this->query("select count(*) from $table where 1=1 $sqlwhere")) {
                    $return = $this->fetchColumn();
                }else{
                    $return = false;
                }
            }else if($mode === 1){
                if ($this->query("select $fields from $table where 1=1 $sqlwhere".$orderby." limit 1")) {
                    $return = $this->fetch();
                }else{
                    $return = false;
                }
                
            }else{
                if ($this->query("select $fields from $table where 1=1 $sqlwhere".$orderby.$limit)) {
                   $return = $this->fetchAll();
                }else{
                    $return = false;
                }
            }
            return $return;
        }else{
            if($mode === 2){
                echo "select count(*) from $table where 1=1 $sqlwhere";
            }else if($mode === 1){
                echo "select $fields from $table where 1=1 $sqlwhere".$orderby." limit 1";
            }
            else{
                echo "select $fields from $table where 1=1 $sqlwhere".$orderby.$limit;
            }
            if($debug === 2){
                exit;
            }
        }
    }

    /**
     * 
     * int              $debug      debug
     *                              0   not open
     *                              1   open
     *                              2   open and exit
     * int              $mode       return mode
     *                              0   null
     *                              1   return count
     *                              2   return id
     * string/array     $table      table
     *                              normal
     *                              'tb_member, tb_money'
     *                              arr mode
     *                              array('tb_member', 'tb_money')
     * string/array     $set        
     *                              normal
     *                              'username = "test", type = 1, dt = now()'
     *                              arr mode
     *                              array('username = "test"', 'type = 1', 'dt = now()')
     */
    public function insert($canshu){
        $debug=$canshu['debug'];
        $mode=$canshu['mode'];
        $table=$canshu['table'];
        $set=$canshu['set'];
        if(is_array($table)){
            $table = implode(', ', $table);
        }
        if(is_array($set)){
            $set = implode(', ', $set);
        }
        if($debug === 0){
            if($mode === 2){
                if ($this->query("insert into $table set $set")) {
                    $return = $this->lastInsertId();
                }else{
                    $return = false;
                }
            }else if($mode === 1){
                if ($this->exec("insert into $table set $set")) {
                   $return = $this->res;
                }else{
                    $return = false;
                }
            }else{
                if ($this->query("insert into $table set $set")) {
                    $return = NULL;
                }else{
                    $return = false;
                }
            }
            return $return;
        }else{
            echo "insert into $table set $set";
            if($debug === 2){
                exit;
            }
        }
    }

    /**
     * 
     * int              $debug      debug
     *                              0   not open
     *                              1   open
     *                              2   open and exit
     * int              $mode       return mode
     *                              0   null
     *                              1   return count
     * string           $table      table
     *                              normal
     *                              'tb_member, tb_money'
     *                              arr mode
     *                              array('tb_member', 'tb_money')
     * string/array     $set        update
     *                              normal
     *                              'username = "test", type = 1, dt = now()'
     *                              arr mode
     *                              array('username = "test"', 'type = 1', 'dt = now()')
     * string/array     $sqlwhere   
     *                              normal
     *                              'and type = 1 and username like "%os%"'
     *                              arr mode
     *                              array('type = 1', 'username like "%os%"')
     */
    public function update($canshu){
        $debug=$canshu['debug'];
        $mode=$canshu['mode'];
        $table=$canshu['table'];
        $set=$canshu['set'];
        $sqlwhere=$canshu['sqlwhere'];
        if(is_array($table)){
            $table = implode(', ', $table);
        }
        if(is_array($set)){
            $set = implode(', ', $set);
        }
        if(is_array($sqlwhere)){
            $sqlwhere = ' and '.implode(' and ', $sqlwhere);
        }
        if($debug === 0){
            if($mode === 1){
                if ($this->exec("update $table set $set where 1=1 $sqlwhere")) {
                    $return = $this->res;
                }else{
                    $return = false;
                }
            }else{
                if ($this->query("update $table set $set where 1=1 $sqlwhere")) {
                    $return = NULL;
                }else{
                    $return = false;
                }      
            }
            return $return;
        }else{
            echo "update $table set $set where 1=1 $sqlwhere";
            if($debug === 2){
                exit;
            }
        }
    }
     
    /**
     * 
     * int              $debug      debug
     *                              0   not open
     *                              1   open
     *                              2   open and exit
     * int              $mode       return mode
     *                              0   null
     *                              1   return count
     * string           $table      table
     * string/array     $sqlwhere   del
     *                              normal
     *                              'and type = 1 and username like "%os%"'
     *                              arr mode
     *                              array('type = 1', 'username like "%os%"')
     */
    public function delete($canshu){
        $debug=$canshu['debug'];
        $mode=$canshu['mode'];
        $table=$canshu['table'];
        $sqlwhere=$canshu['sqlwhere'];
        if(is_array($sqlwhere)){
            $sqlwhere = ' and '.implode(' and ', $sqlwhere);
        }
        if($debug === 0){
            if($mode === 1){
                //echo "delete from $table where 1=1 $sqlwhere\n";
                if ($this->exec("delete from $table where 1=1 $sqlwhere")) {
                    $return = $this->res;
                }else{

                    $return = false;
                }
            }else{
                if ($this->query("delete from $table where 1=1 $sqlwhere")) {
                    $return = NULL;
                }else{
                    $return = false;
                }  
            }
            return $return;
        }else{
            echo "delete from $table where 1=1 $sqlwhere";
            if($debug === 2){
                exit;
            }
        }
    }
}


?>