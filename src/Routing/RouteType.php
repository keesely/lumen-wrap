<?php
namespace Lx\Routing;

class RouteType {

  protected $value = null;

  protected $typeName = null;

  public function __construct ($value, $type = null) {
    $this->value = $value;
    $this->typeName = $type ?: gettype($value);
  }

  public function getType () {
    return $this->typeName;
  }

  public function getValue () {
    return $this->value;
  }

  public function __toString () {
    return $this->value;
  }

  public function __invoke () {
    return $this->value;
  }

  public function __debugInfo () {
    return [
      'value' => $this->value,
      'type' => $this->typeName
    ];
  }

}
