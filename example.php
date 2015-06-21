<?php

// Download CwsDump at https://github.com/crazy-max/CwsDump
require_once '../CwsDump/class.cws.dump.php';
$cwsDump = new CwsDump();

// Download CwsDebug at https://github.com/crazy-max/CwsDebug
require_once '../CwsDebug/class.cws.debug.php';
$cwsDebug = new CwsDebug($cwsDump);
$cwsDebug->setDebugVerbose();
$cwsDebug->setEchoMode();

// Download CwsCrypto at https://github.com/crazy-max/CwsCrypto
require_once '../CwsCrypto/class.cws.crypto.php';
$cwsCrypto = new CwsCrypto($cwsDebug);

require_once 'class.cws.session.php';
$cwsSession = new CwsSession($cwsDebug, $cwsCrypto);

$cwsSession->setLifetime(1800); // in seconds (1800s = 30min)
$cwsSession->setCookieDomain('localhost'); // your domain (eg. crazyws.fr)
$cwsSession->setSessionName('whatuwant'); // default PHPSESSID
$cwsSession->setFpEnable(true); // default true
$cwsSession->setFpModeBasic(); // default (check user agent)
//$cwsSession->setFpModeShield(); // (check user agent and ip address)

//$cwsSession->setDbExtMysql();
//$cwsSession->setDbExtMysqli();
$cwsSession->setDbExtPdo(); // default
//$cwsSession->setDbPdoDriverFirebird();
$cwsSession->setDbPdoDriverMysql(); // default
//$cwsSession->setDbPdoDriverOci();
//$cwsSession->setDbPdoDriverPgsql();
//$cwsSession->setDbPdoDriverSqlite();
//$cwsSession->setDbPdoDriverSqlite2();
//$cwsSession->setDbPdoDriverSqlsrv();
$cwsSession->setDbHost('localhost');
//$cwsSession->setDbPort(null); // null for default port
$cwsSession->setDbUsername('root');
$cwsSession->setDbPassword('');
$cwsSession->setDbName('cws_session');
//$cwsSession->setDbCharset(null); // null for default charset
$cwsSession->setDbTableName('sessions'); // the database table name to store sessions (see README.md for structure)

// Start!
$cwsSession->process();

/**
 * In your application when the user is logged in, set the id in the session var
 * Can be useful if you want to know who is connected.
 * See 'Count visitors and users connected' section in the README.md.
 */
$userId = 1;
$cwsSession->setParamUserId($userId);
