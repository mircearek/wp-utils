<?php

add_filter('the_content', 'remove_fusion_shortcodes', 20, 1);

function remove_fusion_shortcodes( $content ) {
	$content = preg_replace('/\[\/?et_pb.*?\]/', '', $content);
	return preg_replace_callback('/(?<!src=[\"\'])(https?:\/\/(.*?)\.(jpg|png|gif)(\?\w+=\w+)?)/i', '_callback', $content);
}

function _callback($matches){
	return '<img src="'.$matches[0].'" />';
}
