<?php

class WPFBBotKit_Messaging {

	protected $sender;
	protected $message;
	protected $entry;
	protected $text;
	protected $postback;
	protected $payload_type;
	protected $page_access_token;
	protected $plugin;
	protected $last_request;

	public $fb_api_base = 'https://graph.facebook.com/v2.6';
	public $messages_api;
	public $user_api;

	function __construct( $entry, $plugin, $send_200 = true ) {
		$this->plugin            = $plugin;
		$this->page_access_token = $plugin->get_page_access_token();
		$this->entry             = $entry;

		if ( isset( $entry['sender'] ) ) {
			$this->sender = $entry['sender'];
		}
		if ( isset( $entry['message'] ) ) {
			$this->message = $entry['message'];
			if ( isset( $entry['message']['text'] ) ) {
				$this->text = $entry['message']['text'];
			}
			if ( isset( $entry['message']['quick_reply'] ) ) {
				$this->postback     = $entry['message']['quick_reply']['payload'];
				$this->payload_type = 'quick_reply';
			}
		}

		if ( isset( $entry['postback'] ) ) {
			$this->postback     = $entry['postback']['payload'];
			$this->payload_type = 'postback';
		}

		$this->messages_api = $this->fb_api_base . '/me/messages?access_token=' . urlencode( $this->page_access_token );
		$this->user_api     = $this->fb_api_base . '/' . $this->sender['id'] . '?access_token=' . urlencode( $this->page_access_token );
	}

	/**
	 * Make protected properties gettable so they're read-only
	 *
	 * @param $name
	 *
	 * @return mixed
	 * @throws Exception
	 */
	function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			return $this->{$name};
		}

		throw new Exception( "Can not get property: {$name}", 1 );
	}

	/**
	 * Attempts to send a 200 response to the requester before continuing execution to
	 * ensure that Facebook doesn't retry the webhook while we're processing. It is
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

	/**
	 * Send data to the specified API URL and handle the response
	 *
	 * @param string $method POST or GET
	 * @param string $url
	 * @param null   $data
	 *
	 * @return mixed|WP_Error
	 */
	function api_send( $method, $url, $data = null ) {
		if ( ! in_array( strtolower( $method ), array( 'get', 'post' ) ) ) {
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

	/**
	 * Send reply to current sender via Messages API.
	 *
	 * @param array $message       formatted message to send to current sender
	 * @param bool  $set_typing_on Should 'typing_on' action be sent after message to indicate further messages will be sent
	 *
	 * @return mixed|WP_Error
	 */
	function reply( $message, $set_typing_on = false ) {
		$return = $this->api_send( 'post', $this->messages_api, array( 'message' => $message,' recipient' => $this->sender ) );

		if ( $set_typing_on && true === $return ) {
			$this->set_typing_on();
		}

		return $return;
	}

	/**
	 * Sends the 'typing_on' action to the current sender to show that bot is working/typing.
	 * Typically indicates that more messages will be sent.
	 *
	 * @return mixed|WP_Error
	 */
	function set_typing_on() {
		return $this->reply( array(
			'sender_action' => 'typing_on',
		), false );
	}

	/**
	 * Sends a plain text reply to current sender.
	 *
	 * @param string $text          message to send
	 * @param bool   $set_typing_on Should 'typing_on' action be sent after message to indicate further messages will be sent
	 *
	 * @return mixed|WP_Error
	 */
	function reply_with_text( $text, $set_typing_on = false ) {
		return $this->reply( array( 'text' => $text ), $set_typing_on );
	}

	/**
	 * Sends an image to current sender. Image url must be HTTPS with valid cert.
	 * Todo: add is_reusable param and handle attachment_id response
	 *
	 * @param string $url           Valid HTTPS url of image (or,
	 * @param bool   $set_typing_on Should 'typing_on' action be sent after message to indicate further messages will be sent
	 *
	 * @return mixed|WP_Error
	 */
	function reply_with_image_url( $url, $set_typing_on = false ) {
		return $this->reply( array(
			'attachment' => array(
				'type'    => 'image',
				'payload' => array( 'url' => $url ),
			),
		), $set_typing_on );
	}

	/**
	 * Sends a button or multiple buttons along with text message to current sender
	 *
	 * @param string $text          Text message that will preceed buttons
	 * @param array  $buttons       Array of buttons as defined at
	 *                              https://developers.facebook.com/docs/messenger-platform/send-api-reference/buttons
	 * @param bool   $set_typing_on Should 'typing_on' action be sent after message to indicate further messages will
	 *                              be sent
	 *
	 * @return mixed|WP_Error
	 */
	function reply_with_buttons( $text = '', $buttons, $set_typing_on = false ) {
		return $this->reply( array(
			'attachment' => array(
				'type' => 'template',
				'payload' => array(
					'template_type' => 'button',
					'buttons'       => $buttons,
					'text'          => $text,
				),
			),
		), $set_typing_on );
	}

	/**
	 * Sends a "generic template" to current sender. Specifically a version of the generic template with a clickable
	 * image that leads to aa url An array of buttons can also be sent with additional links or postback actions
	 *
	 * @param string $title         Title of card that will be overlaid on top of image in large text
	 * @param string $subtitle      Subtitle, will be smaller text just below title
	 * @param string $image         Image url or reusable attachment_id of image for card
	 * @param string $url           Web url for card link
	 * @param null   $buttons       Array of buttons as defined at
	 *                              https://developers.facebook.com/docs/messenger-platform/send-api-reference/buttons
	 * @param bool   $set_typing_on Should 'typing_on' action be sent after message to indicate further messages will
	 *                              be sent
	 *
	 * @return mixed|WP_Error
	 */
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

	/**
	 * Sends a request to the Facebook User API to request more information about current sender.
	 * Fields are not guaranteed to be populated, so it is recommended to check that values
	 * are set and provide fallback text when utilizing user info in messages.
	 *
	 * @param string $fields
	 *
	 * @return stdClass|WP_Error
	 */
	function get_user_info( $fields = 'all' ) {
		if ( 'all' === $fields ) {
			$fields = 'first_name,last_name,profile_pic,locale,timezone,gender';
		}

		return $this->api_send( 'get', $this->user_api . '&fields=' . urlencode( $fields ) );
	}


}
