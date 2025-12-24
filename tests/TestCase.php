<?php

use Laravel\Lumen\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
  /**
   * Creates the application.
   *
   * @return \Laravel\Lumen\Application
   */
  public function createApplication()
  {
    return require __DIR__.'/../bootstrap/app.php';
  }

  public function info(...$args) {
    foreach ($args as &$arg) {
      if (is_array($arg) || is_object($arg)) {
        $arg = json_encode($arg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
      }
    }
    echo "\r\n[INFO] " .implode("\t", $args) . "\r\n";
  }

  public function error(...$args) {
    foreach ($args as &$arg) {
      if (is_array($arg) || is_object($arg)) {
        $arg = json_encode($arg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
    }
    echo "\r\n\033[31m[ERROR] " .implode("\t", $args) . "\033[0m\r\n";
  }

  public function success(...$args) {
    foreach ($args as &$arg) {
      if (is_array($arg) || is_object($arg)) {
        $arg = json_encode($arg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      }
    }
    echo "\r\n\033[32m[SUCCESS] " .implode("\t", $args) . "\033[0m\r\n";
  }

  public function equal($a, $b, $msg = '') {
    if ($a === $b) {
      $this->success($msg?:'Equal Success');
    } else {
      $this->error($msg ?: sprintf('Equal Error: %s != %s', $a, $b));
    }
  }
}
