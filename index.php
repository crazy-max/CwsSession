<?php

require_once 'class.cws.session.php';

// Download CwsCrypto at https://github.com/crazy-max/CwsCrypto
require_once 'class.cws.crypto.php';

$cwsSession = new CwsSession();
$cwsSession->setDebugVerbose(CWSSESSION_VERBOSE_DEBUG); // default CWSSESSION_VERBOSE_QUIET
$cwsSession->setDebugOutputMode(CWSSESSION_DEBUG_ECHO); // default CWSSESSION_DEBUG_ECHO
$cwsSession->setLifetime(1800);                         // in seconds (1800s = 30min)
$cwsSession->setCookieDomain('.foo.com');               // your domain
$cwsSession->setSessionName('whatuwant');               // default PHPSESSID
$cwsSession->setFpEnable(true);                         // default true
$cwsSession->setFpMode(CWSSESSION_FP_MODE_BASIC);       // default CWSSESSION_FP_MODE_BASIC (check user agent)
$cwsSession->setDbExt(CWSSESSION_DBEXT_PDO);            // default CWSSESSION_DBEXT_PDO
$cwsSession->setDbTableName('sessions');                // the database table name to store sessions (see README.md for structure).

// the informations to connect to the database.
$cwsSession->setDbInfos(
    'localhost',              // database host can be either a host name or an IP address.
    'cws_session',            // database to be used when performing queries.
    'root',                   // database user name.
    '',                       // database password.
    null,                     // database port. Leave empty if your are not sure.
    CWSSESSION_DBDRIVER_MYSQL // PDO driver to use (if you choose the CWSSESSION_DBEXT_PDO database extension). Default CWSSESSION_DBDRIVER_MYSQL.
);

// Start!
$cwsSession->process();

?>