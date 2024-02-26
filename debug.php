<?php
/**
 * Daniel Dias Rodrigues (aka Nerun) <danieldiasr@gmail.com>
 * add to script:
 * require_once('debug.php');
 */
function printr($var, $name=null) {
    echo '<pre>';
    if (!empty($name)){
        echo $name.' =<br />';
    }
    if (is_array($var)) {
        foreach ( $var as $key => $value ){
            if ( is_array($var[$key]) ) {
                print_r(array_map("htmlspecialchars", $value));
            } else {
                print_r(htmlspecialchars($value));
            }
        }
    } else {
        print_r(htmlspecialchars($var));
    }
    echo '</pre><br />';
}
