<?php

namespace Lx\Facades;

use Illuminate\Support\Facades\Facade;

class Jwt extends Facade {

  /**
   * Get the registered name of the component.
   *
   * @return string
   */
  protected static function getFacadeAccessor()
  {
    return 'jwt';
  }


}
