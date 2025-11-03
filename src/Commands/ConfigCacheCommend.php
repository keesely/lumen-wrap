<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConfigCacheCommend extends Command {
  protected $signature = 'config:cache {--clear=no} {keys?}';

  protected $description = 'Storage Config Cache';

  protected $configCacheFile = 'framework/cache/config.json';

  public function handle() {
    
    if (is_null($this->option('clear'))) {
      return $this->clear();
    }
    $keys = $this->argument('keys');
    $keys = array_filter(array_map('trim', explode(',', $keys)));
    $this->cache($keys);
  }

  public function getConfigCacheFile(): string {
    return config('config_cache', storage_path($this->configCacheFile));
  }

  public function cache(array $keys) {
    $config = [];
    if (count($keys) > 0) {
      foreach ($keys as $key) $config[$key] = config($key);
    }
    else {
      $config = config()->all();
    }
    $configCacheFile = $this->getConfigCacheFile();
    if (file_exists($configCacheFile)) {
      $origin = json_decode(file_get_contents($configCacheFile), true);
      $config = array_merge($origin, $config);
      unlink($configCacheFile);
    }
    file_put_contents($configCacheFile, json_encode($config));
    return $this->info('Done');
  }

  public function clear() {
    $configCacheFile = $this->getConfigCacheFile();
    if (file_exists($configCacheFile)) {
      unlink($configCacheFile);
      return $this->info('Done');
    }
  }
}
