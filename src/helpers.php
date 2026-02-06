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

if (!function_exists('carbon')) {
    /**
     * Get a new Carbon instance for the current time.
     *
     * @param  \DateTimeInterface|string|null  $datetime
     * @param  \DateTimeZone|string|null  $tz
     *
     * @throws \InvalidArgumentException
     */
  function carbon($datetime = null, $tz = null)
  {
    $tz = $tz ?: config('app.timezone');
    return \Illuminate\Support\Carbon::parse($datetime)->tz($tz);
  }
}


// jwt_encode($data, $options);
if (!function_exists('jwt_encode')) {
    function jwt_encode($data, $options = [])
    {
      if (
        !($options['singer'] ?? null)
        && !($options['jwk'] ?? null)
      ) {
        $options['singer'] = config('app.key');
      }

      return (new Lx\Support\JWT)->NewBuilder([
        'claims' => $data,
        ...$options
      ])->toString();
    }
}

// jwt_decode($token, $options);
if (!function_exists('jwt_decode')) {
  function jwt_decode($token, $options = [])
  {
    $toArray = false;
    if (true === $options) ($toArray = true) && ($options = []);
    $parse = (new Lx\Support\JWT)->Parse($token, $options);
    return $toArray ? $parse->toArray() : $parse;
  }
}

if (!function_exists('route')) {
  /**
   * Generate the URL to a named route.
   *
   * @param  array|string  $name
   * @param  mixed  $parameters
   * @param  bool  $absolute
   * @return string
   *
   * @throws \InvalidArgumentException
   */
  function route($name, $parameters = [], $absolute = true)
  {
    return app('url')->route($name, $parameters, $absolute);
  }
}

if (!function_exists('router')) {
  /**
   * Set Router addRoute
   * */
  function router() {
    return app('router')->parse(...func_get_args());
  }
}

if (!function_exists('is_assoc_array')) {
  function is_assoc_array($array) {
    return array_keys($array) !== range(0, count($array) - 1);
  }
}

if (!function_exists('array2csv')) {
  function array2csv(array $rows, array $heads, string | null $download = null) {
    ob_start();
    $fp = fopen('php://output', 'w');
    fputcsv($fp, $heads);

    foreach ($rows as $row) {
      fputcsv($fp, $row);
    }
    fclose($fp);
    $contnets = ob_get_contents();
    ob_end_clean();

    // with UTF-8 BOM
    $contents = "\xEF\xBB\xBF" . $contnets;
    if ($download) {
      header('Content-Type: text/csv; charset=utf-8');
      header('Content-Disposition: attachment; filename="' . $download . '.csv"');
      header('Content-Length: ' . strlen($contnets));
      header('Connection: close');
      header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
      header('Expires: 0');
      header('Pragma: no-cache');
    }
    echo $contents;
  }
}
