<?php

namespace Lx\Commands;

use DB;
use Cache;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Laravel\SerializableClosure\SerializableClosure;
use Lx\Database\SchemaTable;

class MigrateTablesCommand extends Command {

  protected $signature = 'migrate:tables
{tables : 数据表结构文件名称}
{--seeds : 同时构造数据填充}';

  protected $description = '根据 database/tables 文件内容更新数据库';

  protected $_types = [];

  static $migrations = [];

  public function handle() {
    $tables = $this->getTablesFiles();
    foreach ($tables as $table) {
      $tabStructs = require $table;
      if (!is_array($tabStructs)) {
        $this->error('invalid table struct array in ('.$table.')');
        continue;
      }

      // 解析并转换为迁移文件
      $i = 0;
      $migrations = [];
      foreach ($tabStructs as $tab => $struct) {
        $i++;
        if (!is_array($struct)) return $this->error('invalid table struct in ('.$table.')');

        $code = serialize(new SerializableClosure(fn() => [$tab, $struct]));
        $code = base64_encode($code);
        $hash = substr(md5($code), 0, 8);
        if ($this->hasImported($tab, $hash)) continue;

        $stub = $this->getStubContents([
          'code' => $code,
          'table' => $tab,
          'connection' => $struct['connection'] ?? config('database.default'),
        ]);

        $istr = str_pad($i, 2, '0', STR_PAD_LEFT);
        $name = implode('_', [
          date('Y_m_d_His'). $istr,
          str_replace(' ', '_', $tab),
          $hash
        ]);

        $this->putStubContents($stub, $name);
        $this->info('make migration: '.$name . ' ('.$tab.') success.');
      }
    }

    $this->info('migrating tables...');
    $this->call('migrate');
  }

  protected function getTablesFiles() {
    $tables = $this->argument('tables');
    return collect(explode(',', $tables))->map(function($table) {
      $path = $this->laravel->basePath('database/tables/'.$table.'.php');
      return realpath($path);
    })->filter()->toArray();
  }

  protected function getStub() {
    return __DIR__.'/stubs/migration.stub';
  }

  protected function getStubContents($variables = []) {
    $stub = file_get_contents($this->getStub());
    foreach ($variables as $key => $value) {
      $stub = str_replace('{{ '.$key.' }}', $value, $stub);
    }
    return $stub;
  }

  protected function hasImported($tab, $hash) {
    if (!count(static::$migrations))
      static::$migrations = DB::table('migrations')->pluck('migration');

    foreach (static::$migrations as $migration) {
      if (str_contains($migration, $tab) && str_contains($migration, $hash)) return true;
    }
    return false;
  }

  protected function putStubContents($stub, $name): array {
    // 替换其他空的变量
    $stub = preg_replace('/{{ (.*?) }}/', '', $stub);
    $saveTo = base_path('database/migrations/'.$name.'.php');
    if (file_exists($saveTo)) return [false, 'migration file already exists ('.$saveTo.')'];
    file_put_contents($saveTo, $stub);
    return [true, $saveTo];
  }

}

