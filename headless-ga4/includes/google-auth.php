<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base64url encodes data.
 *
 * @param string $value Raw string.
 *
 * @return string
 */
function headless_ga4_base64url_encode($value)
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/**
 * Builds a JWT assertion for the Google OAuth service account flow.
 *
 * @param array<string, string> $credentials Validated service account credentials.
 *
 * @return string|WP_Error
 */
function headless_ga4_build_jwt_assertion($credentials)
{
    if (!function_exists('openssl_sign')) {
        return new WP_Error(
            'headless_ga4_openssl_missing',
            __('OpenSSL is required to sign the Google service account JWT.', 'headless-ga4')
        );
    }

    $issued_at = time();
    $expires_at = $issued_at + HOUR_IN_SECONDS;

    $header = array(
        'alg' => 'RS256',
        'typ' => 'JWT',
    );

    $claims = array(
        'iss'   => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud'   => $credentials['token_uri'],
        'iat'   => $issued_at,
        'exp'   => $expires_at,
    );

    $segments = array(
        headless_ga4_base64url_encode((string) wp_json_encode($header)),
        headless_ga4_base64url_encode((string) wp_json_encode($claims)),
    );

    $signing_input = implode('.', $segments);
    $signature = '';
    $private_key = openssl_pkey_get_private($credentials['private_key']);

    if (!$private_key) {
        return new WP_Error(
            'headless_ga4_private_key_invalid',
            __('The service account private key is invalid.', 'headless-ga4')
        );
    }

    $signed = openssl_sign($signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256);

    if (!$signed) {
        return new WP_Error(
            'headless_ga4_signing_failed',
            __('The service account JWT could not be signed.', 'headless-ga4')
        );
    }

    $segments[] = headless_ga4_base64url_encode($signature);

    return implode('.', $segments);
}

/**
 * Requests a Google OAuth access token from the service account credentials.
 *
 * @return string|WP_Error
 */
function headless_ga4_request_access_token()
{
    $credentials = headless_ga4_get_service_account_credentials();

    if (is_wp_error($credentials)) {
        return $credentials;
    }

    $assertion = headless_ga4_build_jwt_assertion($credentials);

    if (is_wp_error($assertion)) {
        return $assertion;
    }

    $response = wp_remote_post(
        esc_url_raw($credentials['token_uri']),
        array(
            'timeout' => 15,
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $assertion,
            ),
        )
    );

    if (is_wp_error($response)) {
        return new WP_Error(
            'headless_ga4_token_request_failed',
            $response->get_error_message()
        );
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $payload = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($payload) || empty($payload['access_token'])) {
        $message = is_array($payload) && !empty($payload['error_description'])
            ? (string) $payload['error_description']
            : __('Google OAuth token request failed.', 'headless-ga4');

        return new WP_Error('headless_ga4_token_invalid', $message);
    }

    $token = (string) $payload['access_token'];
    $expires_in = isset($payload['expires_in']) ? (int) $payload['expires_in'] : HOUR_IN_SECONDS;

    headless_ga4_set_cached_access_token($token, $expires_in);

    return $token;
}

/**
 * Returns a Google OAuth access token, using the transient cache when possible.
 *
 * @param bool $force_refresh Whether to bypass the token cache.
 *
 * @return string|WP_Error
 */
function headless_ga4_get_access_token($force_refresh = false)
{
    if (!$force_refresh) {
        $cached_token = headless_ga4_get_cached_access_token();

        if ($cached_token !== '') {
            return $cached_token;
        }
    }

    return headless_ga4_request_access_token();
}
