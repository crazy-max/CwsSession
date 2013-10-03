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
 * @copyright 2013, Cr@zy
 * @license GNU LESSER GENERAL PUBLIC LICENSE
 * @version 1.3
 *
 */

define('CWSSESSION_DELIMITER',                   '|');

define('CWSSESSION_CFG_SAVE_HANDLER',            'user');
define('CWSSESSION_CFG_URL_REWRITER_TAGS',       '');
define('CWSSESSION_CFG_USE_TRANS_ID',            false);
define('CWSSESSION_CFG_COOKIE_HTTPONLY',         true);
define('CWSSESSION_CFG_USE_ONLY_COOKIES',        1);
define('CWSSESSION_CFG_HASH_BITS_PER_CHARACTER', 6);

define('CWSSESSION_DBEXT_MYSQL',                 'MYSQL');
define('CWSSESSION_DBEXT_MYSQLI',                'MYSQLI');
define('CWSSESSION_DBEXT_PDO',                   'PDO');

define('CWSSESSION_DBDRIVER_FIREBIRD',           'firebird');
define('CWSSESSION_DBDRIVER_MYSQL',              'mysql');
define('CWSSESSION_DBDRIVER_OCI',                'oci');
define('CWSSESSION_DBDRIVER_PGSQL',              'pgsql');
define('CWSSESSION_DBDRIVER_SQLITE',             'sqlite');
define('CWSSESSION_DBDRIVER_SQLITE2',            'sqlite2');
define('CWSSESSION_DBDRIVER_SQLSRV',             'sqlsrv');

define('CWSSESSION_DBCOL_ID',                    'id');
define('CWSSESSION_DBCOL_ID_USER',               'id_user');
define('CWSSESSION_DBCOL_EXPIRE',                'expire');
define('CWSSESSION_DBCOL_DATA',                  'data');
define('CWSSESSION_DBCOL_KEY',                   'skey');

define('CWSSESSION_FP_PREFIX',                   'CWSSESSION');
define('CWSSESSION_FP_SEPARATOR',                ';');
define('CWSSESSION_FP_MODE_BASIC',               0); // Based on HTTP_USER_AGENT
define('CWSSESSION_FP_MODE_SHIELD',              1); // Based on HTTP_USER_AGENT and IP address (may be problematic from some ISPs that use multiple IP addresses for their users)

define('CWSSESSION_VAR_FINGERPRINT',             'fp');
define('CWSSESSION_VAR_ID_USER',                 'id_user');
define('CWSSESSION_VAR_UA',                      'ua');
define('CWSSESSION_VAR_IP',                      'ip');

class CwsSession
{
    /**
     * Control the debug output. (see CwsDebug class)
     * @var int
     */
    private $debugVerbose = false;
    
    /**
     * The debug output mode. (see CwsDebug class)
     * default CWSDEBUG_MODE_ECHO
     * @var int
     */
    private $debugMode = false;
    
    /**
     * The debug file path in CWSDEBUG_MODE_FILE mode. (see CwsDebug class)
     * default './cwssession-debug.html'
     * @var string
     */
    private $debugFilePath = './cwssession-debug.html';
    
    /**
     * The last error message.
     * @var string
     */
    private $errorMsg;
    
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
    private $sessionName = 'PHPSESSID';
    
    /**
     * Enable or disable fingerprint.
     * default true
     * @var boolean
     */
    private $fpEnable = true;
    
    /**
     * The fingerprint mode.
     * default CWSSESSION_FP_MODE_BASIC
     * @var int
     */
    private $fpMode = CWSSESSION_FP_MODE_BASIC;
    
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
     * The database PHP extension used to store sessions.
     * default CWSSESSION_DBEXT_PDO
     * @var string
     */
    private $dbExt = CWSSESSION_DBEXT_PDO;
    
    /**
     * Can be either a host name or an IP address.
     * @var string
     */
    private $dbHost;
    
    /**
     * The database port. Leave empty if your are not sure.
     * default null
     * @var NULL|number
     */
    private $dbPort = null;
    
    /**
     * The database user name.
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
     * The PDO driver to use.
     * default CWSSESSION_DBDRIVER_MYSQL
     * @var string
     */
    private $dbPdoDriver = CWSSESSION_DBDRIVER_MYSQL;
    
    /**
     * The database charset.
     * @var string
     */
    private $dbCharset;
    
    /**
     * The database table name to store sessions.
     * @var string
     */
    private $dbTableName;
    
    public function __construct() {}
    
    /**
     * Start the process.
     * @return boolean
     */
    public function process()
    {
        if (!class_exists('CwsDebug')) {
            $this->errorMsg = 'CwsDebug is required - https://github.com/crazy-max/CwsDebug';
            echo $this->errorMsg;
            return;
        }
        
        global $cwsDebug;
        $cwsDebug = new CwsDebug();
        $cwsDebug->setVerbose($this->debugVerbose);
        $cwsDebug->setMode($this->debugMode, $this->debugFilePath);
        
        if (!class_exists('CwsCrypto')) {
            $this->errorMsg = 'CwsCrypto is required - https://github.com/crazy-max/CwsCrypto';
            $cwsDebug->error($this->errorMsg);
            return;
        } else {
            global $cwsCrypto;
            $cwsCrypto = new CwsCrypto();
        }
        
        if (empty($this->cookieDomain)) {
            $this->errorMsg = 'Cookie domain empty...';
            $cwsDebug->error($this->errorMsg);
            return;
        }
        
        if (empty($this->dbTableName)) {
            $this->errorMsg = 'Database table name empty...';
            $cwsDebug->error($this->errorMsg);
            return;
        }
        
        if (empty($this->dbExt)) {
            $this->errorMsg = 'Database extension empty...';
            $cwsDebug->error($this->errorMsg);
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
        global $cwsDebug;
        $cwsDebug->titleH2('Open');
        return true;
    }
    
    /**
     * Works like a destructor in classes and is executed after the session write callback has been called.
     * It is also invoked when session_write_close() is called.
     * @return boolean
     */
    public function _close()
    {
        global $cwsDebug;
        $cwsDebug->titleH2('Close');
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
        global $cwsCrypto, $cwsDebug;
        $cwsDebug->titleH2('Read');
        $cwsDebug->labelValue('ID', $id);
        
        $encData = $this->dbSelectSingle(CWSSESSION_DBCOL_DATA, $id);
        if (!empty($encData)) {
            $key = $this->retrieveKey($id);
            $data = $cwsCrypto->decrypt(base64_decode($encData), $key);
            $cwsDebug->dump('Encrypted data', $encData);
            $cwsDebug->dump('Decrypted data', $data);
    
            if (!empty($data)) {
                $exData = self::decode($data);
                $cwsDebug->dump('Unserialized data', $exData);
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
        global $cwsCrypto, $cwsDebug;
        $cwsDebug->titleH2('Write');
        
        $unsData = self::decode($data);
        $id_user = 0;
        if (!empty($unsData) && isset($unsData[CWSSESSION_VAR_ID_USER])) {
            $id_user = intval($unsData[CWSSESSION_VAR_ID_USER]);
        }
    
        $key = $this->retrieveKey($id);
        $encData = base64_encode($cwsCrypto->encrypt($data, $key));
        $expire = intval(time() + $this->lifetime);
    
        $cwsDebug->labelValue('ID', $id);
        $cwsDebug->labelValue('ID user', $id_user);
        $cwsDebug->labelValue('Expire', $expire);
        
        $cwsDebug->dump('Data', $data);
        $cwsDebug->dump('Encrypted data', $encData);
        
        return $this->dbReplaceInto(array($id, $id_user, $expire, $encData, $key));
    }
    
    /**
     * Executed when a session is destroyed with session_destroy() or with session_regenerate_id()
     * @param string $id : session id
     * @return boolean
     */
    public function _destroy($id)
    {
        global $cwsDebug;
        $cwsDebug->titleH2('Destroy');
        $cwsDebug->labelValue('ID', $id);
        return $this->dbDelete(CWSSESSION_DBCOL_ID , '=', $id);
    }
    
    /**
     * The garbage collector callback is invoked internally by PHP periodically in order to purge old session data
     * @return boolean
     */
    public function _gc()
    {
        global $cwsDebug;
        
        $cwsDebug->titleH2('Garbage collector');
        $result = $this->dbDelete(CWSSESSION_DBCOL_EXPIRE , '<', time());
        
        if ($result) {
            $affected = 0;
            if ($this->dbExt == CWSSESSION_DBEXT_MYSQL) {
                $affected = mysql_affected_rows($this->db);
            } elseif ($this->dbExt == CWSSESSION_DBEXT_MYSQLI) {
                $affected = mysqli_affected_rows($this->db);
            } elseif ($this->dbExt == CWSSESSION_DBEXT_PDO) {
                $affected = $this->stmt->rowCount();
            }
            $cwsDebug->labelValue('Destroyed', $affected);
        }
        
        return $result;
    }
    
    /**
     * Prevents unexpected effects when using objects as save handlers.
     */
    public function _write_close()
    {
        global $cwsDebug;
        $cwsDebug->titleH2('Write close');
    
        session_write_close();
        if ($this->dbExt == CWSSESSION_DBEXT_MYSQL) {
            mysql_close($this->db);
        } elseif ($this->dbExt == CWSSESSION_DBEXT_MYSQLI) {
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
        global $cwsDebug;
        
        $cwsDebug->titleH2('Start');
        
        // defines the name of the handler which is used for storing and retrieving data associated with a session
        // default 'files'
        ini_set('session.save_handler', CWSSESSION_CFG_SAVE_HANDLER);
        
        // specifies which HTML tags are rewritten to include session id if transparent sid support is enabled
        // default 'a=href,area=href,frame=src,input=src,form=fakeentry,fieldset='
        ini_set('session.url_rewriter.tags', CWSSESSION_CFG_URL_REWRITER_TAGS);
        
        // transparent sid support is enabled or not
        ini_set('session.use_trans_sid', CWSSESSION_CFG_USE_TRANS_ID);
        
        // marks the cookie as accessible only through the HTTP protocol
        ini_set('session.cookie_httponly', CWSSESSION_CFG_COOKIE_HTTPONLY);
        
        // specifies whether the module will only use cookies to store the session id on the client side
        ini_set('session.use_only_cookies', CWSSESSION_CFG_USE_ONLY_COOKIES);
        
        // domain to set in the session cookie
        ini_set('session.cookie_domain', $this->cookieDomain);
        
        // specify any of the algorithms provided by hash_algos() function
        ini_set('session.hash_function', CWSCRYPTO_PBKDF2_ALGORITHM);
        
        // define how many bits are stored in each character when converting the binary hash data
        // to something readable. The possible values are '4' (0-9, a-f), '5' (0-9, a-v),
        // and '6' (0-9, a-z, A-Z, "-", ",").
        ini_set('session.hash_bits_per_character', CWSSESSION_CFG_HASH_BITS_PER_CHARACTER);
        
        // session cookie parameters
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params(
            $this->lifetime,
            $cookieParams['path'],
            $cookieParams['domain'],
            isset($_SERVER['HTTPS']),
            CWSSESSION_CFG_COOKIE_HTTPONLY
        );
        
        $cwsDebug->dump('Cookie params', session_get_cookie_params());
        
        // start session
        session_start();
        
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
        global $cwsDebug;
        
        session_name($this->sessionName);
        $oldId = session_id();
        session_regenerate_id(true);
        
        $cwsDebug->titleH2('Regenerate ID');
        $cwsDebug->labelValue('Current', $oldId);
        $cwsDebug->labelValue('New', session_id());
    }
    
    /**
     * Update specific session vars (user agent, IP address, fingerprint).
     */
    public function update()
    {
        global $cwsDebug;
        
        $cwsDebug->titleH2('Update');
        
        $_SESSION[CWSSESSION_VAR_UA] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $cwsDebug->labelValue('$_SESSION[\'' . CWSSESSION_VAR_UA . '\']', $_SESSION[CWSSESSION_VAR_UA]);
        
        $_SESSION[CWSSESSION_VAR_IP] = self::getIpAddress();
        $cwsDebug->labelValue('$_SESSION[\'' . CWSSESSION_VAR_IP . '\']', $_SESSION[CWSSESSION_VAR_IP]);
        
        if ($this->fpEnable) {
            $_SESSION[CWSSESSION_VAR_FINGERPRINT] = $this->retrieveFingerprint();
            $cwsDebug->labelValue('$_SESSION[\'' . CWSSESSION_VAR_FINGERPRINT . '\']', $_SESSION[CWSSESSION_VAR_FINGERPRINT]);
        }
    }
    
    /**
     * Check if the session is active or not.
     * @return boolean
     */
    public function isActive()
    {
        global $cwsDebug;
        
        $id = session_id();
        $expire = $this->dbSelectSingle(CWSSESSION_DBCOL_EXPIRE, $id);
        $current = time();
        
        $cwsDebug->titleH2('Active');
        $cwsDebug->labelValue('Current time', $current);
        $cwsDebug->labelValue('Expire', $expire);
        
        if ($expire > $current) {
            $cwsDebug->simple('Session <strong>' . $id . '</strong> active!');
            return true;
        } else {
            $cwsDebug->simple('Session <strong>' . $id . '</strong> not active..');
            session_destroy();
            return false;
        }
    }
    
    /**
     * Set the informations to connect to the database.
     * @param string $host - can be either a host name or an IP address.
     * @param string $dbname - will specify the default database to be used when performing queries.
     * @param string $username - the user name.
     * @param string $password - the password.
     * @param NULL|number $port - the port (optional). Leave empty if you are not sure.
     * @param NULL|string $charset - the database charset (optional).
     * @param string $pdoDriver - the PDO driver (optional). Default CWSSESSION_DBDRIVER_MYSQL.
     */
    public function setDbInfos($host, $dbname, $username, $password, $port=null, $charset=null, $pdoDriver=CWSSESSION_DBDRIVER_MYSQL)
    {
        $this->dbHost = $host;
        $this->dbName = $dbname;
        $this->dbUsername = $username;
        $this->dbPassword = $password;
        $this->dbPort = $port;
        $this->dbCharset = $charset;
        $this->dbPdoDriver = $pdoDriver;
    }
    
    /**
     * Connect to database.
     * @return boolean
     */
    private function dbConnect()
    {
        global $cwsDebug;
        
        $dbExts = array(CWSSESSION_DBEXT_MYSQL, CWSSESSION_DBEXT_MYSQLI, CWSSESSION_DBEXT_PDO);
        if (!in_array($this->dbExt, $dbExts)) {
            $this->errorMsg = 'Database extension unknown... Selected : ' . $this->dbExt;
            $cwsDebug->error($this->errorMsg);
        } elseif (empty($this->dbHost)) {
            $this->errorMsg = 'Database host empty...';
            $cwsDebug->error($this->errorMsg);
            return false;
        } elseif (empty($this->dbName)) {
            $this->errorMsg = 'Database name empty...';
            $cwsDebug->error($this->errorMsg);
            return false;
        } elseif (empty($this->dbUsername)) {
            $this->errorMsg = 'Database username empty...';
            $cwsDebug->error($this->errorMsg);
            return false;
        }
        
        if ($this->dbExt == CWSSESSION_DBEXT_MYSQL) {
            return $this->dbConnectMysql();
        } elseif ($this->dbExt == CWSSESSION_DBEXT_MYSQLI) {
            return $this->dbConnectMysqli();
        } elseif ($this->dbExt == CWSSESSION_DBEXT_PDO) {
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
        global $cwsDebug;
        
        if (!function_exists('mysql_connect')) {
            $this->errorMsg = CWSSESSION_DBEXT_MYSQL . ' - Extension not loaded. Check your PHP configuration...';
            $cwsDebug->error($this->errorMsg);
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
                    $this->errorMsg = CWSSESSION_DBEXT_MYSQL . ' - Error loading character set ' . $this->dbCharset . ': ' . mysql_error($this->db);
                    $cwsDebug->error($this->errorMsg);
                    return false;
                }
            }
        } else {
            $this->errorMsg = CWSSESSION_DBEXT_MYSQL . ' - ' . mysql_error($this->db);
            $cwsDebug->error($this->errorMsg);
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
        global $cwsDebug;
        
        if (!function_exists('mysqli_connect')) {
            $this->errorMsg = CWSSESSION_DBEXT_MYSQLI . ' - Extension not loaded. Check your PHP configuration...';
            $cwsDebug->error($this->errorMsg);
            return false;
        }
        
        $this->db = mysqli_connect($this->dbHost, $this->dbUsername, $this->dbPassword, $this->dbName);
        
        if (mysqli_connect_errno()) {
            $this->errorMsg = CWSSESSION_DBEXT_MYSQLI . ' - ' . mysqli_connect_errno();
            $cwsDebug->error($this->errorMsg);
            return false;
        }
        
        if (!empty($this->dbCharset)) {
            if (!mysqli_set_charset($this->db, $this->dbCharset)) {
                $this->errorMsg = CWSSESSION_DBEXT_MYSQLI . ' - Error loading character set ' . $this->dbCharset . ': ' . mysqli_error($this->db);
                $cwsDebug->error($this->errorMsg);
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
        global $cwsDebug;
        
        if (!defined('PDO::ATTR_DRIVER_NAME')) {
            $this->errorMsg = CWSSESSION_DBEXT_PDO . ' - Extension not loaded. Check your PHP configuration...';
            $cwsDebug->error($this->errorMsg);
            return false;
        } elseif (!$this->isValidPdoDriver()) {
            $this->errorMsg = CWSSESSION_DBEXT_PDO . ' - The database you wish to connect to is not supported by your install of PHP. ';
            $this->errorMsg .= 'Check your PDO driver (selected: ' . $this->dbPdoDriver . ')...';
            $cwsDebug->error($this->errorMsg);
            return false;
        }
        
        // Set DSN
        $dsn = $this->dbPdoDriver . ':';
        if ($this->dbPdoDriver == CWSSESSION_DBDRIVER_SQLITE || $this->dbPdoDriver == CWSSESSION_DBDRIVER_SQLITE2) {
            $dsn .= $this->dbHost;
        } elseif ($this->dbPdoDriver == CWSSESSION_DBDRIVER_SQLSRV) {
            $this->dbPort = !is_null($this->dbPort) ? ',' . $this->dbPort : '';
            $dsn .= 'Server=' . $this->dbHost . $this->dbPort . ';Database=' . $this->dbName;
        } elseif ($this->dbPdoDriver == CWSSESSION_DBDRIVER_FIREBIRD || $this->dbPdoDriver == CWSSESSION_DBDRIVER_OCI) {
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
            $this->errorMsg = CWSSESSION_DBEXT_PDO . ' - ' . $e->getMessage();
            $cwsDebug->error($this->errorMsg);
            return false;
        } catch(Exception $e) {
            $this->errorMsg = CWSSESSION_DBEXT_PDO . ' - ' . $e->getMessage();
            $cwsDebug->error($this->errorMsg);
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
        $query .= 'WHERE `' . CWSSESSION_DBCOL_ID . '` = "' . $this->dbEscapeString($idFilter) . '" LIMIT 1';
        
        $result = $this->dbQuery($query);
        if ($result !== false) {
            if ($this->dbExt == CWSSESSION_DBEXT_MYSQL) {
                $numRows = mysql_num_rows($result);
                if ($numRows == 1) {
                    return mysql_result($result, 0);
                }
            } elseif ($this->dbExt == CWSSESSION_DBEXT_MYSQLI) {
                $numRows = mysqli_num_rows($result);
                if ($numRows == 1) {
                    $fetch = mysqli_fetch_assoc($result);
                    return $fetch[$column];
                }
            } elseif ($this->dbExt == CWSSESSION_DBEXT_PDO) {
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
        if ($this->dbExt == CWSSESSION_DBEXT_MYSQL) {
            return mysql_real_escape_string($string, $this->db);
        } elseif ($this->dbExt == CWSSESSION_DBEXT_MYSQLI) {
            return $this->db->real_escape_string($string);
        } elseif ($this->dbExt == CWSSESSION_DBEXT_PDO) {
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
        global $cwsDebug;
        
        $result = false;
        $query = trim($query);
        
        if ($this->dbExt == CWSSESSION_DBEXT_MYSQL) {
            $result = mysql_query($query, $this->db);
            if (!$result) {
                $this->errorMsg = CWSSESSION_DBEXT_MYSQL . ' - ' . mysql_error($this->db);
                $cwsDebug->error($this->errorMsg);
            }
        } elseif ($this->dbExt == CWSSESSION_DBEXT_MYSQLI) {
            $result = mysqli_query($this->db, $query);
            if (!$result) {
                $this->errorMsg = CWSSESSION_DBEXT_MYSQLI . ' - ' . mysqli_error($this->db);
                $cwsDebug->error($this->errorMsg);
            }
        } elseif ($this->dbExt == CWSSESSION_DBEXT_PDO) {
            try {
                $this->stmt = $this->db->prepare($query);
                $result = $this->stmt->execute();
            } catch (PDOException $e) {
                $this->errorMsg = CWSSESSION_DBEXT_PDO . ' - ' . $e->getMessage();
                $cwsDebug->error($this->errorMsg);
            } catch(Exception $e) {
                $this->errorMsg = CWSSESSION_DBEXT_PDO . ' - ' . $e->getMessage();
                $cwsDebug->error($this->errorMsg);
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
        global $cwsDebug;
        
        $cwsDebug->titleH3('Check fingerprint');
        
        if ($this->fpEnable) {
            $fingerprint = $this->retrieveFingerprint();
            if (empty($fingerprint)) {
                $this->errorMsg = 'Can\'t generate fingerprint...';
                $cwsDebug->error($this->errorMsg);
                return false;
            }
            if (!isset($_SESSION[CWSSESSION_VAR_FINGERPRINT])) {
                $this->errorMsg = 'Fingerprint not setted...';
                $cwsDebug->error($this->errorMsg);
                return false;
            }
            if ($fingerprint != $_SESSION[CWSSESSION_VAR_FINGERPRINT]) {
                $this->errorMsg = 'Fingerprint error... Has <strong>' . $fingerprint . ' but expected <strong>' . $_SESSION[CWSSESSION_VAR_FINGERPRINT] . '</strong>';
                $cwsDebug->error($this->errorMsg);
                return false;
            }
            $cwsDebug->simple('Fingerprint OK : ' . $_SESSION[CWSSESSION_VAR_FINGERPRINT]);
        } else {
            $cwsDebug->simple('Fingerprint check disabled...');
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
        if ($this->fpMode == CWSSESSION_FP_MODE_SHIELD) {
            $fingerprint .= CWSSESSION_FP_SEPARATOR . self::getIpAddress();
        }
        if (!empty($fingerprint)) {
            return sha1(CWSSESSION_FP_PREFIX . CWSSESSION_FP_SEPARATOR . $fingerprint);
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
        global $cwsDebug;
        
        $key = $this->dbSelectSingle(CWSSESSION_DBCOL_KEY, $id);
        if (!empty($key)) {
            $cwsDebug->labelValue('Key retrieved from database', htmlentities($key));
            return $key;
        } else {
            $key = CwsCrypto::random(56);
            $cwsDebug->labelValue('Generated random key', htmlentities($key));
            return $key;
        }
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
        $eindex = strpos($data, CWSSESSION_DELIMITER, $sindex);
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
     * Set the debug verbose. (see CwsDebug class)
     * @param int $debugVerbose
     */
    public function setDebugVerbose($debugVerbose)
    {
        $this->debugVerbose = $debugVerbose;
    }
    
    /**
     * Set the debug mode. (see CwsDebug class)
     * @param int $debugMode - CWSDEBUG_MODE_ECHO or CWSDEBUG_MODE_FILE
     * @param string $debugFilePath - The debug file path for CWSDEBUG_MODE_FILE. 
     */
    public function setDebugMode($debugMode, $debugFilePath=null)
    {
        $this->debugMode = $debugMode;
        if ($debugFilePath != null) {
            $this->debugFilePath = $debugFilePath;
        }
    }

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
    public function getFpEnable()
    {
        return $this->fpEnable;
    }
    
    /**
     * Enable/disable fingerprint.
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
     * Set the fingerprint mode.
     * @param number $fpMode
     */
    public function setFpMode($fpMode)
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
     * Set the database PHP extension used to store sessions.
     * @param string $dbExt
     */
    public function setDbExt($dbExt)
    {
        $this->dbExt = $dbExt;
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
     * @param string $dbTableName
     */
    public function setDbTableName($dbTableName)
    {
        $this->dbTableName = $dbTableName;
    }
    
    /**
     * The error msg.
     * @return the $errorMsg
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }
}