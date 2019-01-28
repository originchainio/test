<?php
class MainSQLpdo{
    private static $_instance = null;
    private $_db;
    protected $res;
    /*构造函数*/
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
    /*数据库连接*/
    public function connect($config){

        $this->_db = new PDO('mysql:host='.$config['host'].';dbname='.$config['dbname'].';charset=utf8mb4', $config['user'], $config['pwd']);
        // $this->_db->query('set names utf8;');
        //把结果序列化成stdClass
        //$this->_db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        //自己写代码捕获Exception
        $this->_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    
    }
     
    /*数据库关闭*/
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
     * 参数说明
     * int              $debug      是否开启调试，开启则输出sql语句
     *                              0   不开启
     *                              1   开启
     *                              2   开启并终止程序
     * int              $mode       返回类型
     *                              0   返回多条记录
     *                              1   返回单条记录
     *                              2   返回行数
     * string/array     $table      数据库表，两种传值模式
     *                              普通模式：
     *                              'tb_member, tb_money'
     *                              数组模式：
     *                              array('tb_member', 'tb_money')
     * string/array     $fields     需要查询的数据库字段，允许为空，默认为查找全部，两种传值模式
     *                              普通模式：
     *                              'username, password'
     *                              数组模式：
     *                              array('username', 'password')
     * string/array     $sqlwhere   查询条件，允许为空，两种传值模式
     *                              普通模式：
     *                              'and type = 1 and username like "%os%"'
     *                              数组模式：
     *                              array('type = 1', 'username like "%os%"')
     * string           $orderby    排序，id desc
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
        //参数处理
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
        //数据库操作
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
     * 参数说明
     * int              $debug      是否开启调试，开启则输出sql语句
     *                              0   不开启
     *                              1   开启
     *                              2   开启并终止程序
     * int              $mode       返回类型
     *                              0   无返回信息
     *                              1   返回执行条目数
     *                              2   返回最后一次插入记录的id
     * string/array     $table      数据库表，两种传值模式
     *                              普通模式：
     *                              'tb_member, tb_money'
     *                              数组模式：
     *                              array('tb_member', 'tb_money')
     * string/array     $set        需要插入的字段及内容，两种传值模式
     *                              普通模式：
     *                              'username = "test", type = 1, dt = now()'
     *                              数组模式：
     *                              array('username = "test"', 'type = 1', 'dt = now()')
     */
    public function insert($canshu){
        $debug=$canshu['debug'];
        $mode=$canshu['mode'];
        $table=$canshu['table'];
        $set=$canshu['set'];

        //参数处理
        if(is_array($table)){
            $table = implode(', ', $table);
        }
        if(is_array($set)){
            $set = implode(', ', $set);
        }
        //数据库操作
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
     * 参数说明
     * int              $debug      是否开启调试，开启则输出sql语句
     *                              0   不开启
     *                              1   开启
     *                              2   开启并终止程序
     * int              $mode       返回类型
     *                              0   无返回信息
     *                              1   返回执行条目数
     * string           $table      数据库表，两种传值模式
     *                              普通模式：
     *                              'tb_member, tb_money'
     *                              数组模式：
     *                              array('tb_member', 'tb_money')
     * string/array     $set        需要更新的字段及内容，两种传值模式
     *                              普通模式：
     *                              'username = "test", type = 1, dt = now()'
     *                              数组模式：
     *                              array('username = "test"', 'type = 1', 'dt = now()')
     * string/array     $sqlwhere   修改条件，允许为空，两种传值模式
     *                              普通模式：
     *                              'and type = 1 and username like "%os%"'
     *                              数组模式：
     *                              array('type = 1', 'username like "%os%"')
     */
    public function update($canshu){
        $debug=$canshu['debug'];
        $mode=$canshu['mode'];
        $table=$canshu['table'];
        $set=$canshu['set'];
        $sqlwhere=$canshu['sqlwhere'];

        //参数处理
        if(is_array($table)){
            $table = implode(', ', $table);
        }
        if(is_array($set)){
            $set = implode(', ', $set);
        }
        if(is_array($sqlwhere)){
            $sqlwhere = ' and '.implode(' and ', $sqlwhere);
        }
        //数据库操作
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
     * 参数说明
     * int              $debug      是否开启调试，开启则输出sql语句
     *                              0   不开启
     *                              1   开启
     *                              2   开启并终止程序
     * int              $mode       返回类型
     *                              0   无返回信息
     *                              1   返回执行条目数
     * string           $table      数据库表
     * string/array     $sqlwhere   删除条件，允许为空，两种传值模式
     *                              普通模式：
     *                              'and type = 1 and username like "%os%"'
     *                              数组模式：
     *                              array('type = 1', 'username like "%os%"')
     */
    public function delete($canshu){
        $debug=$canshu['debug'];
        $mode=$canshu['mode'];
        $table=$canshu['table'];
        $sqlwhere=$canshu['sqlwhere'];
        //参数处理
        if(is_array($sqlwhere)){
            $sqlwhere = ' and '.implode(' and ', $sqlwhere);
        }
        //数据库操作
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