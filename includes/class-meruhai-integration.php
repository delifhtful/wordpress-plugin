<?php
/**
 * メインクラス
 */
class MeruHai_Integration {
  
  public function init() {
    // 管理画面の初期化
    if (is_admin()) {
      add_action('admin_menu', array($this, 'add_admin_menu'), 10);
      add_action('admin_init', array($this, 'admin_init'));
    }
    
    // Contact Form 7のフック（プラグインが有効な場合のみ）
    if (class_exists('WPCF7_ContactForm')) {
      add_action('wpcf7_mail_sent', array($this, 'handle_form_submission'));
    }
    
    // AJAX処理はメインファイルで直接登録
  }
  
  /**
   * フロントエンド用の初期化
   */
  public function init_frontend() {
    // Contact Form 7のフック（プラグインが有効な場合のみ）
    if (class_exists('WPCF7_ContactForm')) {
      add_action('wpcf7_mail_sent', array($this, 'handle_form_submission'));
    }
  }
  
  /**
   * 管理画面メニューの追加
   */
  public function add_admin_menu() {
    // 権限チェック
    if (!current_user_can('manage_options')) {
      return;
    }
    
    $hook = add_options_page(
      'める配くん連携設定',
      'める配くん連携',
      'manage_options',
      'meruhai-integration',
      array($this, 'admin_page')
    );
    
  }
  
  /**
   * 管理画面の初期化
   */
  public function admin_init() {
     // 設定を登録
    register_setting('meruhai_settings', 'meruhai_endpoint_url');
    register_setting('meruhai_settings', 'meruhai_login_id');
    register_setting('meruhai_settings', 'meruhai_password');
    register_setting('meruhai_settings', 'meruhai_field_mapping');
    register_setting('meruhai_settings', 'meruhai_csv_headers');
  }

  /**
   * 管理画面の表示
   */
  public function admin_page() {
    ?>
    <div class="wrap">
      <h1>める配くん連携設定</h1>
      
      <form method="post" action="options.php">
        <?php
        settings_fields('meruhai_settings');
        do_settings_sections('meruhai_settings');
        ?>
        
        <table class="form-table">
          <tr>
            <th scope="row">エンドポイントURL</th>
            <td>
              <input type="url" name="meruhai_endpoint_url" value="<?php echo esc_attr(get_option('meruhai_endpoint_url')); ?>" class="regular-text" />
              <p class="description">例: https://m1-v3.mgzn.jp/api/apiText.php </p>
            </td>
          </tr>
          <tr>
            <th scope="row">ログインID</th>
            <td>
              <input type="text" name="meruhai_login_id" value="<?php echo esc_attr(get_option('meruhai_login_id')); ?>" class="regular-text" />
            </td>
          </tr>
          <tr>
            <th scope="row">パスワード</th>
            <td>
              <input type="password" name="meruhai_password" value="<?php echo esc_attr(get_option('meruhai_password')); ?>" class="regular-text" />
            </td>
          </tr>
        </table>
        
        <p class="submit">
          <input type="submit" name="submit" id="submit" class="button button-primary" value="設定を保存" />
          <button type="button" id="test-connection" class="button">接続テスト</button>
        </p>
      </form>
      
      <hr>
      
      <h2>フィールドマッピング設定</h2>
      <div id="field-mapping-container">
        <p>まず上記の設定を保存して接続テストを実行してください。</p>
        <?php
        $saved_headers = get_option('meruhai_csv_headers', array());
        if (!empty($saved_headers)) {
          echo '<p><strong>保存されたCSVヘッダー:</strong> ' . implode(', ', $saved_headers) . '</p>';
          echo '<p><em>ヘッダー数: ' . count($saved_headers) . '個</em></p>';
        } else {
          echo '<p><em>CSVヘッダーが保存されていません。接続テストを実行してください。</em></p>';
        }
        ?>
      </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
      // ボタンクリックイベントの設定
      $('#test-connection').click(function() {
        var button = $(this);
        var endpoint = $('input[name="meruhai_endpoint_url"]').val();
        var loginId = $('input[name="meruhai_login_id"]').val();
        var password = $('input[name="meruhai_password"]').val();

        button.prop('disabled', true).text('接続テスト中...');
        // WordPressのAJAX URL（wp-admin/admin-ajax.php）
        // サーバー側で処理するアクション名 meruhai_test_connection
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            action: 'meruhai_test_connection',
            _wpnonce: '<?php echo wp_create_nonce('meruhai_test_connection'); ?>',
            endpoint: endpoint,
            login_id: loginId,
            password: password
          },
          success: function(response) {
            if (response.success) {
              alert('接続成功！');
              loadFieldMapping(endpoint, loginId, password);
            } else {
              alert('接続失敗: ' + response.data);
            }
          },
          error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr.responseText);
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response:', xhr.responseText);
            console.error('XHR Status:', xhr.status);
            console.error('XHR ReadyState:', xhr.readyState);
            
            var errorMessage = '接続エラーが発生しました。';
            if (xhr.status === 400) {
              errorMessage += ' リクエストが不正です。';
            } else if (xhr.status === 403) {
              errorMessage += ' アクセスが拒否されました。';
            } else if (xhr.status === 500) {
              errorMessage += ' サーバーエラーが発生しました。';
            } else if (xhr.status === 0) {
              errorMessage += ' ネットワークエラーまたはCORSエラーです。';
            }
            
            alert(errorMessage + ' 詳細: ' + error + ' (Status: ' + xhr.status + ')');
          },
          complete: function() {
            button.prop('disabled', false).text('接続テスト');
          }
        });
      });
      
      function loadFieldMapping(endpoint, loginId, password) {
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            action: 'meruhai_get_readers',
            _wpnonce: '<?php echo wp_create_nonce('meruhai_get_readers'); ?>',
            endpoint: endpoint,
            login_id: loginId,
            password: password
          },
          success: function(response) {
            if (response.success) {
              $('#field-mapping-container').html(response.data);
              
              // マッピングフォームの送信処理
              $('#field-mapping-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                formData += '&action=meruhai_save_mapping&_wpnonce=<?php echo wp_create_nonce('meruhai_save_mapping'); ?>';
                
                $.ajax({
                  url: ajaxurl,
                  type: 'POST',
                  data: formData,
                  success: function(response) {
                    if (response.success) {
                      alert('マッピングを保存しました。');
                    } else {
                      alert('エラー: ' + response.data);
                    }
                  },
                  error: function() {
                    alert('保存エラーが発生しました。');
                  }
                });
              });
            } else {
              $('#field-mapping-container').html('<p>エラー: ' + response.data + '</p>');
            }
          }
        });
      }
    });
    </script>
    <?php
  }
  
  /**
   * 接続テスト
   */
  public function test_connection() {
    // WordPressの標準的なAJAX処理
    if (!current_user_can('manage_options')) {
      wp_send_json_error('権限がありません。');
      return;
    }
    
    // nonce検証
    if (!wp_verify_nonce($_POST['_wpnonce'], 'meruhai_test_connection')) {
      wp_send_json_error('セキュリティチェックに失敗しました。');
      return;
    }
    
    $endpoint = isset($_POST['endpoint'])
      ? esc_url_raw(wp_unslash($_POST['endpoint']))
      : get_option('meruhai_endpoint_url');
    $login_id = isset($_POST['login_id'])
      ? sanitize_text_field(wp_unslash($_POST['login_id']))
      : get_option('meruhai_login_id');
    $password = isset($_POST['password'])
      ? sanitize_text_field(wp_unslash($_POST['password']))
      : get_option('meruhai_password');
    
    if (empty($endpoint) || empty($login_id) || empty($password)) {
      wp_send_json_error('設定が不完全です。エンドポイントURL、ログインID、パスワードをすべて入力してください。');
      return;
    }
    
    // URLの形式チェック
    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
      wp_send_json_error('エンドポイントURLの形式が正しくありません。');
      return;
    }
    
    // エンドポイントURLの検証
    if (!preg_match('/\/api\/apiText\.php$/', $endpoint)) {
      wp_send_json_error('エンドポイントURLは /api/apiText.php で終わる必要があります。');
      return;
    }
    
    $api = new MeruHai_API($endpoint, $login_id, $password);
    $result = $api->test_connection();
    
    if ($result) {
      // 接続テスト時は保存済みCSVヘッダーをクリアし、取得した最新のCSVヘッダーで上書きする
      delete_option('meruhai_csv_headers');

      $readers = $api->get_readers();
      if ($readers && !empty($readers['headers'])) {
        update_option('meruhai_csv_headers', $readers['headers']);
        wp_send_json_success('接続成功！CSVヘッダーを保存しました。');
      } else {
        wp_send_json_success('接続成功！ただし、CSVヘッダーの取得に失敗しました。');
      }
    } else {
      $error_message = $api->get_last_error();
      wp_send_json_error('接続失敗: ' . $error_message);
    }
  }
  
  /**
   * 読者リスト取得
   */
  public function get_readers() {
    // WordPressの標準的なAJAX処理
    if (!current_user_can('manage_options')) {
      wp_send_json_error('権限がありません。');
      return;
    }
    
    // nonce検証
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'meruhai_get_readers')) {
      wp_send_json_error('セキュリティチェックに失敗しました。');
      return;
    }
    
    $endpoint = isset($_POST['endpoint'])
      ? esc_url_raw(wp_unslash($_POST['endpoint']))
      : get_option('meruhai_endpoint_url');
    $login_id = isset($_POST['login_id'])
      ? sanitize_text_field(wp_unslash($_POST['login_id']))
      : get_option('meruhai_login_id');
    $password = isset($_POST['password'])
      ? sanitize_text_field(wp_unslash($_POST['password']))
      : get_option('meruhai_password');
    
    $api = new MeruHai_API($endpoint, $login_id, $password);
    $readers = $api->get_readers();
    
    if ($readers) {
      $new_headers = isset($readers['headers']) && is_array($readers['headers'])
        ? $readers['headers']
        : array();

      // 保存済みCSVヘッダーをクリアして、取得した最新のCSVヘッダーで上書きする
      delete_option('meruhai_csv_headers');
      update_option('meruhai_csv_headers', $new_headers);

      $cf7_fields = $this->get_cf7_fields();
      $html = $this->render_field_mapping_form($readers, $cf7_fields);
      wp_send_json_success($html);
    } else {
      $error_message = $api->get_last_error();
      if (empty($error_message)) {
        $error_message = '不明なエラー';
      }
      wp_send_json_error('読者リストの取得に失敗しました。' . ' 詳細: ' . $error_message);
    }
  }
  
  /**
   * Contact Form 7のフィールド取得
   */
  public function get_cf7_fields() {
    $forms = WPCF7_ContactForm::find();
    $all_fields = array();
    
    foreach ($forms as $form) {
      $form_fields = $form->scan_form_tags();
      foreach ($form_fields as $field) {
        if (!empty($field->name)) {
          $all_fields[$field->name] = $field->name;
        }
      }
    }
    
    return $all_fields;
  }
  
  /**
   * フィールドマッピングフォームのレンダリング
   */
  private function render_field_mapping_form($readers, $cf7_fields) {
    if (empty($readers['headers'])) {
      return '<p>読者リストのヘッダーが取得できませんでした。</p>';
    }
    
    $excluded_fields = array('登録日', '未使用', 'SMSステータス');
    $available_headers = array_diff($readers['headers'], $excluded_fields);
    
    $html = '<form id="field-mapping-form">';
    $html .= '<table class="form-table">';
    
    foreach ($available_headers as $header) {
      $html .= '<tr>';
      $html .= '<th scope="row">' . esc_html($header) . '</th>';
      $html .= '<td>';
      $html .= '<select name="mapping[' . esc_attr($header) . ']">';
      $html .= '<option value="">選択してください</option>';
      
      foreach ($cf7_fields as $field_name) {
        $selected = '';
        $current_mapping = get_option('meruhai_field_mapping', array());
        if (isset($current_mapping[$header]) && $current_mapping[$header] === $field_name) {
          $selected = 'selected';
        }
        $html .= '<option value="' . esc_attr($field_name) . '" ' . $selected . '>' . esc_html($field_name) . '</option>';
      }
      
      $html .= '</select>';
      $html .= '</td>';
      $html .= '</tr>';
    }
    
    $html .= '</table>';
    $html .= '<p class="submit">';
    $html .= '<input type="submit" class="button button-primary" value="マッピングを保存" />';
    $html .= '</p>';
    $html .= '</form>';
    
    return $html;
  }
  
  /**
   * フィールドマッピング保存
   */
  public function save_mapping() {
    // WordPressの標準的なAJAX処理
    if (!current_user_can('manage_options')) {
      wp_send_json_error('権限がありません。');
      return;
    }
    
    // nonce検証
    if (!wp_verify_nonce($_POST['_wpnonce'], 'meruhai_save_mapping')) {
      wp_send_json_error('セキュリティチェックに失敗しました。');
      return;
    }
    
    if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
      $mapping = array();
      foreach ($_POST['mapping'] as $meruhai_field => $cf7_field) {
        if (!empty($cf7_field)) {
          $mapping[sanitize_text_field($meruhai_field)] = sanitize_text_field($cf7_field);
        }
      }
      
      update_option('meruhai_field_mapping', $mapping);
      wp_send_json_success('マッピングを保存しました。');
    } else {
      wp_send_json_error('マッピングデータが不正です。');
    }
  }
  
  /**
   * Contact Form 7の送信処理
   */
  public function handle_form_submission($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    
    if (!$submission) {
      return;
    }
    
    $posted_data = $submission->get_posted_data();
    $mapping = get_option('meruhai_field_mapping', array());
    
    if (empty($mapping)) {
      return;
    }
    
    $endpoint = get_option('meruhai_endpoint_url');
    $login_id = get_option('meruhai_login_id');
    $password = get_option('meruhai_password');
    
    $api = new MeruHai_API($endpoint, $login_id, $password);
    
    // 保存されたCSVヘッダーを取得
    $saved_headers = get_option('meruhai_csv_headers', array());
    
    if (empty($saved_headers)) {
      error_log('める配くん: CSVヘッダーが保存されていません。接続テストを実行してください。');
      return;
    }
    
    // CSVデータの作成（保存されたヘッダー順序に従う）
    $csv_data = array();
    $headers = array();
    
    // 保存されたヘッダーの順序でデータを構築
    foreach ($saved_headers as $header) {
      if ($header === '登録日') {
          // 登録日を現在の日時で設定
          $headers[] = $header;
          $csv_data[] = date('Y-m-d H:i:s');
          continue;
      }
      if (isset($mapping[$header]) && !empty($mapping[$header])) {
        $cf7_field = $mapping[$header];
        if (isset($posted_data[$cf7_field])) {
          $headers[] = $header;
          // 配列の場合は最初の要素を取得、文字列の場合はそのまま
          $value = $posted_data[$cf7_field];
          if (is_array($value)) {
            $csv_data[] = !empty($value) ? $value[0] : '';
          } else {
            $csv_data[] = $value;
          }
        } else {
          // フィールドが存在しない場合は空文字
          $headers[] = $header;
          $csv_data[] = '';
        }
      } else {
        $headers[] = $header;
        $csv_data[] = '';
      }
    }

    if (!empty($csv_data)) {
      $result = $api->register_reader($headers, $csv_data);
      
      if (!$result) {
        $error_message = $api->get_last_error();
        error_log('める配くん読者登録エラー: ' . $error_message);
        
        // 管理者にエラー通知を送信
        $this->send_error_notification($error_message, $posted_data);
      }
    }
  }
  
  /**
   * エラー通知メール送信
   */
  private function send_error_notification($error_message, $posted_data) {
    // 管理者メールアドレスを取得
    $admin_email = get_option('admin_email');
    
    if (empty($admin_email)) {
      return;
    }
    
    // メール件名
    $subject = '[める配くん連携] 読者登録エラーが発生しました';
    
    // メール本文
    $message = "める配くんへの読者登録でエラーが発生しました。\n\n";
    $message .= "【エラー詳細】\n";
    $message .= $this->format_error_message($error_message) . "\n\n";
    
    $message .= "【送信データ】\n";
    foreach ($posted_data as $key => $value) {
      if (!empty($value)) {
        $formatted_value = $this->format_value($value);
        $message .= $key . ": " . $formatted_value . "\n";
      }
    }
    $message .= "\n";
    
    $message .= "【発生時刻】\n";
    $message .= date('Y-m-d H:i:s') . "\n\n";
    
    $message .= "【サイト情報】\n";
    $message .= "サイトURL: " . home_url() . "\n";
    $message .= "管理画面: " . admin_url() . "\n\n";
    
    $message .= "このエラーを解決するには、WordPressの管理画面で設定を確認してください。\n";
    
    // メールヘッダー
    $headers = array(
      'Content-Type: text/plain; charset=UTF-8',
      'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>'
    );
    
    // メール送信
    wp_mail($admin_email, $subject, $message, $headers);
  }
  
  /**
   * エラーメッセージのフォーマット
   */
  private function format_error_message($error_message) {
    // Arrayが含まれている場合は展開
    if (strpos($error_message, 'Array') !== false) {
      // エラーメッセージをクリーンアップ
      $error_message = str_replace('Array; (; [', '', $error_message);
      $error_message = str_replace('] => ', ': ', $error_message);
      $error_message = str_replace('; [', "\n", $error_message);
      $error_message = str_replace('; ); ', '', $error_message);
      $error_message = str_replace('Array', '', $error_message);
      $error_message = trim($error_message);
    }
    
    // 重複したエラーメッセージを削除
    $lines = explode("\n", $error_message);
    $unique_lines = array();
    $seen_messages = array();
    
    foreach ($lines as $line) {
      $line = trim($line);
      if (!empty($line)) {
        // 行番号を除いたメッセージ部分を取得
        if (preg_match('/^\d+:\s*(.+)$/', $line, $matches)) {
          $message_part = trim($matches[1]);
          if (!in_array($message_part, $seen_messages)) {
            $unique_lines[] = $line;
            $seen_messages[] = $message_part;
          }
        } else {
          $unique_lines[] = $line;
        }
      }
    }
    
    return implode("\n", $unique_lines);
  }
  
  /**
   * 値のフォーマット（Arrayを展開）
   */
  private function format_value($value) {
    if (is_array($value)) {
      return implode(', ', $value);
    }
    
    return $value;
  }
}
