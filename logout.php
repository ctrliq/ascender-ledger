<?php

include_once('includes/global.php');

if( !isset($_SESSION)) {
	session_start();
}

unset($_SESSION['auth']);
unset($_SESSION['username']);
unset($_SESSION['auth']);
unset($_SESSION['code']);
@session_destroy();

Header("Location: /\n\n");
exit;
