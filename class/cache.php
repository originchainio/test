<?php 
class cache
{   
    private static $_instance = null;
 
    protected $_options = array(
        'cache_dir'        => "./cache/",
        'file_name_prefix' => 'cache',
        'mode'            => '2', //mode=1:serialize model=2:file
    );  
     
    /**
     * getInstance
     * 
     * @return Ambiguous
     */
    public static function getInstance()
    {
        if(self::$_instance === null)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    } 
     
    /**
     * get
     * 
     * @param string $id
     * @return boolean|array
     */
    public static function get($id)
    {
        $id=$id.".php";
        $instance = self::getInstance();
         
        if(!$instance->has($id))
        {
            return false;
        }
         
        $file = $instance->_file($id);
         
        $data = $instance->_fileGetContents($file);
         
        if($data['expire'] == 0 || time() < $data['expire'])
        {
            return $data['contents'];
        }
        return false;
    }
     
    /**
     * set
     * 
     * @param string $id   id
     * @param array  $data data
     * @param int    $cacheLife Cache life defaults to 0 infinite life
     */
    public static function set($id, $data, $cacheLife = 0)
    {
        $id=$id.".php";
        $instance = self::getInstance();
         
        $time = time();
        $cache         = array();
        $cache['contents'] = $data;
        $cache['expire']   = $cacheLife === 0 ? 0 : $time + $cacheLife;
        $cache['mtime']    = $time;
         
        $file = $instance->_file($id);
         
        return $instance->_filePutContents($file, $cache);
    }
     
    /**
     * clear cache
     * 
     * @param string cache id    
     * @return void
     */  
    public static function delete($id)
    {
        $instance = self::getInstance();
         
        if(!$instance->has($id))
        {
            return false;
        }
        $file = $instance->_file($id);
        //del
        return unlink($file);
    }
     
    /**
     * cache has
     * 
     * @param string $id cache_id
     * @return boolean true or false
     */
    public static function has($id)
    {
        $instance = self::getInstance();
        $file     = $instance->_file($id);
         
        if(!is_file($file))
        {
            return false;
        }
        return true;
    }
     
    /**
     * get file info from id
     * @param string $id
     * @return string file
     */
    protected function _file($id)
    {
        $instance  = self::getInstance();
        $fileNmae  = $instance->_idToFileName($id);
        return $instance->_options['cache_dir'] . $fileNmae;
    }   
     
    /**
     * get filename from id
     * 
     * @param  $id
     * @return string filename
     */
    protected function _idToFileName($id)
    {
        $instance  = self::getInstance();
        $prefix    = $instance->_options['file_name_prefix'];
        return $prefix . '---' . $id;
    }
     
    /**
     * get id from filename
     * 
     * @param  $id
     * @return string id
     */
    protected function _fileNameToId($fileName)
    {
        $instance  = self::getInstance();
        $prefix    = $instance->_options['file_name_prefix'];
        return preg_replace('/^' . $prefix . '---(.*)$/', '$1', $fileName);
    }
     
    /**
     * write data
     * 
     * @param string $file filename
     * @param array  $contents data
     * @return bool 
     */
    protected function _filePutContents($file, $contents)
    {
        if($this->_options['mode'] == 1)
        {
            $contents = serialize($contents);
        }
        else
        {
            $time = time(); 
            $contents = "<?php\n".
                    " // mktime: ". $time. "\n".
                    " return ".
                    var_export($contents, true).
                    "\n?>";
        }
         
        $result = false;
        $f = @fopen($file, 'w');
        if ($f) {
            @flock($f, LOCK_EX);
            fseek($f, 0);
            ftruncate($f, 0);
            $tmp = @fwrite($f, $contents);
            if (!($tmp === false)) {
                $result = true;
            }
            @fclose($f);
        }
        @chmod($file,0777);
        return $result;             
    }
     
    /**
     * get data from file
     * 
     * @param  sring $file
     * @return boolean|array
     */
    protected function _fileGetContents($file)
    {
        if(!is_file($file))
        {
            return false;
        }
         
        if($this->_options['mode'] == 1)
        {
            $f = @fopen($file, 'r'); 
            @$data = fread($f,filesize($file));
            @fclose($f);
            return unserialize($data);
        }
        else
        {
            return include $file;
        }
    }

    protected function __construct()
    {
     
    }
     
    /**
     * set cache dir
     * 
     * @param string $path
     * @return self
     */
    public static function setCacheDir($path)
    {
        $instance  = self::getInstance();
        if (!is_dir($path)) {
            exit('file_cache: ' . $path.' invalid ');
        }
        if (!is_writable($path)) {
            exit('file_cache: dir "'.$path.'" no write');
        }
     
        $path = rtrim($path,'/') . '/';
        $instance->_options['cache_dir'] = $path;
         
        return $instance;
    }
     
    /**
     * set prefix
     * 
     * @param srting $prefix
     * @return self
     */
    public static function setCachePrefix($prefix)
    {
        $instance  = self::getInstance();
        $instance->_options['file_name_prefix'] = $prefix;
        return $instance;
    }
     
    /**
     * set mode
     * 
     * @param int $mode
     * @return self
     */
    public static function setCacheMode($mode = 1)
    {
        $instance  = self::getInstance();
        if($mode == 1)
        {
            $instance->_options['mode'] = 1;
        }
        else
        {
            $instance->_options['mode'] = 2;
        }
         
        return $instance;
    }
     
    /**
     * del all
     * @return boolean
     */
    public static function flush()
    {
        $instance  = self::getInstance();
        $glob = @glob($instance->_options['cache_dir'] . $instance->_options['file_name_prefix'] . '--*');
         
        if(empty($glob))
        {
            return false;
        }
         
        foreach ($glob as $v)
        {
            $fileName = basename($v);
            $id =  $instance->_fileNameToId($fileName);
            $instance->delete($id);
        }
        return true;
    }
}