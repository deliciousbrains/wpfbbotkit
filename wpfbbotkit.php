<?php
/**
 * Plugin Name: WPFBBotKit
 * Plugin URI: https://jrgould.com
 * Description: A Facebook Messenger Bot for WordPress
 * Version: 1.0.0
 * Author: Jeff Gould
 * Author URI: https://jrgould.com
 * License: GPL2
 */

require_once dirname( __FILE__ ) . '/class/class-wpfbbk-plugin.php';
new WPFBBotKit_Plugin();

function wpfbbk_message_received( $callback, $priority = 10 ) {
	add_action( 'wpfbbk_message_received', $callback, $priority, 1 );
}


wpfbbk_message_received( function( $M ) {
	$message = $M->text ? $M->text : $M->postback;

	error_log( 'Received Message: ' . $message );

	error_log( print_r( $M->entry, 1 ) );

	$M->set_typing_on();

	$user_info = $M->get_user_info();
	if( ! is_wp_error( $user_info ) && isset( $user_info->first_name ) ) {
		$name = $user_info->first_name;
	} else {
		error_log( print_r( $user_info, 1 ) );
		error_log( $M->last_request->body );
		$name = 'there';
	}

	$M->reply_with_text( "Hey {$name}!", true );
	$M->reply_with_image_url( 'https://media.giphy.com/media/3oEduULmVplmGtWNmE/giphy.gif', true );

	$quick_replies = [
		[
			'content_type' => 'text',
			'title' => '🎉   YQY!',
			'payload' => 'QR_YES',
		],
		[
			'content_type' => 'text',
			'title' => '🔥   NOOOOOOOO!',
			'payload' => 'QR_NO',
		]
	];
	// $M->reply( [
	// 	'text' => 'How \'bout them apples?',
	// 	'quick_replies' => $quick_replies,
	// ] );

	$buttons = [
		[
			'type' => 'postback',
			'title' => 'Option One 🎤',
			'payload' => 'OPTION_ONE',
		],
		[
			'type' => 'postback',
			'title' => 'Option Two 🙇‍',
			'payload' => 'OPTION_TWO',
		]
	];
	$M->reply_with_buttons( 'Hey there!', $buttons );
	exit();
} );


// add_action( 'wpfbbk_request_received', function( $req ) {
// 	error_log( 'entire request: ');
// 	error_log( print_r( $req->get_params(), 1) );
// } );
