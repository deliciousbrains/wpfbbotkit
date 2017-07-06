<?php

class WPFBBotKit_Messaging {

	protected $sender;
	protected $message;
	protected $entry;
	protected $text;
	protected $postback;
	protected $page_access_token;
	protected $plugin;
	protected $last_request;

	public $fb_api_base = 'https://graph.facebook.com/v2.6';
	public $messages_api;
	public $user_api;

	function __construct( $entry, $plugin, $send_200 = true ) {
		$this->init_props( $entry, $plugin );
	}

	protected function init_props( $entry, $plugin ) {
		$this->plugin = $plugin;

		$this->page_access_token = $plugin->get_page_access_token();

		$this->entry = $entry;

		if ( isset( $entry['sender'] ) ) {
			$this->sender = $entry['sender'];
		}
		if ( isset( $entry['message'] ) ) {
			$this->message = $entry['message'];
			if ( isset( $entry['message']['text'] ) ) {
				$this->text = $entry['message']['text'];
			}
			if ( isset( $entry['message']['quick_reply'] ) ) {
				$this->postback = $entry['message']['quick_reply']['payload'];
			}
		}

		if ( isset( $entry['postback'] ) ) {
			$this->postback = $entry['postback']['payload'];
		}

		$this->messages_api = $this->fb_api_base . '/me/messages?access_token=' . urlencode( $this->page_access_token );
		$this->user_api     = $this->fb_api_base . '/' . $this->sender['id'] . '?access_token=' . urlencode( $this->page_access_token );
	}

	function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			return $this->{$name};
		}

		throw new Exception( "Can not get property: {$name}", 1 );
	}

	/**
	 * Attempts to send a 200 response to the requester efore continuing execution to
	 * ensure that Facebook doesn't retry the webhook while we're processing. It is
	 * recommended that you call `exit()` when done responding in order to prevent
	 * warnings from other parts of WP that might try to send headers
	 *
	 * TODO: Appears not to work with WordPress, but this would be really nice to have
	 */
	protected function send_200_continue() {
		ob_start();
		echo '0';
		http_response_code( 200 );
		header( 'Content-Encoding: none' );
		header( 'Connection: close' );
		header( 'Content-Length: ' . ob_get_length() );
		ob_end_flush();
		flush();
		session_write_close();
	}

	function api_send( $method, $url, $data = null ) {
		if ( ! in_array( $method, array( 'get', 'post' ) ) ) {
			return new WP_Error( 'wpfbbk_type_error', '$method must be one of \'get\', \'post\'' );
		}

		/**
		 * Opportunity to hijack API Requests.
		 *
		 * Ideal for sending requests via a queue rather than blocking execution by sending immediately.
		 *
		 * @param bool   $request_handled Return `true` to skip sending immediately via Requests API
		 * @param string $method          Request method (get or post)
		 * @param string $url             Request URL
		 * @param array  $data            Request data
		 *
		 */
		$request_handled = apply_filters( 'wpfbbk_before_send_request', false, $method, $url, $data );
		if ( false !== $request_handled ) {
			error_log( print_r( [ $request_handled ], 1 ) );

			return $request_handled;
		}

		$req                = Requests::{$method}( $url, array(), $data );
		$this->last_request = $req;

		$decoded_body  = json_decode( $req->body );
		$response_body = $decoded_body ? $decoded_body : $req->body;

		if ( $req->success ) {
			return $response_body;
		} else {
			return new WP_Error( 'wpfbbk_fb_api_error', $response_body );
		}
	}

	function _reply( $reply ) {
		if ( ! is_array( $reply ) ) {

			return new WP_Error( 'wpfbbk_type_error', 'Reply must be an array' );
		}

		$reply['recipient'] = $this->sender;

		return $this->api_send( 'post', $this->messages_api, $reply );
	}

	function set_typing_on() {
		return $this->reply( array(
			'sender_action' => 'typing_on',
		) );
	}

	function reply( $message, $set_typing_on = false ) {
		$return = $this->_reply( array( 'message' => $message ) );

		if ( $set_typing_on && true === $return ) {
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
				'type'    => 'image',
				'payload' => array( 'url' => $url ),
			),
		), $set_typing_on );
	}

	function reply_with_buttons( $text = '', $buttons, $set_typing_on = false ) {
		return $this->reply( array(
			'attachment' => array(
				'type'    => 'template',
				'payload' => array(
					'template_type' => 'button',
					'buttons'       => $buttons,
					'text'          => $text,
				),
			),
		), $set_typing_on );
	}

	function reply_with_generic_template_link( $title, $subtitle, $image, $url, $buttons = null, $set_typing_on = false ) {
		$reply = array(
			'attachment' => array(
				'type'    => 'template',
				'payload' => array(
					'template_type' => 'generic',
					'elements'      => array(
						array(
							'title'          => $title,
							'subtitle'       => $subtitle,
							'image_url'      => $image,
							'default_action' => array(
								'type' => 'web_url',
								'url'  => $url,
							),
							'buttons'        => $buttons,
						),
					),
				),
			),
		);

		return $this->reply( $reply, $set_typing_on );
	}

	function get_user_info( $fields = 'all' ) {
		if ( 'all' === $fields ) {
			$fields = 'first_name,last_name,profile_pic,locale,timezone,gender';
		}

		return $this->api_send( 'get', $this->user_api . '&fields=' . urlencode( $fields ) );
	}


}
