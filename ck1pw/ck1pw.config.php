<?php
// Per-app ck1pw config — one file changes per app.
define('CK1PW_BASE',   'https://ck1pw.peoplestar.com');   // where ck1pw is served
define('CK1PW_APP_ID', 'quickmls');                      // must match `apps` table
define('CK1PW_RETURN', 'https://APP_HOST_FOR_quickmls/ck1pw/return.php'); // whitelisted return uri
// Optional: where to land after super-admin login (defaults to '/')
// define('CK1PW_POST_LOGIN', '/admin');
