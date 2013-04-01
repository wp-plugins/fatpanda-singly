<?php
/*
Plugin Name: Singly for WordPress by Fat Panda
Plugin URI: http://singly.com
Description: Allow your users to login using Facebook, Twitter, Gmail, and 34 other services. 
Author: Fat Panda, LLC
Author URI: http://fatpandadev.com
Version: 0.1
License: GPL2
*/

/*
Copyright (C)2011 Fat Panda, LLC

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if (!defined('ABSPATH')) exit;

define('FPSINGLY', __FILE__);
define('FPS_DIR', dirname(__FILE__));
define('FPS_DEBUG', true);
define('FPS_REWRITE_RULE', 'auth/service/([a-z0-9]+)/?.*?$');

// support setting client id and secret in configuration
@define('FPS_SINGLY_CLIENT_ID', '');
@define('FPS_SINGLY_CLIENT_SECRET', '');

$fps_services = array(
  'facebook' => 'Facebook',
  'twitter' => 'Twitter',
  'google' => 'Google',
  'linkedin' => 'LinkedIn',
  'wordpress' => 'WordPress.com',
  'github' => 'Github',
  '37signals' => '37signals',
  'bodymedia' => 'BodyMedia',
  'dropbox' => 'Dropbox',
  'dwolla' => 'Dwolla',
  'flickr' => 'Flickr',
  'foursquare' => 'foursquare',
  'imgur' => 'imgur',
  'instagram' => 'Instagram',
  'klout' => 'Klout',
  'meetup' => 'Meetup',
  'paypal' => 'PayPal',
  'picasa' => 'Picasa',
  'rdio' => 'Rdio',
  'reddit' => 'Reddit',
  'runkeeper' => 'RunKeeper',
  'shutterfly' => 'Shutterfly',
  'soundcloud' => 'SoundCloud',
  'stocktwits' => 'StockTwits',
  'tout' => 'Tout',
  'tumblr' => 'Tumblr',
  'withings' => 'Withings',
  'yahoo' => 'Yahoo',
  'yammer' => 'Yammer',
  'youtube' => 'YouTube',
  'zeo' => 'Zeo'
);

add_action('activate_fatpanda-singly/plugin.php', 'fps_activate');
add_filter('rewrite_rules_array', 'fps_rewrite_rules_array');
add_filter('query_vars', 'fps_query_vars');
add_action(constant('WP_DEBUG') || constant('FPS_DEBUG') ? 'wp_loaded' : 'fps_activated', 'fps_flush_rewrite_rules');
add_action('parse_request', 'fps_parse_request');
add_action('login_footer', 'fps_login_footer');
add_action('admin_init', 'fps_admin_init');
add_action('get_avatar', 'fps_get_avatar', 10, 5);

/**
 * Simple model for access tokens obtained through Singly API
 */
class FpsAccessToken {

  public $service;
  public $access_token;
  public $account;

  function __construct($service, $auth_result) {
    $this->service = $service;
    $this->access_token = $auth_result->access_token;
    $this->account = $auth_result->account;
  }

}

function fps_get_avatar($avatar = '', $id_or_email, $size = 96, $default = '', $alt = false) {
  if ($id_or_email) {
    $user = get_user_by('id', $id_or_email);
    if (!$user || !$user->ID) {
      $user = get_user_by('email', $id_or_email);
    }
    if ($user && $user->ID) {
      if ($profile = get_user_meta($user->ID, 'fps:profile')) {
        if ($profile[0]->thumbnail_url) {
          $avatar = preg_replace("/src='.*?'/", "src='{$profile[0]->thumbnail_url}'", $avatar);
        }
      }
    }
  }

  return $avatar;
}

function fps_admin_init() {
  global $fps_services;
  add_settings_section('fps_settings_section', 'Singly Settings', 'fps_settings_section', 'general');
  register_setting('general', 'fps_singly_client_id');
  register_setting('general', 'fps_singly_client_secret');
  foreach($fps_services as $service => $label) {
    register_setting('general', "fps_service_{$service}_enabled");
  }
}

function fps_settings_section() {
  global $fps_services;
  require(FPS_DIR.'/settings.php'); 
}

/**
 * This activation hook simply triggers an action called "fps_activated" which,
 * if in debugging mode, will trigger rewrite rules flushing.
 */
function fps_activate() {
  do_action('fps_activated');
}

/**
 * Add our rewrite rule.
 */
function fps_rewrite_rules_array($rules) {
  return array(
    FPS_REWRITE_RULE => 'index.php?_fps=auth&_service=$matches[1]'
  ) + $rules;
}

/**
 * Add our query vars.
 */
function fps_query_vars($vars) {
  $vars[] = '_fps';
  $vars[] = '_service';
  return $vars;
}

/**
 * Make sure our rewrite rule is in place
 */
function fps_flush_rewrite_rules() {
  $rules = get_option( 'rewrite_rules' );
  if (!isset($rules[FPS_REWRITE_RULE])) {
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }
}

/**
 * What to do when our rewrite rule matches the current request
 */
function fps_parse_request($wp) {
  if (isset($wp->query_vars['_fps']) && isset($wp->query_vars['_service'])) {
    if (!get_option("fps_service_{$wp->query_vars['_service']}_enabled")) {
      wp_die("Oops! That service isn't supported.");
    }
    if (!empty($_REQUEST['error'])) {
      $error = json_decode($_REQUEST['error']);
      print_r($error);
      // TODO: log the error
      wp_die("Oops! Something went wrong. Please try again later.");
    } else if (!empty($_REQUEST['code'])) {
      $result = fps_singly_auth($_REQUEST['code']);
      if (is_wp_error($result) || $result->error) {
        // TODO: log the error
        wp_die("Oops! Something went wrong. Please try again later.");
      } else {
        $token = new FpsAccessToken($wp->query_vars['_service'], $result);
        if (is_wp_error($user = fps_wordpress_auth($token))) {
          // TODO: log the error
          if (isset($user->errors['existing_user_email'])) {
            wp_die("Oops! Your e-mail address is already registered.");
          } else {
            wp_die("Oops! Something went wrong. Please try again later.");
          }
        }
        wp_redirect($_REQUEST['redirect_uri']);
      }
    } else {
      if (!$keys = fps_get_keys()) {
        wp_die("Missing keys: Client ID and Client Secret. Setup not complete?");
      }
      $service_url = "https://api.singly.com/oauth/authorize?client_id=%s&redirect_uri=%s&service={$wp->query_vars['_service']}";
      if (!empty($_REQUEST['redirect_uri'])) {
        if ($_REQUEST['nonce'] === md5(AUTH_SALT.$_REQUEST['redirect_uri'])) {
          $redirect_uri = urlencode($_REQUEST['redirect_uri']);
        } else {
          $redirect_uri = '/';
        }
      } else {
        $redirect_uri = '/';
      }
      wp_redirect(sprintf($service_url, $keys->id, urlencode(get_bloginfo('siteurl')."/auth/service/{$wp->query_vars['_service']}?redirect_uri={$redirect_uri}")));
    }
    exit;
  }
}

/**
 * @return stdClass with properties "id" and "secret"
 */
function fps_get_keys() {
  if (defined('FPS_SINGLY_CLIENT_ID') && FPS_SINGLY_CLIENT_ID && defined('FPS_SINGLY_CLIENT_SECRET') && FPS_SINGLY_CLIENT_SECRET) {
    return (object) array(
      'id' => FPS_SINGLY_CLIENT_ID,
      'secret' => FPS_SINGLY_CLIENT_SECRET
    );
  } else if (($id = get_option('fps_singly_client_id')) && ($secret = get_option('fp_singly_client_secret'))) {
    return (object) array(
      'id' => $id,
      'secret' => $secret
    );
  }
}

/**
 * Given an auth code obtained via the front-end authentication workflow,
 * do a request to the Singly API to verify the code and exchange it for 
 * an access Token.
 * @return If there's a connection error, the return type is WP_Error.
 * If there is an authentication failure, the return type is stdClass with
 * property "error" containing the message. Otherwise the return type is
 * a stdClass with properties "access_token" and "account".
 */
function fps_singly_auth($code) {
  if (!$keys = fps_get_keys()) {
    return new WP_Error(0, 'Missing keys: Client ID and Client Secret. Setup not complete?');
  }

  $url = "https://api.singly.com/oauth/access_token";  
    
  $args = array(
    'body' => array(
      'client_id' => $keys->id,
      'client_secret' => $keys->secret,
      'code' => $code
    )
  );

  if (is_wp_error($result = wp_remote_post($url, $args))) {
    return $result;
  }

  return json_decode($result['body']);
}

/**
 * Load profile data from Singly, given a Singly access_token.
 * @return WP_Error or a stdClass containing the user's profile data,
 * aggregated across one or more services.
 */
function fps_get_profile($access_token) {
  $url = "https://api.singly.com/profile?access_token=".$access_token;  
    
  if (is_wp_error($result = wp_remote_get($url))) {
    return $result;
  }

  return json_decode($result['body']);
}

/**
 * Get a URL that will facilitate login via a specific service, e.g., "facebook"
 * @return String The URL
 */
function fps_get_login_url($service, $redirect_uri = '/', $secure = false) {
  $nonce = md5(AUTH_SALT.$redirect_uri);
  return site_url("/auth/service/{$service}?nonce={$nonce}&redirect_uri=".urlencode($redirect_uri), $secure ? 'https' : 'http');
}

/**
 * Given an FpsAccessToken, load and/or create an account, and authenticate it.
 * @return WP_Error or WP_User, as appropriate.
 */
function fps_wordpress_auth($token) {
  require_once(ABSPATH . 'wp-admin/includes/user.php');
    
  global $wpdb;

  if (is_wp_error($profile = fps_get_profile($token->access_token))) {
    return $profile;
  }

  $user_id = $wpdb->get_var( $wpdb->prepare("
    SELECT user_id FROM $wpdb->usermeta 
    WHERE 
      meta_key = %s
      AND meta_value = %s
  ", 
    'fps:service:singly',
    $token->account
  ) );

  // TODO: is user blocked?

  // user already exists
  if ($user_id) {
    $user = get_user_to_edit($user_id);

    // make sure this user exists
    if (!$user->ID) {
      // clean up dead meta data
      $wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->usermeta WHERE user_id = %s", $user_id) );
      $user = false;
    }
  }

  // user doesn't exist -- create it
  if (!$user) {
    $userdata = array(
      'user_login' => 'singly_'.$profile->id,
      'user_email' => $profile->email ? $profile->email : $profile->id . '@api.singly.com',
      'user_nicename' => $profile->handle ? $profile->handle : $profile->name,
      'display_name' => $profile->name ? $profile->name : $profile->handle,
      'user_url' => $profile->url,
      'user_pass' => md5(uniqid())
    );

    $user_id = wp_insert_user($userdata);

    if (is_wp_error($user_id)) {
      return $user_id;
    } else {
      $user = get_user_to_edit($user_id);
    }
  } 

  update_user_meta($user->ID, 'fps:service:singly', $token->account);
  update_user_meta($user->ID, 'fps:access_token:singly', $token->access_token);
  update_user_meta($user->ID, 'fps:profile', $profile);
  
  // user exists, make sure it's up-to-date, then login
  if (!$user->data->role) {
    // TODO: filter for new users
    $user->data->role = 'subscriber';
  }
  $user->data->user_nicename = $profile->handle ? $profile->handle : $profile->name;
  $user->data->user_url = $profile->url;
  
  // TODO: consider figuring out how to update default thumbnail_url

  $to_save = (array) $user->data;
  // don't update description
  unset($to_save['description']);
  wp_update_user($to_save);

  wp_set_auth_cookie($user->ID);
  
  return get_user_by('id', $user_id);
}

/**
 * Add social login options to the login screen.
 */
function fps_login_footer() {
  global $fps_services;
  require(FPS_DIR.'/login-footer.php');
}

// TODO: add block user checkbox to user mgmt screen