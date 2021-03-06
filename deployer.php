<?php
//deployer script for
	$content = file_get_contents( "php://input" );
	$json    = json_decode( $content, true );
	$file    = fopen( LOGFILE, "a" );
	$time    = time();
	$token   = false;
	$DIR     = preg_match( "/\/$/", DEPLOYMENT_DIR ) ? DEPLOYMENT_DIR : DEPLOYMENT_DIR . "/";

// retrieve the token
	if ( ! $token && isset( $_SERVER["HTTP_X_HUB_SIGNATURE"] ) ) {
		list( $algo, $token ) = explode( "=", $_SERVER["HTTP_X_HUB_SIGNATURE"], 2 ) + array( "", "" );
	} elseif ( isset( $_SERVER["HTTP_X_GITLAB_TOKEN"] ) ) {
		$token = $_SERVER["HTTP_X_GITLAB_TOKEN"];
	} elseif ( isset( $_GET["token"] ) ) {
		$token = $_GET["token"];
	}

// log the time
	date_default_timezone_set( "UTC" );
	fputs( $file, date( "d-m-Y (H:i:s)", $time ) . "\n" );

// function to forbid access
	function forbid( $file, $reason ) {
		// explain why
		if ( $reason ) {
			fputs( $file, "=== ERROR: " . $reason . " ===\n" );
		}
		fputs( $file, "*** ACCESS DENIED ***" . "\n\n\n" );
		fclose( $file );

		// forbid
		header( "HTTP/1.0 403 Forbidden" );
		exit;
	}

// function to return OK
	function ok() {
		ob_start();
		header( "HTTP/1.1 200 OK" );
		header( "Connection: close" );
		header( "Content-Length: " . ob_get_length() );
		ob_end_flush();
		ob_flush();
		flush();
	}

// Check for a GitHub signature
	if ( ! empty( TOKEN ) && isset( $_SERVER["HTTP_X_HUB_SIGNATURE"] ) && $token !== hash_hmac( $algo, $content, TOKEN ) ) {
		forbid( $file, "X-Hub-Signature does not match TOKEN" );
// Check for a GitLab token
	} elseif ( ! empty( TOKEN ) && isset( $_SERVER["HTTP_X_GITLAB_TOKEN"] ) && $token !== TOKEN ) {
		forbid( $file, "X-GitLab-Token does not match TOKEN" );
// Check for a $_GET token
	} elseif ( ! empty( TOKEN ) && isset( $_GET["token"] ) && $token !== TOKEN ) {
		forbid( $file, "\$_GET[\"token\"] does not match TOKEN" );
// if none of the above match, but a token exists, exit
	} elseif ( ! empty( TOKEN ) && ! isset( $_SERVER["HTTP_X_HUB_SIGNATURE"] ) && ! isset( $_SERVER["HTTP_X_GITLAB_TOKEN"] ) && ! isset( $_GET["token"] ) ) {
		forbid( $file, "No token detected" );
	} else {
		// check if pushed branch matches branch specified in config
		foreach ( $json["push"]["changes"] as $change ) {
			if (
				(
					isset( $change["old"]["type"] )
					&& isset( $change["old"]["name"] )
					&& $change["old"]["type"] == "branch"
					&& $change["old"]["name"] == "master"
				)
				|| (
					isset( $change["new"]["type"] )
					&& isset( $change["new"]["name"] )
					&& $change["new"]["type"] == "branch"
					&& $change["new"]["name"] == "master"
				)
			) {
				exec( "git pull ".REMOTE_REPOSITORY." ".YOUR_BRANCH_NAME );
				die( "Complete." );
			}
		}
	}

	fputs( $file, "\n\n" . PHP_EOL );
	fclose( $file );
