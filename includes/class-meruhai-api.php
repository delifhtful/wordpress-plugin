<?php
/**
 * める配くんAPI連携クラス
 */
class MeruHai_API {
  
  private $endpoint;
  private $login_id;
  private $password;
  private $last_error;
  
  public function __construct($endpoint, $login_id, $password) {
    $this->endpoint = $endpoint;
    $this->login_id = $login_id;
    $this->password = $password;
  }
  
  /**
   * 接続テスト
   */
  public function test_connection() {
    $post_data = $this->build_request('downloadReaders', array(
      'includeValid' => 'true',
      'includeInvalid' => 'false',
      'includeError' => 'false'
    ));
    // 読者情報を取得する。
    $response = $this->send_request($post_data);
    
    if ($response === false) {
      return false;
    }
    
    return $this->parse_response($response, 'downloadReaders');
  }
  
  /**
   * 読者リスト取得
   */
  public function get_readers() {
    $post_data = $this->build_request('downloadReaders', array(
      'includeValid' => 'true',
      'includeInvalid' => 'false',
      'includeError' => 'false'
    ));
    
    $response = $this->send_request($post_data);
    
    if ($response === false) {
      return false;
    }
    
    $parsed = $this->parse_response($response, 'downloadReaders');
    
    if (!$parsed) {
      return false;
    }
    
    // CSVデータを解析
    $lines = explode("\n", $response);
    $headers = array();
    $data = array();
    
    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line) || strpos($line, '!') === 0) {
        continue;
      }
      // UTF-8 BOM (EF BB BF) を削除
      if (substr($line, 0, 3) === "\xEF\xBB\xBF") {
        $line = substr($line, 3);
      }

      if (strpos($line, '#') === 0) {
        // ヘッダー行
        $headers = str_getcsv(substr($line, 1));
      } else {
        // データ行
        $data[] = str_getcsv($line);
      }
    }
    
    return array(
      'headers' => $headers,
      'data' => $data
    );
  }
  
  /**
   * 読者登録
   */
  public function register_reader($headers, $data) {
    // 重複チェック
    /*$existing_readers = $this->get_readers();
    if ($existing_readers) {
      foreach ($existing_readers['data'] as $existing_data) {
        if (count($existing_data) > 0 && count($data) > 0) {
          // emailフィールドでの重複チェック
          $email_index = array_search('email', $headers);
          if ($email_index !== false && isset($existing_data[0]) && isset($data[$email_index])) {
            if ($existing_data[0] === $data[$email_index]) {
              $this->last_error = '重複するメールアドレスです: ' . $data[$email_index];
              return false;
            }
          }
        }
      }
    }*/
    
    // CSVデータの構築（データ行のみダブルクォートで囲む）
    $csv_data = '#' . implode(',', $headers) . "\n";
    
    $quoted_data = array_map(function($value) {
      return '"' . str_replace('"', '""', $value) . '"';
    }, $data);
    $csv_data .= implode(',', $quoted_data) . "\n";
    
    // 空行を削除
    $csv_data = preg_replace('/\n\s*\n/', "\n", $csv_data);
    $csv_data = trim($csv_data);
    
    $post_data = $this->build_request('updateReadersJob', array(), $csv_data);
    error_log("row post_data: " . $post_data);
    
    $response = $this->send_request($post_data);
    error_log("row response: " . $response);
    if ($response === false) {
      return false;
    }
    
    return $this->parse_response($response, 'updateReadersJob');
  }
  
  /**
   * リクエストデータの構築
   */
  private function build_request($command, $params = array(), $csv_data = '') {
    $request = "!command={$command}\n";
    $request .= "!cid={$this->login_id}\n";
    $request .= "!pass={$this->password}\n";
    
    foreach ($params as $key => $value) {
      $request .= "!{$key}={$value}\n";
    }
    
    if (!empty($csv_data)) {
      $request .= $csv_data;
    }
    
    return $request;
  }
  
  /**
   * APIリクエスト送信
   */
  private function send_request($post_data) {
    $curl = curl_init($this->endpoint);
    
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($curl, CURLOPT_USERAGENT, 'WordPress MeruHai Integration Plugin');
    
    $response = curl_exec($curl);
    
    if (curl_errno($curl)) {
      $error_code = curl_errno($curl);
      $error_message = curl_error($curl);
      $this->last_error = "CURL Error {$error_code}: {$error_message}";
      curl_close($curl);
      return false;
    }
    
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($curl);
    
    curl_close($curl);
    
    if ($http_code !== 200) {
      $error_message = "HTTP Error {$http_code}";
      if ($response) {
        // HTMLレスポンスの場合は最初の数行のみ表示
        $lines = explode("\n", $response);
        $first_lines = array_slice($lines, 0, 3);
        $error_message .= ": " . implode(" ", $first_lines);
      } else {
        $error_message .= ": No response body";
      }
      $this->last_error = $error_message;
      return false;
    }
    
    return $response;
  }
  
  /**
   * レスポンス解析
   */
  private function parse_response($response, $command = null) {
    // デバッグログ
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('MeruHai API Response: ' . $response);
    }
    
    // レスポンスが配列形式の場合（PHPの配列出力）
    if (strpos($response, 'Array') !== false && strpos($response, '(') !== false) {
      // 配列形式のレスポンスを解析
      $this->parse_array_response($response);
      return false;
    }
    
    // BOMや先頭空白による誤判定を防ぐため、先頭を正規化
    $normalized_response = ltrim($response, "\xEF\xBB\xBF \t\r\n");
    $lines = explode("\n", $normalized_response);
    $result_status = null;
    $error_messages = array();
    
    foreach ($lines as $line) {
      $line = trim($line);
      
      // !result= の行をチェック
      if (strpos($line, '!result=') === 0) {
        $result_status = substr($line, 8);
      }
      // !result= 以外の空でない行をエラーメッセージとして処理
      elseif (!empty($line)) {
        // downloadReadersコマンドの場合はCSVデータなのでエラーとして扱わない
        if ($command === 'downloadReaders') {
          continue;
        }
        
        // "先頭から:まで"を削除
        if (strpos($line, ':') !== false) {
          $parts = explode(':', $line, 2);
          if (count($parts) > 1) {
            $error_message = trim($parts[1]);
            // 空でないエラーメッセージのみ追加
            if (!empty($error_message)) {
              $error_messages[] = $error_message;
            }
          }
        } else {
          $error_messages[] = trim($line);
        }
      }
    }
    
    // result=okの場合
    if ($result_status === 'ok') {
      // エラーメッセージがある場合はエラーとして処理
      if (!empty($error_messages)) {
        $this->last_error = 'API Error: ' . implode('; ', $error_messages);
        return false;
      }
      // エラーメッセージがない場合は成功
      return true;
    }
    
    // result=ok以外の場合
    if ($result_status) {
      $this->last_error = 'API Error: ' . $result_status;
      return false;
    } else {
      // !result= がない場合、CSVデータが返ってきた場合は成功として扱う
      if (strpos($normalized_response, '#') === 0 || strpos($normalized_response, 'email') !== false) {
        return true;
      } else {
        $this->last_error = 'Invalid response format';
        return false;
      }
    }
  }
  
  /**
   * 配列形式レスポンスの解析
   */
  private function parse_array_response($response) {
    $error_messages = array();
    
    // statusErrors:Array の部分を抽出
    if (preg_match('/statusErrors:Array\s*\(\s*(.*?)\s*\)/s', $response, $matches)) {
      $array_content = $matches[1];
      
      // 配列の各要素を抽出
      if (preg_match_all('/\[\s*(\d+)\s*\]\s*=>\s*(.+?)(?=\[\s*\d+\s*\]|$)/s', $array_content, $array_matches, PREG_SET_ORDER)) {
        foreach ($array_matches as $match) {
          $index = $match[1];
          $value = trim($match[2]);
          
          // 値が "Array" の場合はスキップ
          if ($value !== 'Array') {
            $error_messages[] = $value;
          }
        }
      }
    }
    
    if (!empty($error_messages)) {
      $this->last_error = 'API Error: ' . implode('; ', $error_messages);
    } else {
      $this->last_error = 'API Error: Unknown array response format';
    }
  }
  
  /**
   * 最後のエラーメッセージ取得
   */
  public function get_last_error() {
    return $this->last_error;
  }
}
