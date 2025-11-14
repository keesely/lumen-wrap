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

trait ApiController
{
  protected $result = [];

  protected function setResult(array $data, $node) {
    if (!$data) return $this;
    $this->result = is_array($this->result) ? $this->result : [];

    $this->result[$node] = array_merge($this->result[$node] ?? [], $data);
    return $this;
  }

  public function __call($name, $args) {
    if (in_array($name, ['extend', 'info', 'debug'])) {
      return $this->setResult(array_shift($args), $name);
    }
    
  }

}
