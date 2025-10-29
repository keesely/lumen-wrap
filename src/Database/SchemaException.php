<?php

namespace Lx\Database;

class SchemaException extends \Exception {

  public $message;

  public $code;

  public $context;
  
  public function __construct($msg, $code = null, array $context = null) {
    $this->message = $msg;
    $this->code = $code;
    $this->context = $context;
  }
}
