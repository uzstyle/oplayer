<?php

function conf( $section, $key, $default = null ) {
    return Art\Config::getInstance()->getOption($section, $key, $default);
}

function view_ducation( $duration ) {
	$mins = floor( $duration/60 );
	$secs = $duration - $mins*60;

	return $mins . ":" . (( $secs < 10 ) ? "0" : "") . $secs;
}


function trunc( $string, $limit = 30, $pad="..." ) {
    if ( mb_strlen($string) <= $limit ) {
        return $string;
    }

    return mb_substr( $string, 0, $limit, "utf8" ) . $pad;
}

function seo_title() {
	$seo = Reg::get('seo');
	return $seo['title'];
}

function seo_description() {
	$seo = Reg::get('seo');
	return $seo['description'];
}

function seo_keywords() {
	$seo = Reg::get('seo');
	return $seo['keywords'];
}