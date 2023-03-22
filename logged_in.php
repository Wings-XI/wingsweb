<?php

/**
 *	@file logged_in.php
 *	Simple check to return if the user is logged in for nginx auth_request module
 *	(C) 2020-2023 MowFord
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */

require_once("output.php");
require_once("user.php");

if(WGWUser::$user->is_logged_in()){  // or $_SESSION
   print("Yes");
}else{
   header("HTTP/1.1 401 Unauthorized");
    exit;
}
?>

