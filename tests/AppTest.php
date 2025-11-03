<?php

class AppTest extends TestCase {

  public function testVersion() {
    dd($this->app, $this->app->router->getRoutes());
  }
}
