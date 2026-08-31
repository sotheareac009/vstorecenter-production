<?php
/**
 * Plugin Name: VSC S3 Offload
 * Description: Serves wp-content/uploads from S3/CloudFront and pushes new uploads to the bucket. Local files are kept as a fallback.
 * Author: vStoreCenter
 * Version: 1.0.0
 *
 * Configuration lives in .env (already loaded by wp-config.php):
 *
 *   S3_OFFLOAD_ENABLED=1                     # master switch; 0 = fully inert
 *   S3_BUCKET=vstore-media
 *   S3_REGION=ap-southeast-1
 *   S3_PREFIX=wp-content/uploads             # key prefix inside the bucket
 *   S3_MEDIA_BASE_URL=https://dxxxx.cloudfront.net/wp-content/uploads
 *   AWS_ACCESS_KEY_ID=...
 *   AWS_SECRET_ACCESS_KEY=...
 *   S3_REWRITE_CONTENT=1                     # optional: rewrite hardcoded upload URLs in content
 *   S3_MAX_UPLOAD_MB=100                     # skip pushing files larger than this
 */

defined( 'ABSPATH' ) || exit;

/* ---------------------------------------------------------------- config */

/**
 * Read .env directly as a fallback. Production's wp-config.php may not carry the
 * .env loader this repo's copy has, so never depend on getenv() alone.
 */
function vsc_s3_env_file() {
	static $vars = null;
	if ( null !== $vars ) {
		return $vars;
	}
	$vars = array();
	$path = ABSPATH . '.env';
	if ( is_readable( $path ) ) {
		foreach ( (array) file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $k, $v ) = explode( '=', $line, 2 );
			$vars[ trim( $k ) ] = trim( $v, " \t\n\r\0\x0B\"'" );
		}
	}
	return $vars;
}

function vsc_s3_cfg( $key, $default = '' ) {
	$v = getenv( $key );
	if ( false === $v || '' === $v ) {
		$file = vsc_s3_env_file();
		$v    = isset( $file[ $key ] ) ? $file[ $key ] : false;
	}
	// .env values often carry stray whitespace or quotes — never trust them raw.
	$v = is_string( $v ) ? trim( $v, " \t\n\r\0\x0B\"'" ) : $v;
	return ( false === $v || '' === $v ) ? $default : $v;
}

function vsc_s3_enabled() {
	return '1' === (string) vsc_s3_cfg( 'S3_OFFLOAD_ENABLED', '0' );
}

/** Offload is only usable when every required value is present. */
function vsc_s3_ready() {
	if ( ! vsc_s3_enabled() ) {
		return false;
	}
	foreach ( array( 'S3_BUCKET', 'S3_REGION', 'AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY' ) as $k ) {
		if ( '' === vsc_s3_cfg( $k ) ) {
			return false;
		}
	}
	return true;
}

function vsc_s3_prefix() {
	return trim( vsc_s3_cfg( 'S3_PREFIX', 'wp-content/uploads' ), '/' );
}

/** Public base URL for media — point at S3 now, CloudFront later, one var. */
function vsc_s3_base_url() {
	return rtrim( vsc_s3_cfg( 'S3_MEDIA_BASE_URL' ), '/' );
}

/* ------------------------------------------------------------ URL rewrite */

/**
 * Rewrite the uploads base URL only. 'basedir'/'path' stay local so WordPress
 * keeps writing to disk — that local copy is our rollback.
 */
add_filter( 'upload_dir', 'vsc_s3_upload_dir', 100 );
function vsc_s3_upload_dir( $uploads ) {
	$base = vsc_s3_base_url();
	if ( ! vsc_s3_enabled() || '' === $base ) {
		return $uploads;
	}
	$uploads['baseurl'] = $base;
	$uploads['url']     = $base . ( isset( $uploads['subdir'] ) ? $uploads['subdir'] : '' );
	return $uploads;
}

/**
 * Optional: rewrite upload URLs hardcoded into post content / page-builder data.
 * Covers the production and local hostnames baked into older content.
 */
add_action( 'init', 'vsc_s3_maybe_filter_content' );
function vsc_s3_maybe_filter_content() {
	if ( ! vsc_s3_enabled() || '1' !== (string) vsc_s3_cfg( 'S3_REWRITE_CONTENT', '0' ) || '' === vsc_s3_base_url() ) {
		return;
	}
	foreach ( array( 'the_content', 'widget_text', 'post_thumbnail_html', 'elementor/frontend/the_content' ) as $hook ) {
		add_filter( $hook, 'vsc_s3_rewrite_urls', 20 );
	}
}

function vsc_s3_rewrite_urls( $html ) {
	if ( ! is_string( $html ) || '' === $html ) {
		return $html;
	}
	$base = vsc_s3_base_url();
	// Any scheme/host followed by /wp-content/uploads/ becomes the CDN base.
	return preg_replace( '#https?://[^/"\']+/wp-content/uploads/#i', $base . '/', $html );
}

/* --------------------------------------------------------- push to bucket */

/** Images: fires once metadata (and every thumbnail size) exists. */
add_filter( 'wp_generate_attachment_metadata', 'vsc_s3_push_attachment', 20, 2 );
function vsc_s3_push_attachment( $metadata, $attachment_id ) {
	if ( ! vsc_s3_ready() ) {
		return $metadata;
	}

	$uploads = wp_get_upload_dir();
	$basedir = trailingslashit( $uploads['basedir'] );
	$files   = array();

	$main = get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( $main ) {
		$files[] = $main;
	}

	// Every generated size sits beside the original.
	if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) && $main ) {
		$dir = trailingslashit( dirname( $main ) );
		if ( './' === $dir ) {
			$dir = '';
		}
		foreach ( $metadata['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$files[] = $dir . $size['file'];
			}
		}
	}

	foreach ( array_unique( $files ) as $rel ) {
		vsc_s3_put_file( $basedir . $rel, vsc_s3_prefix() . '/' . $rel );
	}

	return $metadata;
}

/** Non-image uploads (PDF, zip) never reach wp_generate_attachment_metadata sizes. */
add_filter( 'wp_handle_upload', 'vsc_s3_push_plain_upload', 20, 2 );
function vsc_s3_push_plain_upload( $upload, $context = '' ) {
	if ( ! vsc_s3_ready() || empty( $upload['file'] ) ) {
		return $upload;
	}
	$uploads = wp_get_upload_dir();
	$basedir = trailingslashit( $uploads['basedir'] );
	if ( 0 === strpos( $upload['file'], $basedir ) ) {
		$rel = substr( $upload['file'], strlen( $basedir ) );
		vsc_s3_put_file( $upload['file'], vsc_s3_prefix() . '/' . $rel );
	}
	return $upload;
}

/** Keep the bucket tidy when media is deleted in WP. */
add_action( 'delete_attachment', 'vsc_s3_delete_attachment' );
function vsc_s3_delete_attachment( $attachment_id ) {
	if ( ! vsc_s3_ready() ) {
		return;
	}
	$main = get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( ! $main ) {
		return;
	}
	$meta = wp_get_attachment_metadata( $attachment_id );
	$keys = array( $main );
	if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
		$dir = trailingslashit( dirname( $main ) );
		if ( './' === $dir ) {
			$dir = '';
		}
		foreach ( $meta['sizes'] as $size ) {
			if ( ! empty( $size['file'] ) ) {
				$keys[] = $dir . $size['file'];
			}
		}
	}
	foreach ( array_unique( $keys ) as $rel ) {
		vsc_s3_request( 'DELETE', vsc_s3_prefix() . '/' . $rel, '' );
	}
}

/* ------------------------------------------------------- S3 SigV4 client */

/** Upload one local file to a bucket key. Returns true on success. */
function vsc_s3_put_file( $path, $key ) {
	if ( ! is_readable( $path ) ) {
		vsc_s3_log( "missing local file: $path" );
		return false;
	}

	$max = (int) vsc_s3_cfg( 'S3_MAX_UPLOAD_MB', '100' ) * 1024 * 1024;
	if ( $max > 0 && filesize( $path ) > $max ) {
		vsc_s3_log( "skipped (over {$max} bytes): $path" );
		return false;
	}

	$body = file_get_contents( $path );
	if ( false === $body ) {
		vsc_s3_log( "unreadable: $path" );
		return false;
	}

	$type = wp_check_filetype( $path );
	$mime = $type['type'] ? $type['type'] : 'application/octet-stream';

	return vsc_s3_request( 'PUT', $key, $body, $mime );
}

/**
 * Signed S3 request using SigV4 — no AWS SDK, no Composer.
 */
function vsc_s3_request( $method, $key, $body = '', $mime = 'application/octet-stream' ) {
	$bucket = vsc_s3_cfg( 'S3_BUCKET' );
	$region = vsc_s3_cfg( 'S3_REGION' );
	$access = vsc_s3_cfg( 'AWS_ACCESS_KEY_ID' );
	$secret = vsc_s3_cfg( 'AWS_SECRET_ACCESS_KEY' );

	if ( ! $bucket || ! $region || ! $access || ! $secret ) {
		return false;
	}

	$host = "{$bucket}.s3.{$region}.amazonaws.com";

	// Encode each path segment but keep the separators.
	$canonical_uri = '/' . implode( '/', array_map( 'rawurlencode', explode( '/', ltrim( $key, '/' ) ) ) );

	$amz_date  = gmdate( 'Ymd\THis\Z' );
	$datestamp = gmdate( 'Ymd' );
	$payload   = hash( 'sha256', $body );

	$headers = array(
		'content-type'         => $mime,
		'host'                 => $host,
		'x-amz-content-sha256' => $payload,
		'x-amz-date'           => $amz_date,
	);
	ksort( $headers );

	$canonical_headers = '';
	foreach ( $headers as $k => $v ) {
		$canonical_headers .= $k . ':' . trim( $v ) . "\n";
	}
	$signed_headers = implode( ';', array_keys( $headers ) );

	$canonical_request = implode( "\n", array(
		$method,
		$canonical_uri,
		'',
		$canonical_headers,
		$signed_headers,
		$payload,
	) );

	$scope        = "{$datestamp}/{$region}/s3/aws4_request";
	$string_to_sign = implode( "\n", array(
		'AWS4-HMAC-SHA256',
		$amz_date,
		$scope,
		hash( 'sha256', $canonical_request ),
	) );

	$k_date    = hash_hmac( 'sha256', $datestamp, 'AWS4' . $secret, true );
	$k_region  = hash_hmac( 'sha256', $region, $k_date, true );
	$k_service = hash_hmac( 'sha256', 's3', $k_region, true );
	$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service, true );
	$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );

	$authorization = "AWS4-HMAC-SHA256 Credential={$access}/{$scope}, "
		. "SignedHeaders={$signed_headers}, Signature={$signature}";

	$response = wp_remote_request( "https://{$host}{$canonical_uri}", array(
		'method'  => $method,
		'timeout' => 60,
		'headers' => array(
			'Authorization'        => $authorization,
			'Content-Type'         => $mime,
			'x-amz-content-sha256' => $payload,
			'x-amz-date'           => $amz_date,
		),
		'body'    => $body,
	) );

	if ( is_wp_error( $response ) ) {
		vsc_s3_log( "$method $key failed: " . $response->get_error_message() );
		return false;
	}

	$code = wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		vsc_s3_log( "$method $key HTTP $code: " . substr( wp_remote_retrieve_body( $response ), 0, 400 ) );
		return false;
	}

	return true;
}

function vsc_s3_log( $msg ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[VSC S3] ' . $msg );
	}
}

/* --------------------------------------------------------- admin warning */

add_action( 'admin_notices', 'vsc_s3_admin_notice' );
function vsc_s3_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) || ! vsc_s3_enabled() ) {
		return;
	}
	$problems = array();
	if ( ! vsc_s3_ready() ) {
		$problems[] = 'AWS credentials or bucket settings are missing — new uploads are NOT reaching S3.';
	}
	if ( '' === vsc_s3_base_url() ) {
		$problems[] = 'S3_MEDIA_BASE_URL is empty — media is still served from this server.';
	}
	if ( $problems ) {
		echo '<div class="notice notice-warning"><p><strong>VSC S3 Offload:</strong> ' . esc_html( implode( ' ', $problems ) ) . '</p></div>';
	}
}
