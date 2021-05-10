<?php

/**
 *	@file output.php
 *	Output wrapper. Used to automatically add the page header and footer
 *	(C) 2020 Twilight
 *	This software is available under AGPLv3 license.
 *	Source code of modified versions must be disclosed.
 */
 
require_once("header.php");
// require_once("user.php");

class WGWOutput
{
	private $m_output = "";
	public $title = null;
	
	public function __construct()
	{
		ob_start();
	}
	
	public function __destruct()
	{
		$this->SendAll();
	}
	
	/**
	 *	Add a string to the output
	 */
	public function write($str)	
	{
		$this->m_output = $this->m_output . $str;
	}
	
	public function SendAll()
	{
		global $g_wgwHeaderBefore;
		global $g_wgwHeaderAfter;
		global $g_wgwMenu;
		global $g_wgwFooter;
		global $g_wgwNotLoggedInHeader;
		global $g_wgwLoggedInHeader;
		
		if ($this->m_output != "") {
			$final = WGWGetPageHeader($this->title) . $this->m_output . WGWGetPageFooter();
			echo $final;
			header("Content-Length: " . ob_get_length());
		}
		ob_end_flush();
	}
	
	public static $out;
};

WGWOutput::$out = new WGWOutput();

?>
