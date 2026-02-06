<?php
namespace Lx\Concerns\ModelTraits;

trait WithAppend {

  protected static $withAppends = [];

  protected static $withoutAppend = false;

  public function scopeWithAppends($query, array $appends = []) {
    self::$withAppends = $appends;
    return $query;
  }

  public function scopeWithoutAppend($query) {
    self::$withoutAppend = true;
    return $query;
  }

  protected function getArrayableAppends() {
    if (self::$withoutAppend) {
      return [];
    }
    $appends = is_array(self::$withAppends) ? self::$withAppends : [];
    return array_merge(parent::getArrayableAppends(), $appends);
  }

  public function setAppend(...$appends) {
    $this->withAppends($appends);
    return $this;
  }

  public function unsetAppend(...$appends) {
    $this->appends = array_diff($this->appends, $appends);
    return $this;
  }

}
