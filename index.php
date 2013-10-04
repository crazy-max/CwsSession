<?php

// Download CwsDump at https://github.com/crazy-max/CwsDump
require_once '../CwsDump/class.cws.dump.php';

// Download CwsDebug at https://github.com/crazy-max/CwsDebug
require_once '../CwsDebug/class.cws.debug.php';

// Download CwsCrypto at https://github.com/crazy-max/CwsCrypto
require_once '../CwsCrypto/class.cws.crypto.php';

require_once 'class.cws.session.php';
$cwsSession = new CwsSession();
$cwsSession->setDebugVerbose(CWSDEBUG_VERBOSE_DEBUG); // CWSDEBUG_VERBOSE_QUIET, CWSDEBUG_VERBOSE_SIMPLE, CWSDEBUG_VERBOSE_REPORT or CWSDEBUG_VERBOSE_DEBUG
$cwsSession->setDebugMode(CWSDEBUG_MODE_ECHO);        // CWSDEBUG_MODE_ECHO or CWSDEBUG_MODE_FILE
$cwsSession->setLifetime(1800);                       // in seconds (1800s = 30min)
$cwsSession->setCookieDomain('localhost');            // your domain
$cwsSession->setSessionName('whatuwant');             // default PHPSESSID
$cwsSession->setFpEnable(true);                       // default true
$cwsSession->setFpMode(CWSSESSION_FP_MODE_BASIC);     // default CWSSESSION_FP_MODE_BASIC (check user agent)
$cwsSession->setDbExt(CWSSESSION_DBEXT_PDO);          // default CWSSESSION_DBEXT_PDO
$cwsSession->setDbTableName('sessions');              // the database table name to store sessions (see README.md for structure)

// the informations to connect to the database.
$cwsSession->setDbInfos(
    'localhost',              // database host can be either a host name or an IP address.
    'cws_session',            // database to be used when performing queries.
    'root',                   // database user name.
    '',                       // database password.
    null,                     // database port. Leave empty if your are not sure.
    null,                     // database charset to use. Leave empty if your are not sure.
    CWSSESSION_DBDRIVER_MYSQL // PDO driver to use (if you choose the CWSSESSION_DBEXT_PDO database extension). Default CWSSESSION_DBDRIVER_MYSQL.
);

// Start!
$cwsSession->process();

?>