<?php

return [
	/*
	  |--------------------------------------------------------------------------
	  | Third Party Services
	  |--------------------------------------------------------------------------
	  |
	  | This file is for storing the credentials for third party services such
	  | as Mailgun, Postmark, AWS and more. This file provides the de facto
	  | location for this type of information, allowing packages to have
	  | a conventional file to locate the various service credentials.
	  |
	 */

	'postmark'	=> [
		'token' => env('POSTMARK_TOKEN'),
	],
	'resend'	=> [
		'key' => env('RESEND_KEY'),
	],
	'ses'		=> [
		'key'	 => env('AWS_ACCESS_KEY_ID'),
		'secret' => env('AWS_SECRET_ACCESS_KEY'),
		'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
	],
	'slack'		=> [
		'notifications' => [
			'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
			'channel'			   => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
		],
	],
	'recaptcha' => [
		'site_key'	 => env('RECAPTCHA_SITE_KEY'),
		'secret_key' => env('RECAPTCHA_SECRET_KEY'),
	],
	'wompi'		=> [
		'client_id'		   => env('WOMPI_PUBLIC_KEY'),
		'client_secret'    => env('WOMPI_SECRET_KEY'),
		'base_url'		   => env('WOMPI_API_URL', 'https://api.wompi.sv/v1'),
		'secret_events'    => env('WOMPI_SECRET_EVENTS'),
		'secret_integrity' => env('WOMPI_SECRET_INTEGRITY'),
	],
];
