<?php

/**
 * CwsSession
 * 
 * CwsSession is a PHP class to manipulate sessions.
 * Data are securely encrypted and sessions are stored in database.
 *
 * CwsSession is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at your option)
 * or (at your option) any later version.
 *
 * CwsSession is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public License
 * for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see http://www.gnu.org/licenses/.
 * 
 * Related post : http://goo.gl/YyNSJz
 * 
 * @package CwsSession
 * @author Cr@zy
 * @copyright 2013-2015, Cr@zy
 * @license GNU LESSER GENERAL PUBLIC LICENSE
 * @version 1.6
 * @link https://github.com/crazy-max/CwsSession
 *
 */

class CwsSession
{
    const DELIMITER = '|';
    
    const CFG_SAVE_HANDLER = 'user';
    const CFG_URL_REWRITER_TAGS = '';
    const CFG_USE_TRANS_ID = false;
    const CFG_COOKIE_HTTPONLY = true;
    const CFG_USE_ONLY_COOKIES = 1;
    const CFG_HASH_BITS_PER_CHARACTER = 6;
    
    const DB_EXT_MYSQL = 'MYSQL';
    const DB_EXT_MYSQLI = 'MYSQLI';
    const DB_EXT_PDO = 'PDO';
    
    const DB_PDO_DRIVER_FIREBIRD = 'firebird';
    const DB_PDO_DRIVER_MYSQL = 'mysql';
    const DB_PDO_DRIVER_OCI = 'oci';
    const DB_PDO_DRIVER_PGSQL = 'pgsql';
    const DB_PDO_DRIVER_SQLITE = 'sqlite';
    const DB_PDO_DRIVER_SQLITE2 =  'sqlite2';
    const DB_PDO_DRIVER_SQLSRV = 'sqlsrv';
    
    const DB_COL_ID = 'id';
    const DB_COL_ID_USER = 'id_user';
    const DB_COL_EXPIRE = 'expire';
    const DB_COL_DATA = 'data';
    const DB_COL_KEY = 'skey';
    
    const FP_PREFIX =  'CWSSESSION';
    const FP_SEPARATOR =  ';';
    const FP_MODE_BASIC = 0; // Based on HTTP_USER_AGENT
    const FP_MODE_SHIELD =  1; // Based on HTTP_USER_AGENT and IP address (may be problematic from some ISPs that use multiple IP addresses for their users)
    
    const VAR_FINGERPRINT = 'fp';
    const VAR_ID_USER = 'id_user';
    const VAR_UA = 'ua';
    const VAR_IP = 'ip';
    
    /**
     * The session life time.
     * @var int
     */
    private $lifetime;
    
    /**
     * Domain of the session cookie.
     * @var string
     */
    private $cookieDomain;
    
    /**
     * The session name references the name of the session, which is used in cookies.
     * default 'PHPSESSID'
     * @var string
     */
    private $sessionName;
    
    /**
     * Enable or disable fingerprint.
     * default true
     * @var boolean
     */
    private $fpEnable;
    
    /**
     * The fingerprint mode.
     * default FP_MODE_BASIC
     * @var int
     */
    private $fpMode;
    
    /**
     * The database PHP extension used to store sessions.
     * default DB_EXT_PDO
     * @var string
     */
    private $dbExt;
    
    /**
     * The PDO driver to use.
     * default DB_PDO_DRIVER_MYSQL
     * @var string
     */
    private $dbPdoDriver;
    
    /**
     * Can be either a host name or an IP address.
     * @var string
     */
    private $dbHost;
    
    /**
     * The database port. Leave empty if your are not sure.
     * default null
     * @var NULL|int
     */
    private $dbPort;
    
    /**
     * The database username.
     * @var string
     */
    private $dbUsername;
    
    /**
     * If not provided or NULL, the database server will attempt to authenticate the user against
     * those user records which have no password only.
     * @var string
     */
    private $dbPassword;
    
    /**
     * If provided will specify the default database to be used when performing queries.
     * @var string
     */
    private $dbName;
    
    /**
     * The database charset.
     * @var string
     */
    private $dbCharset;
    
    /**
     * The database table name to store sessions.
     * default 'sessions'
     * @var string
     */
    private $dbTableName;
    
    /**
     * Contains the database object after the database connection has been established.
     * @var object
     */
    private $db;
    
    /**
     * Represents a prepared PDO statement and, after the statement is executed, an associated result set.
     * @var object
     */
    private $stmt;
    
    /**
     * The last error.
     * @var string
     */
    private $error;
    
    /**
     * The cws debug instance.
     * @var CwsDebug
     */
    private $cwsDebug;
    
    /**
     * The cws crypto instance.
     * @var CwsCrypto
     */
    private $cwsCrypto;
    
    public function __construct(CwsDebug $cwsDebug, CwsCrypto $cwsCrypto)
    {
        $this->cwsDebug = $cwsDebug;
        $this->cwsCrypto = $cwsCrypto;
        
        $this->sessionName = 'PHPSESSID';
        
        $this->fpEnable = true;
        $this->fpMode = self::FP_MODE_BASIC;
        
        $this->dbExt = self::DB_EXT_PDO;
        $this->dbPort = null;
        $this->dbPdoDriver = self::DB_PDO_DRIVER_MYSQL;
        $this->dbTableName = 'sessions';
    }
    
    /**
     * Start the process.
     * @return boolean
     */
    public function process()
    {
        if (empty($this->cookieDomain)) {
            $this->error = 'Cookie domain empty...';
            $this->cwsDebug->error($this->error);
            return;
        }
        
        if (empty($this->dbTableName)) {
            $this->error = 'Database table name empty...';
            $this->cwsDebug->error($this->error);
            return;
        }
        
        if (empty($this->dbExt)) {
            $this->error = 'Database extension empty...';
            $this->cwsDebug->error($this->error);
            return;
        }
        
        if ($this->dbConnect()) {
            session_name($this->sessionName);
            
            session_set_save_handler(
                array(&$this, '_open'),
                array(&$this, '_close'),
                array(&$this, '_read'),
                array(&$this, '_write'),
                array(&$this, '_destroy'),
                array(&$this, '_gc')
            );
            
            register_shutdown_function(array($this, '_write_close'));
            
            $this->start();
            $this->_gc();
        }
        
        return;
    }
    
    /**
     * Works like a constructor in classes and is executed when the session is being opened.
     * It is the first callback function executed when the session is started automatically or manually with session_start().
     * @return boolean
     */
    public function _open()
    {
        $this->cwsDebug->titleH2('Open');
        return true;
    }
    
    /**
     * Works like a destructor in classes and is executed after the session write callback has been called.
     * It is also invoked when session_write_close() is called.
     * @return boolean
     */
    public function _close()
    {
        $this->cwsDebug->titleH2('Close');
        return true;
    }
    
    /**
     * Called internally by PHP when the session starts or when session_start() is called.
     * Before this callback is invoked PHP will invoke the open callback.
     * @param string $id : session id
     * @return string : session encoded (serialized)
     */
    public function _read($id)
    {
        $this->cwsDebug->titleH2('Read');
        $this->cwsDebug->labelValue('ID', $id);
        
        $encData = $this->dbSelectSingle(self::DB_COL_DATA, $id);
        if (!empty($encData)) {
            $this->cwsCrypto->setEncryptionKey($this->retrieveKey($id));
            $data = $this->cwsCrypto->decrypt(base64_decode($encData));
            $this->cwsDebug->dump('Encrypted data', $encData);
            $this->cwsDebug->dump('Decrypted data', $data);
    
            if (!empty($data)) {
                $exData = self::decode($data);
                $this->cwsDebug->dump('Unserialized data', $exData);
                return $data;
            }
        }
    
        return "";
    }
    
    /**
     * Called when the session needs to be saved and closed.
     * Callback is invoked when PHP shuts down or explicitly when session_write_close() is called.
     * Note that after executing this function PHP will internally execute the close callback. 
     * @param string $id : session id
     * @param string $data : session encoded (serialized)
     * @return boolean
     */
    public function _write($id, $data)
    {
        $this->cwsDebug->titleH2('Write');
        
        $unsData = self::decode($data);
        $idUser = 0;
        if (!empty($unsData) && isset($unsData[self::VAR_ID_USER])) {
            $idUser = intval($unsData[self::VAR_ID_USER]);
        }
    
        $key = $this->retrieveKey($id);
        $this->cwsCrypto->setEncryptionKey($key);
        $encData = base64_encode($this->cwsCrypto->encrypt($data));
        $expire = intval(time() + $this->lifetime);
    
        $this->cwsDebug->labelValue('ID', $id);
        $this->cwsDebug->labelValue('ID user', $idUser);
        $this->cwsDebug->labelValue('Expire', $expire);
        
        $this->cwsDebug->dump('Data', $data);
        $this->cwsDebug->dump('Encrypted data', $encData);
        
        return $this->dbReplaceInto(array($id, $idUser, $expire, $encData, $key));
    }
    
    /**
     * Executed when a session is destroyed with session_destroy() or with session_regenerate_id()
     * @param string $id : session id
     * @return boolean
     */
    public function _destroy($id)
    {
        $this->cwsDebug->titleH2('Destroy');
        $this->cwsDebug->labelValue('ID', $id);
        return $this->dbDelete(self::DB_COL_ID, '=', $id);
    }
    
    /**
     * The garbage collector callback is invoked internally by PHP periodically in order to purge old session data
     * @return boolean
     */
    public function _gc()
    {
        $this->cwsDebug->titleH2('Garbage collector');
        $result = $this->dbDelete(self::DB_COL_EXPIRE , '<', time());
        
        if ($result) {
            $affected = 0;
            if ($this->dbExt == self::DB_EXT_MYSQL) {
                $affected = mysql_affected_rows($this->db);
            } elseif ($this->dbExt == self::DB_EXT_MYSQLI) {
                $affected = mysqli_affected_rows($this->db);
            } elseif ($this->dbExt == self::DB_EXT_PDO) {
                $affected = $this->stmt->rowCount();
            }
            $this->cwsDebug->labelValue('Destroyed', $affected);
        }
        
        return $result;
    }
    
    /**
     * Prevents unexpected effects when using objects as save handlers.
     */
    public function _write_close()
    {
        $this->cwsDebug->titleH2('Write close');
    
        session_write_close();
        if ($this->dbExt == self::DB_EXT_MYSQL) {
            mysql_close($this->db);
        } elseif ($this->dbExt == self::DB_EXT_MYSQLI) {
            mysqli_close($this->db);
        }
        
        $this->db = null;
        $this->stmt = null;
    }
    
    /**
     * To call everytime you want to start a new session instead of session_start
     */
    public function start()
    {
        $this->cwsDebug->titleH2('Start');
        
        // defines the name of the handler which is used for storing and retrieving data associated with a session
        // default 'files'
        ini_set('session.save_handler', self::CFG_SAVE_HANDLER);
        
        // specifies which HTML tags are rewritten to include session id if transparent sid support is enabled
        // default 'a=href,area=href,frame=src,input=src,form=fakeentry,fieldset='
        ini_set('session.url_rewriter.tags', self::CFG_URL_REWRITER_TAGS);
        
        // transparent sid support is enabled or not
        ini_set('session.use_trans_sid', self::CFG_USE_TRANS_ID);
        
        // marks the cookie as accessible only through the HTTP protocol
        ini_set('session.cookie_httponly', self::CFG_COOKIE_HTTPONLY);
        
        // specifies whether the module will only use cookies to store the session id on the client side
        ini_set('session.use_only_cookies', self::CFG_USE_ONLY_COOKIES);
        
        // domain to set in the session cookie
        ini_set('session.cookie_domain', $this->cookieDomain);
        
        // specify any of the algorithms provided by hash_algos() function
        ini_set('session.hash_function', CwsCrypto::PBKDF2_ALGORITHM);
        
        // define how many bits are stored in each character when converting the binary hash data
        // to something readable. The possible values are '4' (0-9, a-f), '5' (0-9, a-v),
        // and '6' (0-9, a-z, A-Z, "-", ",").
        ini_set('session.hash_bits_per_character', self::CFG_HASH_BITS_PER_CHARACTER);
        
        // session cookie parameters
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params(
            $this->lifetime,
            $cookieParams['path'],
            $cookieParams['domain'],
            isset($_SERVER['HTTPS']),
            self::CFG_COOKIE_HTTPONLY
        );
        
        $this->cwsDebug->dump('Cookie params', session_get_cookie_params());
        
        // start session
        @session_start();
        
        if (!$this->checkFingerprint()) {
            $this->regenerate();
            $_SESSION = array();
        }
        
        $this->update();
    }
    
    /**
     * Regenerates the session and delete the old one.
     * It also generates a new encryption key in the database.
     * To use each time a user connects to your application successfully.
     */
    public function regenerate()
    {
        session_name($this->sessionName);
        $oldId = session_id();
        session_regenerate_id(true);
        
        $this->cwsDebug->titleH2('Regenerate ID');
        $this->cwsDebug->labelValue('Current', $oldId);
        $this->cwsDebug->labelValue('New', session_id());
    }
    
    /**
     * Update specific session vars (user agent, IP address, fingerprint).
     */
    public function update()
    {
        $this->cwsDebug->titleH2('Update');
        
        $_SESSION[self::VAR_UA] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $this->cwsDebug->labelValue('$_SESSION[\'' . self::VAR_UA . '\']', $_SESSION[self::VAR_UA]);
        
        $_SESSION[self::VAR_IP] = self::getIpAddress();
        $this->cwsDebug->labelValue('$_SESSION[\'' . self::VAR_IP . '\']', $_SESSION[self::VAR_IP]);
        
        if ($this->fpEnable) {
            $_SESSION[self::VAR_FINGERPRINT] = $this->retrieveFingerprint();
            $this->cwsDebug->labelValue('$_SESSION[\'' . self::VAR_FINGERPRINT . '\']', $_SESSION[self::VAR_FINGERPRINT]);
        }
    }
    
    /**
     * Check if the session is active or not.
     * @return boolean
     */
    public function isActive()
    {
        $id = session_id();
        $expire = $this->dbSelectSingle(self::DB_COL_EXPIRE, $id);
        $current = time();
        
        $this->cwsDebug->titleH2('Active');
        $this->cwsDebug->labelValue('Current time', $current);
        $this->cwsDebug->labelValue('Expire', $expire);
        
        if ($expire > $current) {
            $this->cwsDebug->simple('Session <strong>' . $id . '</strong> active!');
            return true;
        } else {
            $this->cwsDebug->simple('Session <strong>' . $id . '</strong> not active..');
            session_destroy();
            return false;
        }
    }
    
    /**
     * Connect to database.
     * @return boolean
     */
    private function dbConnect()
    {
        if (!in_array($this->dbExt, self::getDbExts())) {
            $this->error = 'Database extension unknown... Selected : ' . $this->dbExt;
            $this->cwsDebug->error($this->error);
        } elseif (empty($this->dbHost)) {
            $this->error = 'Database host empty...';
            $this->cwsDebug->error($this->error);
            return false;
        } elseif (empty($this->dbName)) {
            $this->error = 'Database name empty...';
            $this->cwsDebug->error($this->error);
            return false;
        } elseif (empty($this->dbUsername)) {
            $this->error = 'Database username empty...';
            $this->cwsDebug->error($this->error);
            return false;
        }
        
        if ($this->dbExt == self::DB_EXT_MYSQL) {
            return $this->dbConnectMysql();
        } elseif ($this->dbExt == self::DB_EXT_MYSQLI) {
            return $this->dbConnectMysqli();
        } elseif ($this->dbExt == self::DB_EXT_PDO) {
            return $this->dbConnectPdo();
        }
        
        return false;
    }
    
    /**
     * Connect to the database with mysql extension.
     * @return boolean
     */
    private function dbConnectMysql()
    {
        if (!function_exists('mysql_connect')) {
            $this->error = self::DB_EXT_MYSQL . ' - Extension not loaded. Check your PHP configuration...';
            $this->cwsDebug->error($this->error);
            return false;
        }
        
        $this->db = mysql_connect($this->dbHost, $this->dbUsername, $this->dbPassword, true);
        
        $selectDb = false;
        if ($this->db !== false) {
            $selectDb = mysql_select_db($this->dbName, $this->db);
        }
        
        if ($selectDb) {
            if (!empty($this->dbCharset)) {
                if (!$this->dbQuery("SET NAMES '" . $this->dbCharset . "'")) {
                    $this->error = self::DB_EXT_MYSQL . ' - Error loading character set ' . $this->dbCharset . ': ' . mysql_error($this->db);
                    $this->cwsDebug->error($this->error);
                    return false;
                }
            }
        } else {
            $this->error = self::DB_EXT_MYSQL . ' - ' . mysql_error($this->db);
            $this->cwsDebug->error($this->error);
            return false;
        }
        
        return true;
    }
    
    /**
     * Connect to the database with mysqli extension.
     * @return boolean
     */
    private function dbConnectMysqli()
    {
        if (!function_exists('mysqli_connect')) {
            $this->error = self::DB_EXT_MYSQLI . ' - Extension not loaded. Check your PHP configuration...';
            $this->cwsDebug->error($this->error);
            return false;
        }
        
        $this->db = mysqli_connect($this->dbHost, $this->dbUsername, $this->dbPassword, $this->dbName);
        
        if (mysqli_connect_errno()) {
            $this->error = self::DB_EXT_MYSQLI . ' - ' . mysqli_connect_errno();
            $this->cwsDebug->error($this->error);
            return false;
        }
        
        if (!empty($this->dbCharset)) {
            if (!mysqli_set_charset($this->db, $this->dbCharset)) {
                $this->error = self::DB_EXT_MYSQLI . ' - Error loading character set ' . $this->dbCharset . ': ' . mysqli_error($this->db);
                $this->cwsDebug->error($this->error);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Connect to the database with PDO extension.
     * @return boolean
     */
    private function dbConnectPdo()
    {
        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            $this->error = self::DB_EXT_PDO . ' - Extension not loaded. Check your PHP configuration...';
            $this->cwsDebug->error($this->error);
            return false;
        } elseif (!$this->isValidPdoDriver()) {
            $this->error = self::DB_EXT_PDO . ' - The database you wish to connect to is not supported by your install of PHP. ';
            $this->error .= 'Check your PDO driver (selected: ' . $this->dbPdoDriver . ')...';
            $this->cwsDebug->error($this->error);
            return false;
        }
        
        // Set DSN
        $dsn = $this->dbPdoDriver . ':';
        if ($this->dbPdoDriver == self::DB_PDO_DRIVER_SQLITE || $this->dbPdoDriver == self::DB_PDO_DRIVER_SQLITE2) {
            $dsn .= $this->dbHost;
        } elseif ($this->dbPdoDriver == self::DB_PDO_DRIVER_SQLSRV) {
            $this->dbPort = !is_null($this->dbPort) ? ',' . $this->dbPort : '';
            $dsn .= 'Server=' . $this->dbHost . $this->dbPort . ';Database=' . $this->dbName;
        } elseif ($this->dbPdoDriver == self::DB_PDO_DRIVER_FIREBIRD || $this->dbPdoDriver == self::DB_PDO_DRIVER_OCI) {
            $this->dbPort = !is_null($this->dbPort) ? ':' . $this->dbPort : '';
            $dsn .= 'dbname=//' . $this->dbHost . $this->dbPort . '/' . $this->dbName;
        } else {
            $this->dbPort = !is_null($this->dbPort) ? ';port=' . $this->dbPort : '';
            $dsn .= 'host=' . $this->dbHost . $this->dbPort . ';dbname=' . $this->dbName;
        }
        
        // Set options
        $options[PDO::ATTR_PERSISTENT] = true;
        $options[PDO::ATTR_ERRMODE] = true;
        if (!empty($this->dbCharset)) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES '" . $this->dbCharset . "'";
        }
        
        try {
            $this->db = new PDO($dsn, $this->dbUsername, $this->dbPassword, $options);
            return true;
        } catch (PDOException $e) {
            $this->error = self::DB_EXT_PDO . ' - ' . $e->getMessage();
            $this->cwsDebug->error($this->error);
            return false;
        } catch(Exception $e) {
            $this->error = self::DB_EXT_PDO . ' - ' . $e->getMessage();
            $this->cwsDebug->error($this->error);
            return false;
        }
    }
    
    /**
     * Select a single value from a specified column filtered by session id.
     * @param string $column - The column to select
     * @param string $idFilter - The session id to be filtered.
     * @return string|unknown|NULL
     */
    private function dbSelectSingle($column, $idFilter)
    {
        $query = 'SELECT `' . $this->dbEscapeString($column) . '` ';
        $query .= 'FROM `' . $this->dbEscapeString($this->dbTableName) . '` ';
        $query .= 'WHERE `' . self::DB_COL_ID . '` = "' . $this->dbEscapeString($idFilter) . '" LIMIT 1';
        
        $result = $this->dbQuery($query);
        if ($result !== false) {
            if ($this->dbExt == self::DB_EXT_MYSQL) {
                $numRows = mysql_num_rows($result);
                if ($numRows == 1) {
                    return mysql_result($result, 0);
                }
            } elseif ($this->dbExt == self::DB_EXT_MYSQLI) {
                $numRows = mysqli_num_rows($result);
                if ($numRows == 1) {
                    $fetch = mysqli_fetch_assoc($result);
                    return $fetch[$column];
                }
            } elseif ($this->dbExt == self::DB_EXT_PDO) {
                $numRows = $this->stmt->rowCount();
                if ($numRows == 1) {
                    return $this->stmt->fetchColumn(0);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Delete a row based on column / value.
     * @param string $column
     * @param string $ope
     * @param string $value
     * @return boolean
     */
    private function dbDelete($column, $operand, $value)
    {
        $query = 'DELETE FROM `' . $this->dbEscapeString($this->dbTableName) . '` WHERE ';
        $query .= '`' . $this->dbEscapeString($column) . '` ' . $operand . ' "' . $this->dbEscapeString($value) . '"';
        return $this->dbQuery($query);
    }
    
    /**
     * Insert/update session values.
     * @param array $values
     * @return boolean
     */
    private function dbReplaceInto($values)
    {
        $query = '';
        
        foreach ($values as $value) {
            $query .= empty($query) ? 'REPLACE INTO `' . $this->dbEscapeString($this->dbTableName) . '` VALUES (' : ', ';
            $query .= '"' . $this->dbEscapeString($value) . '"';
        }
        
        $query .= ')';
    
        return $this->dbQuery($query);
    }
    
    /**
     * Escapes special characters.
     * @param string $string
     * @return string - The escaped string.
     */
    private function dbEscapeString($string)
    {
        if ($this->dbExt == self::DB_EXT_MYSQL) {
            return mysql_real_escape_string($string, $this->db);
        } elseif ($this->dbExt == self::DB_EXT_MYSQLI) {
            return $this->db->real_escape_string($string);
        } elseif ($this->dbExt == self::DB_EXT_PDO) {
            return $string;
        }
    }
    
    /**
     * Sends a query to the currently active database.
     * @param string $query
     * @return boolean|resource.
     */
    private function dbQuery($query)
    {
        $result = false;
        $query = trim($query);
        
        if ($this->dbExt == self::DB_EXT_MYSQL) {
            $result = mysql_query($query, $this->db);
            if (!$result) {
                $this->error = self::DB_EXT_MYSQL . ' - ' . mysql_error($this->db);
                $this->cwsDebug->error($this->error);
            }
        } elseif ($this->dbExt == self::DB_EXT_MYSQLI) {
            $result = mysqli_query($this->db, $query);
            if (!$result) {
                $this->error = self::DB_EXT_MYSQLI . ' - ' . mysqli_error($this->db);
                $this->cwsDebug->error($this->error);
            }
        } elseif ($this->dbExt == self::DB_EXT_PDO) {
            try {
                $this->stmt = $this->db->prepare($query);
                $result = $this->stmt->execute();
            } catch (PDOException $e) {
                $this->error = self::DB_EXT_PDO . ' - ' . $e->getMessage();
                $this->cwsDebug->error($this->error);
            } catch(Exception $e) {
                $this->error = self::DB_EXT_PDO . ' - ' . $e->getMessage();
                $this->cwsDebug->error($this->error);
            }
        }
        
        return $result;
    }
    
    /**
     * Validate the database in question is supported by the installation of PHP.
     * @return boolean - true, the database is supported ; false, the database is not supported.
     */
    private function isValidPdoDriver()
    {
        return in_array($this->dbPdoDriver, PDO::getAvailableDrivers());
    }
    
    /**
     * Check fingerprint based on user agent and/or IP address.
     * @return boolean
     */
    private function checkFingerprint()
    {
        $this->cwsDebug->titleH3('Check fingerprint');
        
        if ($this->fpEnable) {
            $fingerprint = $this->retrieveFingerprint();
            if (empty($fingerprint)) {
                $this->error = 'Can\'t generate fingerprint...';
                $this->cwsDebug->error($this->error);
                return false;
            }
            if (!isset($_SESSION[self::VAR_FINGERPRINT])) {
                $this->error = 'Fingerprint not setted...';
                $this->cwsDebug->error($this->error);
                return false;
            }
            if ($fingerprint != $_SESSION[self::VAR_FINGERPRINT]) {
                $this->error = 'Fingerprint error... Has <strong>' . $fingerprint . ' but expected <strong>' . $_SESSION[self::VAR_FINGERPRINT] . '</strong>';
                $this->cwsDebug->error($this->error);
                return false;
            }
            $this->cwsDebug->simple('Fingerprint OK : ' . $_SESSION[self::VAR_FINGERPRINT]);
        } else {
            $this->cwsDebug->simple('Fingerprint check disabled...');
        }
        
        return true;
    }
    
    /**
     * Generate a fingerprint based on user agent and/or IP address.
     * @return string
     */
    private function retrieveFingerprint()
    {
        $fingerprint = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if ($this->fpMode == self::FP_MODE_SHIELD) {
            $fingerprint .= self::FP_SEPARATOR . self::getIpAddress();
        }
        if (!empty($fingerprint)) {
            return sha1(self::FP_PREFIX . self::FP_SEPARATOR . $fingerprint);
        }
        return null;
    }
    
    /**
     * Get the unique key for encryption from the sessions table.
     * @param string $id - The session id.
     * @return string
     */
    private function retrieveKey($id)
    {
        $key = $this->dbSelectSingle(self::DB_COL_KEY, $id);
        if (!empty($key)) {
            $this->cwsDebug->labelValue('Key retrieved from database', htmlentities($key));
            return $key;
        } else {
            $key = CwsCrypto::random(56);
            $this->cwsDebug->labelValue('Generated random key', htmlentities($key));
            return $key;
        }
    }
    
    /**
     * Database extensions.
     * @return array
     */
    private static function getDbExts()
    {
        return array(
            self::DB_EXT_MYSQL,
            self::DB_EXT_MYSQLI,
            self::DB_EXT_PDO
        );
    }
    
    /**
     * Get the user IP address.
     * @return string
     */
    private static function getIpAddress()
    {
        if ($_SERVER) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip_addr = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip_addr = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $ip_addr = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $ip_addr = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_CLIENT_IP')) {
                $ip_addr = getenv('HTTP_CLIENT_IP');
            } else {
                $ip_addr = getenv('REMOTE_ADDR');
            }
        }
        
        if (filter_var($ip_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                || filter_var($ip_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip_addr;
        }
        
        return null;
    }
    
    /**
     * Decode session data.
     * @param string $data - Encoded session data.
     * @param number $sindex - Index of the current value to decode.
     * @param array $result - Decoded session data.
     */
    private static function decode($data, $sindex=0, &$result=array()) {
        $eindex = strpos($data, self::DELIMITER, $sindex);
        if ($eindex !== false) {
            $name = substr($data, $sindex, $eindex - $sindex);
            $rest = substr($data, $eindex + 1);
            $value = unserialize($rest);
            $result[$name] = $value;
            return self::decode($data, $eindex + 1 + strlen(serialize($value)), $result);
        }
        return $result;
    }
    
    /**
     * Getters and setters
     */

    /**
     * The session life time.
     * @return the $lifetime
     */
    public function getLifetime()
    {
        return $this->lifetime;
    }
    
    /**
     * Set the session life time.
     * @param int $lifetime
     */
    public function setLifetime($lifetime)
    {
        return $this->lifetime = $lifetime;
    }
    
    /**
     * The domain of the session cookie.
     * @return the $cookieDomain
     */
    public function getCookieDomain()
    {
        return $this->cookieDomain;
    }
    
    /**
     * Set the domain of the session cookie.
     * @param string $cookieDomain
     */
    public function setCookieDomain($cookieDomain)
    {
        $this->cookieDomain = $cookieDomain;
    }
    
    /**
     * The session name.
     * @return the $sessionName
     */
    public function getSessionName()
    {
        return $this->sessionName;
    }
    
    /**
     * Set the session name.
     * default 'PHPSESSID'
     * @param string $sessionName
     */
    public function setSessionName($sessionName)
    {
        $this->sessionName = $sessionName;
    }
    
    /**
     * The fingerprint status.
     * @return the $fpEnable
     */
    public function isFpEnable()
    {
        return $this->fpEnable;
    }
    
    /**
     * Enable/disable fingerprint.
     * default true
     * @param boolean $fpEnable
     */
    public function setFpEnable($fpEnable)
    {
        $this->fpEnable = $fpEnable;
    }
    
    /**
     * The fingerprint mode.
     * @return the $fpMode
     */
    public function getFpMode()
    {
        return $this->fpMode;
    }
    
    /**
     * Set the fingerprint mode basic.
     * default
     */
    public function setFpModeBasic()
    {
        $this->setFpMode(self::FP_MODE_BASIC);
    }
    
    /**
     * Set the fingerprint mode shield.
     */
    public function setFpModeShield()
    {
        $this->setFpMode(self::FP_MODE_SHIELD);
    }
    
    /**
     * Set the fingerprint mode.
     * @param number $fpMode
     */
    private function setFpMode($fpMode)
    {
        $this->fpMode = $fpMode;
    }
    
    /**
     * The database PHP extension used to store sessions.
     * @return the $dbExt
     */
    public function getDbExt()
    {
        return $this->dbExt;
    }
    
    /**
     * Set the database PHP extension used to store sessions to mysql.
     */
    public function setDbExtMysql()
    {
        $this->setDbExt(self::DB_EXT_MYSQL);
    }
    
    /**
     * Set the database PHP extension used to store sessions to mysqli.
     */
    public function setDbExtMysqli()
    {
        $this->setDbExt(self::DB_EXT_MYSQLI);
    }
    
    /**
     * Set the database PHP extension used to store sessions to pdo.
     * default
     */
    public function setDbExtPdo()
    {
        $this->setDbExt(self::DB_EXT_PDO);
    }
    
    /**
     * Set the database PHP extension used to store sessions.
     * @param string $dbExt
     */
    private function setDbExt($dbExt)
    {
        $this->dbExt = $dbExt;
    }
    
    /**
     * The PDO driver to use. (if db extension is Pdo)
     * @return the $dbPdoDriver
     */
    public function getDbPdoDriver()
    {
        return $this->dbPdoDriver;
    }
    
    /**
     * Set the PDO driver to firebird.
     */
    public function setDbPdoDriverFirebird()
    {
        $this->setDbPdoDriver(self::DB_PDO_DRIVER_FIREBIRD);
    }
    
    /**
     * Set the PDO driver to mysql.
     * default
     */
    public function setDbPdoDriverMysql()
    {
        $this->setDbPdoDriver(self::DB_PDO_DRIVER_MYSQL);
    }
    
    /**
     * Set the PDO driver to oci.
     */
    public function setDbPdoDriverOci()
    {
        $this->setDbPdoDriver(self::DB_PDO_DRIVER_OCI);
    }
    
    /**
     * Set the PDO driver to pgsql.
     */
    public function setDbPdoDriverPgsql()
    {
        $this->setDbPdoDriver(self::DB_PDO_DRIVER_PGSQL);
    }
    
    /**
     * Set the PDO driver to sqlite.
     */
    public function setDbPdoDriverSqlite()
    {
        $this->setDbPdoDriver(self::DB_PDO_DRIVER_SQLITE);
    }
    
    /**
     * Set the PDO driver to sqlite2.
     */
    public function setDbPdoDriverSqlite2()
    {
        $this->setDbPdoDriver(self::DB_PDO_DRIVER_SQLITE2);
    }
    
    /**
     * Set the PDO driver to sqlsrv.
     */
    public function setDbPdoDriverSqlsrv()
    {
        $this->setDbPdoDriver(self::DB_PDO_DRIVER_SQLSRV);
    }
    
    /**
     * Set the PDO driver to use. (if db extension is Pdo)
     * @param string $dbPdoDriver
     */
    private function setDbPdoDriver($dbPdoDriver)
    {
        $this->dbPdoDriver = $dbPdoDriver;
    }
    
    /**
     * The database host name or IP address.
     * @return the $dbHost
     */
    public function getDbHost()
    {
        return $this->dbHost;
    }
    
    /**
     * Set the database host name or IP address.
     * @param string $dbHost
     */
    public function setDbHost($dbHost)
    {
        $this->dbHost = $dbHost;
    }
    
    /**
     * The database port.
     * @return the $dbPort
     */
    public function getDbPort()
    {
        return $this->dbPort;
    }
    
    /**
     * Set the database port. Leave empty if your are not sure.
     * @param int $dbPort
     */
    public function setDbPort($dbPort)
    {
        $this->dbPort = $dbPort;
    }
    
    /**
     * Set the database username.
     * @param string $dbUsername
     */
    public function setDbUsername($dbUsername)
    {
        $this->dbUsername = $dbUsername;
    }
    
    /**
     * Set the database password.
     * If not provided or NULL, the database server will attempt to authenticate the user against
     * those user records which have no password only.
     * @param string $dbPassword
     */
    public function setDbPassword($dbPassword)
    {
        $this->dbPassword = $dbPassword;
    }
    
    /**
     * The database name.
     * @return the $dbName
     */
    public function getDbName()
    {
        return $this->dbName;
    }
    
    /**
     * Set the database name.
     * @param string $dbName
     */
    public function setDbName($dbName)
    {
        $this->dbName = $dbName;
    }
    
    /**
     * The database charset.
     * @return the $dbCharset
     */
    public function getDbCharset()
    {
        return $this->dbCharset;
    }
    
    /**
     * Set the database charset. Leave empty if your are not sure.
     * @param string $dbCharset
     */
    public function setDbCharset($dbCharset)
    {
        $this->dbCharset = $dbCharset;
    }
    
    /**
     * The database table name to store sessions.
     * @return the $dbTableName
     */
    public function getDbTableName()
    {
        return $this->dbTableName;
    }
    
    /**
     * Set the database table name to store sessions.
     * default 'sessions'
     * @param string $dbTableName
     */
    public function setDbTableName($dbTableName)
    {
        $this->dbTableName = $dbTableName;
    }
    
    /**
     * The fingerprint SESSION value
     */
    public function getParamFp()
    {
        return $this->getParam(self::VAR_FINGERPRINT);
    }
    
    /**
     * The user id SESSION value
     */
    public function getParamUserId()
    {
        return $this->getParam(self::VAR_ID_USER);
    }
    
    /**
     * The user agent SESSION value
     */
    public function getParamUa()
    {
        return $this->getParam(self::VAR_UA);
    }
    
    /**
     * The ip address SESSION value
     */
    public function getParamIp()
    {
        return $this->getParam(self::VAR_IP);
    }
    
    /**
     * A SESSION value
     * @param string $key
     */
    public function getParam($key)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    /**
     * Set id_user SESSION value
     */
    public function setParamUserId($value)
    {
        return $this->setParam(self::VAR_ID_USER, $value);
    }
    
    /**
     * Set a SESSION key/value
     * @param string $key
     * @param string $value
     */
    public function setParam($key, $value)
    {
        $_SESSION[$key] = $value;
    }
    
    /**
     * The last error.
     * @return the $error
     */
    public function getError() {
        return $this->error;
    }
}
