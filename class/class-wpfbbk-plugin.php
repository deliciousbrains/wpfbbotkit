<?php

class WPFBBotKit_Plugin {

	protected $string_ns = 'wpfbbk_';
	protected $api_namespace;
	protected $access_token;

	function __construct() {
		$this->register_hooks();
	}

	function __get( $name ) {
		if ( property_exists( $this, $name ) ) {
			return $this->{$name};
		}

		throw new Exception( "Can not get property: {$name}", 1 );
	}

	function register_hooks() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ), 10 );
		add_action( 'admin_init', array( $this, 'save_page_access_token' ) );
	}

	function admin_menu() {
		add_options_page(
			'WPFBBotKit',
			'Messenger Bot',
			'manage_options',
			'wpfbbotkit',
			array( $this, 'admin_page' )
		);
	}

	function admin_page() {
		require dirname( __FILE__ ) . '/../templates/admin_page.php';
	}

	function save_page_access_token() {
		if ( isset( $_POST['wpfbbk_access_token'] ) && check_admin_referer( 'wpfbbk_save_page_access_token' ) ) {
			$this->set_page_access_token( $_POST['wpfbbk_access_token'] );
		}
	}

	function get_page_access_token() {
		if ( ! $this->access_token ) {
			$this->access_token = get_site_option( 'wpfbbk_page_access_token' );
		}

		return $this->access_token;
	}

	function set_page_access_token( $token ) {
		$this->access_token = $token;

		return update_site_option( 'wpfbbk_page_access_token', trim( $token ) );
	}

	function get_verification_string() {
		$transient_name = 'wpfbbk_verification_string';
		if ( ! $verification_string = get_site_transient( $transient_name ) ) {
			$verification_string = md5( $this->string_ns . time() );
			set_site_transient( $transient_name, $verification_string );
		}

		return $verification_string;
	}

	function rest_api_init() {
		$webhook_url = $this->get_webhook_url( true );
		register_rest_route( $webhook_url['namespace'], $webhook_url['endpoint'], array(
			'methods'  => 'GET,POST',
			'callback' => array( $this, 'receive_api_request' ),
		) );
	}

	function get_webhook_url( $parts = false ) {
		$base_url    = network_site_url();
		$namespace   = apply_filters( 'wpfbbk_api_namespace', trim( $this->string_ns, '_' ) );
		$endpoint    = apply_filters( 'wpfbbk_api_endpoint', 'webhook' );
		$rest_prefix = rest_get_url_prefix();

		$url_parts = compact( 'base_url', 'rest_prefix', 'namespace', 'endpoint' );

		if ( $parts ) {
			return $url_parts;
		}

		return implode( '/', $url_parts );
	}

	function receive_api_request( $req ) {
		do_action( 'wpfbbk_request_received', $req );

		$method = $req->get_method();

		if ( 'GET' === $method && isset( $req['hub_mode'] ) && 'subscribe' == $req['hub_mode'] ) {
			$this->verify_webhook_subscription( $req );
		}

		if ( 'POST' === $method && isset( $req['object'] ) && 'page' === $req['object'] && isset( $req['entry'] ) ) {
			require_once dirname( __FILE__ ) . '/class-wpfbbk-messaging.php';

			$entries = $req['entry'];
			if ( ! is_array( $entries ) ) {
				$entries = array( $entries );
			}

			foreach ( $entries as $entry ) {
				if ( ! isset( $entry['messaging'] ) ) {
					continue;
				}
				if ( ! is_array( $entry['messaging'] ) ) {
					$entry['messaging'] = array( $entry['messaging'] );
				}
				foreach ( $entry['messaging'] as $message ) {
					do_action( 'wpfbbk_message_received', new WPFBBotKit_Messaging( $message, $this ) );
				}
			}
		}
	}

	function verify_webhook_subscription( $req ) {
		if ( $this->get_verification_string() === $req['hub_verify_token'] ) {
			http_response_code( 200 );
			update_site_option( 'wpfbbk_verified', true );
			exit( $req['hub_challenge'] );
		} else {
			error_log( 'Recieved invalid webhook validation request. Expected: "' . $this->get_verification_string . '" Received: "' . $req['hub_verify_token'] . '"' );
			http_response_code( 403 );
			exit( 0 );
		}
	}

}
