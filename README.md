# WPFBBotKit

WPFBBotKit provides an easy way for developers to start creating bots with the Facebook Messenger Platform.
WPFBBotKit is not a bot itself, but allows you to easily verify and receive webhook requests from the Messenger
Platform and send responses from your bot via a simplified API.

## Getting Started

1. Download or clone this repo and install via the WordPress plugin installer. 
2. Access WPFBBotKit Settings via the "WPFBBotKit" menu option under "Settings" in the wp-admin.
3. Follow the Messenger Platform [setup guide](https://developers.facebook.com/docs/messenger-platform/guides/setup)
to set up your bot using the Webhook URL and Verification String from the WPFBBotKit Settings page.

## Usage

Receive messages via the `wpfbbk_message_received` hook which will provide an instance of [`WPFBBK_Messaging`](class/class-wpfbbk-messaging.php) as its only argument. 

```php
add_action( 'wpfbbk_message_received', function( $M ) {
	# bot code here
}, 10, 1 );
```

When a message has been received, you'll have access to the message's text and/or postback content as well as a few helper functions to help you respond to the message and find out information about the sender.

```php
add_action( 'wpfbbk_message_received', function( $M ) {

	// Retrieve user's info from Facebook API
	$info = $M->get_user_info();
	
	// set fallback since user info is not guaranteed
	$name = $info->first_name ? $info->first_name : 'Human';
	
	if ( ! $M->postback ){
	
		// if no postback  is set, respond to text message
		if ( 'hello' === $M->text || 'hi' === $M->text ) {
			$M->reply_with_text( "Hi $name!", true );
			$M->reply_with_image_url( 'https://media.giphy.com/media/13TXV4kfn7r2iA/giphy.gif' );
		} else {
		
			// reply_with_buttons is one of a few reply helpers provided by `WPFBBK_Messaging`
			$M->reply_with_buttons(
				"Sorry, $name. I don't understand what you said. Do you want me to send you a gif?",
				array(
					array(
						'type'  => 'postback',
						'title' => '👍  Sure.',
						'payload' => 'REPLY_WITH_GIF',
					),
					array(
						'type'  => 'postback',
						'title' => '👎  No, go away.',
						'payload' => 'NO_REPLY',
					),
				)
			);
		}
	} else {
	
		// postback has been received
		if( 'REPLY_WITH_GIF' === $M->postback ) {
			$M->reply_with_image_url( 'https://media.giphy.com/media/13TXV4kfn7r2iA/giphy.gif' );
		}
	}
}, 10, 1 );
```

Additionally, a `wpfbbk_request_received` action is fired whenever the webhook url is hit and provides an instance
 of the [WP_REST_Request](https://developer.wordpress.org/reference/classes/wp_rest_request/) object for the request. 
 
 ```php
 // debug requests sent to webhook
 add_action( 'wpfbbk_request_received', function( $req ) {
    error_log( print_r( $req->get_raw_data(), 1 ) );
 }, 10, 1 );
 ```
 
 Finally, a `wpfbbk_before_send_request` filter is applied before sending any request via `WPFBBK::api_send()` which
 provides an opportunity to bypass the built-in sending functionality and offload sending API requests to a queue. 
 Documentation for this hook can be found inline in `WPFBBK::api_send()`.
 
 ```php
 // send requests to queue instead of using WPFBBK::api_send()
 apply_filters( 'wpfbbk_before_send_request', function( $request_handled, $method, $url, $data ) {
    if ( ! $request_handled ) {
        $request_handled = SomeOtherClass::queueRequest( $method, $url, $data );
    }
    return $request_handled;
 }, 10, 4 );
 ```

## Requirements

Requires WordPress 4.7 or better since WPFBBotKit utilizes the WordPress Rest API.

## License

This project is licensed under the GPL V2 License - see the [LICENSE](LICENSE) file for details

## Credits

WPFBBotKit was created by [Jeff Gould](https://twitter.com/jrgould/) from [Delicious Brains](https://deliciousbrains.com)
