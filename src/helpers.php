<?php

if (!function_exists('app_path')) {
    /**
     * Get the path to the app of the install.
     *
     * @param  string  $path
     * @return string
     *
     * @Feature add function app_path($path = '')
     */
    function app_path($path = '')
    {
        return app()->basePath().'/app'.($path ? '/'.$path : $path);
    }
}

if (!function_exists('now')) {
    /**
     * Get a new Carbon instance for the current time.
     *
     * @param  \DateTimeInterface|string|null  $format
     * @param  \DateTimeZone|string|null  $tz
     * @return \Illuminate\Support\Carbon
     *
     * @throws \InvalidArgumentException
     */
    function now($format = null, $tz = null)
    {
        return \Illuminate\Support\Carbon::now($format, $tz);
    }
}

if (!function_exists('get_client_ip')) {
  /**
   * Get the client IP address. Fix With X-Forwarded-For | X-Real-IP
   *
   * @return string
   * */
  function get_client_ip($showall = false) {
    $req = \Illuminate\Support\Facades\Request::class;

    $ips = array_filter([
      $req::server('HTTP_X_REAL_IP'),
      $req::server('HTTP_REAL_IP'),
      $req::server('HTTP_X_FORWARDED_FOR'),
      $req::getClientIp(),
      '0.0.0.0',
    ]);

    return $showall ? $ips : current($ips);
  }
}
