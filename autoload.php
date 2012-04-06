<?php

spl_autoload_register(function( $className ) {
    // Namespace mapping
    $namespaces = array(
        "Art" => ROOT . "/vendor/Art",
        "Model" => ROOT . "/model",
        "Pagerfanta" => ROOT . "/vendor/Pagerfanta"
    );

    foreach ( $namespaces as $ns => $path ) {
        if ( 0 === strpos( $className, "{$ns}\\" ) ) {
            $pathArr = explode( "\\", $className );
            $pathArr[0] = $path;

            $class = implode(DIRECTORY_SEPARATOR, $pathArr);

            require_once "{$class}.php";
        }
    }
});


