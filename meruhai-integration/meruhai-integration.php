<?php
/**
 * Plugin Name: メール配信システム「める配くん」連携
 * Plugin URI: https://meruhaikum.com
 * Description: Contact Form 7の投稿内容をメール配信システム「める配くん」のAPIを実行して読者登録するプラグイン
 * Version: 1.0.0
 * Author: Delightful Inc.
 * License: GPL v2 or later
 * Text Domain: meruhai-integration
 * Network: false
 */

// プラグインの直接アクセスを防ぐ
if (!defined('ABSPATH')) {
  exit;
}

// プラグインの定数定義
define('MERUHAI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MERUHAI_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MERUHAI_VERSION', '1.0.0');

// クラスファイルの読み込み
require_once MERUHAI_PLUGIN_PATH . 'includes/class-meruhai-api.php';
require_once MERUHAI_PLUGIN_PATH . 'includes/class-meruhai-integration.php';

// プラグインの初期化
function meruhai_integration_init() {
  $meruhai = new MeruHai_Integration();
  $meruhai->init();
  
  if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('MeruHai Integration: Plugin initialization completed');
  }
}

// 管理画面でのみ初期化
add_action('admin_menu', 'meruhai_integration_init', 0);

// AJAXハンドラーを直接登録
add_action('wp_ajax_meruhai_test_connection', 'meruhai_integration_test_connection');
add_action('wp_ajax_meruhai_get_readers', 'meruhai_integration_get_readers');
add_action('wp_ajax_meruhai_save_mapping', 'meruhai_integration_save_mapping');

// AJAXハンドラー関数
function meruhai_integration_test_connection() {
  $meruhai = new MeruHai_Integration();
  $meruhai->test_connection();
}

function meruhai_integration_get_readers() {
  $meruhai = new MeruHai_Integration();
  $meruhai->get_readers();
}

function meruhai_integration_save_mapping() {
  $meruhai = new MeruHai_Integration();
  $meruhai->save_mapping();
}

// フロントエンド用の初期化（Contact Form 7のフック用）
function meruhai_integration_frontend_init() {
  if (!is_admin()) {
    $meruhai = new MeruHai_Integration();
    $meruhai->init_frontend();
  }
}
add_action('init', 'meruhai_integration_frontend_init');

// プラグインの設定リンクを追加
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'meruhai_integration_add_settings_link');
function meruhai_integration_add_settings_link($links) {
  $settings_link = '<a href="' . admin_url('options-general.php?page=meruhai-integration') . '">設定</a>';
  array_unshift($links, $settings_link);
  return $links;
}

// プラグインの行アクションに設定リンクを追加
add_filter('plugin_row_meta', 'meruhai_integration_add_row_meta', 10, 2);
function meruhai_integration_add_row_meta($links, $file) {
  if (plugin_basename(__FILE__) === $file) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=meruhai-integration') . '">設定</a>';
    $links[] = $settings_link;
  }
  return $links;
}

// プラグインの有効化時の処理
register_activation_hook(__FILE__, 'meruhai_integration_activate');
function meruhai_integration_activate() {
  // 必要なオプションの初期化
  add_option('meruhai_endpoint_url', '');
  add_option('meruhai_login_id', '');
  add_option('meruhai_password', '');
  add_option('meruhai_field_mapping', array());
  add_option('meruhai_csv_headers', array());
  
  // フラッシュリライトルール
  flush_rewrite_rules();
}

// プラグインの無効化時の処理
register_deactivation_hook(__FILE__, 'meruhai_integration_deactivate');
function meruhai_integration_deactivate() {
  // 必要に応じてクリーンアップ処理
}
