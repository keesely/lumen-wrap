<?php

namespace Lx\Events;


use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_ALL)]
class Listener
{

  public function __construct(
    public $listener
  ) {
    //
  }

}
