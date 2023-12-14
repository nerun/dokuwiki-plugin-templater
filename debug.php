<?php
/**
 * Daniel Dias Rodrigues (aka Nerun) <danieldiasr@gmail.com>
 * add to script:
 * require_once('debug.php');
 */
function printr($arr, $name=null) {
	echo '<pre>';
	if (!empty($name)){
	    echo $name.' =<br />';
	}
	print_r($arr);
	echo '</pre><br />';
}
?>
