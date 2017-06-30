<?php

// TODO: replies should be queued and run on separate requests if possible, as sending too many replies could result in an FB timeout/resend

class WPFBBotKit_Messaging {

	protected $_sender;
	protected $_message;
	protected $_entry;
	protected $_text;
	protected $_postback;
	protected $_page_access_token;
	protected $_last_request;

	public $fb_api_base = 'https://graph.facebook.com/v2.6';
	public $messages_api;
	public $user_api;

	function __construct( $entry, $plugin, $send_200 = true ) {
		$this->init_props( $entry, $plugin );
	}

	protected function init_props( $entry, $plugin ) {
		$this->_page_access_token = $plugin->get_page_access_token();

		$this->_entry = $entry;

		$messaging = $entry;


		if ( isset( $entry['sender'] ) ) {
			$this->_sender = $entry['sender'];
		}
		if ( isset( $entry['message'] ) ) {
			$this->_message = $entry['message'];
			if ( isset( $entry['message']['text'] ) ) {
				$this->_text = $entry['message']['text'];
			}
			if ( isset( $entry['message']['quick_reply'] ) ) {
				$this->_postback= $entry['message']['quick_reply']['payload'];
			}
		}

		if ( isset( $entry['postback'] ) ) {
			$this->_postback = $entry['postback']['payload'];
		}

		$this->messages_api = $this->fb_api_base . '/me/messages?access_token=' . urlencode( $this->_page_access_token );
		$this->user_api = $this->fb_api_base . '/' . $this->_sender['id'] . '?access_token=' . urlencode( $this->_page_access_token );
	}

	function __get( $name ) {
		if( property_exists( $this, '_' . $name) ) {
			return $this->{ '_' . $name };
		}

		throw new Exception("Can not get property: {$name}", 1);
	}

	/**
	 * TODO: Appears not to work with WordPress
	 * Attempts to send a 200 response to the requester efore continuing execution to
	 * ensure that Facebook doesn't retry the webhook while we're processing. It is
	 * recommended that you call `exit()` when done responding in order to prevent
	 * warnings from other parts of WP that might try to send headers
	 */
	protected function send_200_continue() {
		ob_start();
		echo '0';
		http_response_code( 200 );
		header('Content-Encoding: none');
		header('Connection: close');
		header('Content-Length: ' . ob_get_length() );
		ob_end_flush();
		flush();
		session_write_close();
	}

	function api_send( $method, $url, $data = null ) {

		if( ! in_array( $method, array( 'get', 'post') ) ) {
			return new WP_Error( $this->plugin->string_ns . 'type_error', '$method must be one of \'get\', \'post\'' );
		}

		$req = Requests::{$method}( $url, array(), $data );
		$this->_last_request = $req;

		$decoded_body = json_decode( $req->body );
		$response_body = $decoded_body ? $decoded_body : $req->body;

		if( $req->success ) {
			return $response_body;
		} else {
			return new WP_Error( $this->plugin->string_ns . 'fb_api_error', $response_body );
		}
	}

	function _reply( $reply ) {
		if( ! is_array( $reply ) ) {

			return new WP_Error( $this->plugin->string_ns . 'type_error', 'Reply must be an array' );
		}

		$reply['recipient'] = $this->_sender;

		return $this->api_send( 'post', $this->messages_api, $reply );
	}

	function set_typing_on() {
		return $this->_reply( array(
			'sender_action' => 'typing_on',
		) );
	}

	function reply( $message, $set_typing_on = false ) {
		$return = $this->_reply( array( 'message' => $message ));

		if( $set_typing_on && true === $return ) {
			$this->set_typing_on();
		}

		return $return;
	}

	function reply_with_text( $text, $set_typing_on = false ) {
		return $this->reply( array( 'text' => $text ), $set_typing_on );
	}

	function reply_with_image_url( $url, $set_typing_on = false ) {
		return $this->reply( array(
			'attachment' => array(
				'type' => 'image',
				'payload' => array( 'url' => $url ),
			),
		), $set_typing_on );
	}

	function reply_with_buttons( $text, $buttons, $set_typing_on = false ) {
		return $this->reply( array(
			'attachment' => array(
				'type' => 'template',
				'payload' => array_merge( array(
					'template_type' => 'button',
					'buttons' => $buttons,
				), ( strlen( $text ) ) ? array( 'text' => $text ) : array() ),
			),
		), $set_typing_on );
	}

	function get_user_info( $fields = 'all' ) {
		if( 'all' === $fields ) {
			$fields = 'first_name,last_name,profile_pic,locale,timezone,gender';
		}

		return $this->api_send( 'get', $this->user_api . '&fields=' . urlencode( $fields ) );
	}



}
