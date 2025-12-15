<?php

/**
 * 
 * @fileName ApiController.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 17/08/2017
 * @version ApiController.php 2017.08.17
 * */

namespace Lx\Controller;

use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;

trait ApiController
{
  protected $result = [];

  protected $responser;

  public function __construct() {
    $this->withResponser(function ($response, Request $request) {
      return (new ApiResponse($response, $request))($this);
    });
  }

  public function getResponser() {
    return $this->responser;
  }

  protected function withResponser($responser, $cover = true) {
    if ($cover) $this->responser = $responser;
    else if($this->responser instanceof \Closure) {
      $this->responser = [$this->responser, $responser];
    }
    else if (is_array($this->responser)) {
      $this->responser[] = $responser;
    }
    return $this;
  }

  protected function setResult(array $data, $node) {
    if (!$data) return $this;
    if (count($data) == 1) $data = array_shift($data);
    else if (count($data) == 2 && is_string($data[0])) {
      [$key, $data] = [array_shift($data), array_shift($data)];
      $data = [$key => $data];
    }
    $this->result = is_array($this->result) ? $this->result : [];

    $this->result[$node] = array_merge($this->result[$node] ?? [], $data);
    return $this;
  }

  public function __call($name, $args) {
    if (in_array($name, ['extend', 'info', 'debug', 'failure'])) {
      $this->setResult($args, $name);
      //return $this->setResult(array_shift($args), $name);
    }
  }

  public function result($data, $msg = 'Success') {
    $msg = str_contains($msg, '.') ? trans($msg) : $msg;
    $data = ['code' => 200, 'msg' => $msg, 'data' => $data];

    $this->result = is_array($this->result) ? $this->result : [];

    return response(array_merge($this->result, $data));
  }

  public function error($msg, $code = 9999) {
    $data = ['code' => "$code" ?: '9999', 'msg' => $msg ?: "exceptions"];
    $this->result = is_array($this->result) ? $this->result : [];

    return response(array_merge($this->result, $data));
  }

  public function fail($msg, ...$args) {
    $msg = str_contains($msg, '.') ? trans($msg) : $msg;
    if (!is_array($msg)) $msg = [$msg, 9999];

    if (count($args)) $this->failure(...$args);
    return $this->error(...array_slice(array_values($msg), 0, 2));
  }

  public function validate(Request|array $request, array|Model $rules, array $messages = [], array $customAttributes = []) {
    if (is_array($request)) $request = app()->make(Request::class)->merge($request);
    if ($rules instanceof Model) {
      if (method_exists($rules, 'getRules')) {
        $rules = $rules->getRules($rules->exists ? 'update' : 'create');
      }
      else {
        $_rules = array_combine($rules->getFillable(), array_fill(0, count($rules->getFillable()), 'string|array|numeric'));
        $_rules = array_merge($_rules, [
          $rules->getKeyName() => join('|', [
            'exists:' . $rules->getTable() . ',' . $rules->getKeyName(),
          ])
        ]);
        $rules = $_rules;
      }
    }
    return parent::validate($request, $rules, $messages, $customAttributes); 
  }

}
