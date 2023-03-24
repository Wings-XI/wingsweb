<?php

/**
 *	@file logged_in_admin.php
 *	Simple check to return if the user is logged in and an admin for nginx auth_request module
 *	(C) 2020-2023 MowFord
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */

require_once("output.php");
require_once("user.php");

if(WGWUser::$user->is_logged_in() and WGWUser::$user->is_admin()){
   print("Yes");
}else{
   header("HTTP/1.1 401 Unauthorized");
    exit;
}
?>

