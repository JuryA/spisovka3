<?php

class UserSettings
{
    const TABLE_NAME = 'user_settings';

    protected static $instance = null;

    protected static function _getInstance() {
    
        if (self::$instance === null)
            self::$instance = new self;
        return self::$instance;
    }

    public static function get($key, $default = null) {
    
        $i = self::_getInstance();
        return $i->_get($key, $default);
    }

    public static function set($key, $value) {
    
        $i = self::_getInstance();
        $i->_set($key, $value);
    }

    public static function remove($key) {
    
        $i = self::_getInstance();
        $i->_set($key, null);
    }
    
    // ------------------------------------------------------------

    protected $user_id;
    protected $settings = array();
    protected $table_prefix;
   
    protected function __construct() {
    
        $this->user_id = Environment::getUser()->getIdentity()->id;
        $this->table_prefix = BaseModel::getDbPrefix();
        
        $result = dibi::query('SELECT [settings] FROM %n', $this->table_prefix . self::TABLE_NAME, 'WHERE [id] = %i', $this->user_id);
        if (count($result) > 0) {
            $value = unserialize($result->fetchSingle());
            if ($value !== false)
                $this->settings = $value;
        }
        else
            dibi::query('INSERT INTO %n', $this->table_prefix . self::TABLE_NAME,
                'VALUES (%i, %s)', $this->user_id, serialize(array()));
    }
        
    protected function _get($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    protected function _set($key, $value) {
    
        if ($value === null)
            unset($this->settings[$key]);
        else
            $this->settings[$key] = $value;
        $this->_flush();
    }
    
    protected function _flush() {
    
        dibi::query('UPDATE %n', $this->table_prefix . self::TABLE_NAME, 'SET [settings] = %s',
            serialize($this->settings), 'WHERE [id] = %i', $this->user_id);
    }
}

?>