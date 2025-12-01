<?php

namespace Lx\Routing;

#[\Attribute(\Attribute::IS_REPEATABLE |\Attribute::TARGET_ALL)]
class MixRoute {
  public function __construct (
    public string $method,
    public string $uri,
    public array $options = [],
    public bool $merge = true
  ) {}
}
