# CwsSession

CwsSession is a PHP class to manipulate sessions.<br />
Data are securely encrypted and sessions are stored in database. 

## Requirements and installation

* PHP version >= 5.1.5
* A database.
* Download and copy the [CwsDump](https://github.com/crazy-max/CwsDump), [CwsDebug](https://github.com/crazy-max/CwsDebug) and [CwsCrypto](https://github.com/crazy-max/CwsCrypto) PHP classes.
* Copy the ``class.cws.session.php`` file in a folder on your server.
* You can use the ``index.php`` file sample to help you.

## Getting started

Add a new table in your database with the following structure.<br />
You can change the name of the table (sessions) but not the columns.

```sql
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `id_user` int(10) unsigned NOT NULL DEFAULT '0',
  `expire` int(10) unsigned NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  `skey` varchar(128) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

**id** - the session id.<br />
**id_user** - the user id from your application. If you want to use it, use CWSSESSION_VAR_ID_USER as $_SESSION key.<br />
**expire** - the session cache expire.<br />
**data** - the session data.<br />
**skey** - unique key for data encryption.<br />

PHP example :

```php
<?php

// Download CwsDump at https://github.com/crazy-max/CwsDump
require_once 'class.cws.dump.php';

// Download CwsDebug at https://github.com/crazy-max/CwsDebug
require_once 'class.cws.debug.php';

// Download CwsCrypto at https://github.com/crazy-max/CwsCrypto
require_once 'class.cws.crypto.php';

require_once 'class.cws.session.php';
$cwsSession = new CwsSession();
$cwsSession->setDebugVerbose(CWSDEBUG_VERBOSE_DEBUG); // CWSDEBUG_VERBOSE_QUIET, CWSDEBUG_VERBOSE_SIMPLE, CWSDEBUG_VERBOSE_REPORT or CWSDEBUG_VERBOSE_DEBUG
$cwsSession->setDebugMode(CWSDEBUG_MODE_ECHO); // CWSDEBUG_MODE_ECHO or CWSDEBUG_MODE_FILE
$cwsSession->setLifetime(1800); // in seconds (1800s = 30min)
$cwsSession->setCookieDomain('localhost'); // your domain
$cwsSession->setSessionName('whatuwant'); // default PHPSESSID
$cwsSession->setFpEnable(true); // default true
$cwsSession->setFpMode(CWSSESSION_FP_MODE_BASIC); // default CWSSESSION_FP_MODE_BASIC (check user agent)
$cwsSession->setDbExt(CWSSESSION_DBEXT_PDO); // default CWSSESSION_DBEXT_PDO
$cwsSession->setDbTableName('sessions'); // the database table name to store sessions (see README.md for structure)

// the informations to connect to the database.
$cwsSession->setDbInfos(
    'localhost', // database host can be either a host name or an IP address.
    'cws_session', // database to be used when performing queries.
    'root', // database user name.
    '', // database password.
    null, // database port. Leave empty if your are not sure.
    null, // database charset to use. Leave empty if your are not sure.
    CWSSESSION_DBDRIVER_MYSQL // PDO driver to use (if you choose the CWSSESSION_DBEXT_PDO database extension). Default CWSSESSION_DBDRIVER_MYSQL.
);

// Start!
$cwsSession->process();

?>
```

## Disconnect all users

If you want to disconnect all the users from your PHP application, execute this query :

```sql
TRUNCATE TABLE `sessions`;
```

## Count visitors and users connected

If you want to count visitors and users connected on your PHP application, execute these queries :

```sql
SELECT (SELECT COUNT(*) FROM `sessions` WHERE `id_user` > 0 LIMIT 1) AS nb_connected,
(SELECT COUNT(*) FROM `sessions` WHERE `id_user` = 0 LIMIT 1) AS nb_visitors;
```

## Example

An example is available in ``index.php`` file :

![](http://static.crazyws.fr/resources/blog/2013/10/cwssession-debug2.png)

## Methods

**process** - Start the process.<br />
**start** - To call everytime you want to start a new session instead of session_start().<br />
**regenerate** - Regenerates the session and delete the old one. It also generates a new encryption key in the database. To use each time a user connects to your application successfully.<br />
**update** - Update specific session vars (user agent, IP address, fingerprint).<br />
**isActive** - Check if the session is active or not.<br />
**setDbInfos** - Set the informations to connect to the database (host, dbname, username, password, port, charset, pdoDriver).<br />

**setDebugVerbose** - Set the debug verbose. (see CwsDebug class)<br />
**setDebugMode** - Set the debug mode. (see CwsDebug class)<br />
**getLifetime** - The session life time.<br />
**setLifetime** - Set the session life time (in seconds).<br />
**getCookieDomain** - The domain of the session cookie.<br />
**setCookieDomain** - Set the domain of the session cookie (eg: .foo.com).<br />
**getSessionName** - The session name.<br />
**setSessionName** - Set the session name. (default PHPSESSID).<br />
**getFpEnable** - The fingerprint enable status.<br />
**setFpEnable** - Enable/disable fingerprint.<br />
**getFpMode** - The fingerprint mode.<br />
**setFpMode** - Set the fingerprint mode (default CWSSESSION_FP_MODE_BASIC).<br />
**getDbExt** - The database PHP extension used to store sessions.<br />
**setDbExt** - Set the database PHP extension used to store sessions (default CWSSESSION_DBEXT_PDO).<br />
**getDbTableName** - The database table name to store sessions.<br />
**setDbTableName** - Set the database table name to store sessions (default CWSSESSION_DBEXT_PDO).<br />
**getErrorMsg** - The last error message.

## License

LGPL. See ``LICENSE`` for more details.

## More infos

http://www.crazyws.fr/dev/classes-php/cwssession-proteger-les-sessions-php-et-les-stocker-en-base-de-donnees-7VB7X.html
