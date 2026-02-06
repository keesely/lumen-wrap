<?php
namespace Lx\Concerns\ModelTraits;

trait WithContext {

  protected $ctx = null;

  public function setContext($name, $value) {
    $this->ctx = $this->ctx ?: collect([]);
    $this->ctx->put($name, $value);
    return $this;
  }

  public function getContext($name, $value = null) {
    return ($this->ctx ?: collect([]))->get($name, $value);
  }

}
