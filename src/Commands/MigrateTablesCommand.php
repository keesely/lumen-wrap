<?php

namespace Lx\Commands;

use DB;
use Cache;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Laravel\SerializableClosure\SerializableClosure;
use Illuminate\Support\Facades\Schema;
use Lx\Database\SchemaTable;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class MigrateTablesCommand extends Command {

  protected $signature = 'migrate:tables
{tables : 数据表结构文件名称}
{--seeds : 同时构造数据填充}';

  protected $description = '根据 database/tables 文件内容更新数据库';

  protected $_types = [];

  static $migrations = [];

  public function configure() {
    $this->addOption('in', 'i', InputOption::VALUE_OPTIONAL, '限定执行表名称,多表使用逗号分割, ex: table1,table2');
    $this->addOption('only', null, InputOption::VALUE_OPTIONAL, '限定执行方法：create/update');
    $this->addOption('rollback', 'r', InputOption::VALUE_OPTIONAL, '执行批次回滚并删除生成的迁移文件');
  }

  protected function getOption($name) {
    return $this->options()[$name] ?? null;
  }

  public function handle() {
    $tables = $this->getTablesFiles();

    if ($rollback = $this->getOption('rollback')) {
      return $this->rollback($rollback);
    }

    if (($only = $this->getOption('only')) && !in_array($only, ['create', 'update'])) {
      $only = null;
    }

    $inTables = $this->getOption('in');
    if ($inTables) $inTables = array_values(array_filter(explode(',', $inTables)));

    $batch = $this->getImportLastBatch() + 1;

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
        if ($inTables && !in_array($tab, $inTables)) {
          $this->info('Table ['.$tab.'] Continue.');
          continue;
        }

        $code = serialize(new SerializableClosure(fn() => [$tab, $struct]));
        $code = base64_encode($code);
        $hash = substr(md5($code), 0, 8);
        if ($this->hasImported($tab, $hash)) continue;

        $hasTable = Schema::hasTable($tab);
        if (('update' == $only && !$hasTable) || ('create' == $only && $hasTable)) {
          $this->info('Table ['.$tab.'] is ' . ($hasTable ? 'createed' : 'no created'));
          continue;
        }

        $stub = $this->getStubContents([
          'code' => $code,
          'table' => $tab,
          'connection' => $struct['connection'] ?? config('database.default'),
          'drop' => $hasTable ? 0 : 1,
        ]);

        $istr = str_pad($i, 2, '0', STR_PAD_LEFT);
        $name = implode('_', [
          str_pad($batch, 2, '0', STR_PAD_LEFT),
          date('YmdHis'). $istr,
          $hasTable ? 'update' : 'create',
          str_replace(' ', '_', $tab),
          $hash
        ]);
        // if (!file_exists(base_path($dir = 'database/migrations/'.$tab))) {
        //     mkdir($dir, 0755, true);
        // }
        // $name = $tab .'/'. $name;

        $this->putStubContents($stub, $name);
        $this->info('make migration: '.$name . ' ('.$tab.') success.');
        $this->info('migrating table => database/migrations/'.$name. '.php');
        $this->migrate($name, $batch);
        //$res = $this->call('migrate', ['--path' => 'database/migrations/'.$name.'.php']);
      }
    }

    // $this->info('migrating tables...');
    // try {
    //   $this->call('migrate');
    // } catch(throwable $e) {
    //     dd($e);
    // }
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
      if (str_contains($migration, $tab.'_'. $hash)) return true;
    }
    return false;
  }

  protected function getImportLastBatch() {
    return DB::table('migrations')->selectRaw('MAX(batch) as last')->orderBy('batch', 'desc')
      ->limit(1)->value('last') ?: 0;
  }

  protected function getImportedByBatch(int $batch) {
    return DB::table('migrations')
      ->where('batch', intval($batch))
      ->pluck('migration');
  }

  protected function fixMigrateBatch($name, $batch) {
    return DB::table('migrations')
      ->where('migration', $name)
      ->update(['batch' => $batch]);
  }

  protected function putStubContents($stub, $name): array {
    // 替换其他空的变量
    $stub = preg_replace('/{{ (.*?) }}/', '', $stub);
    $saveTo = base_path('database/migrations/'.$name.'.php');
    if (file_exists($saveTo)) return [false, 'migration file already exists ('.$saveTo.')'];
    file_put_contents($saveTo, $stub);
    return [true, $saveTo];
  }

  // 执行迁移数据 - throwable 删除失效文件
  protected function migrate($name, int $batch) {
    $command = $this->getApplication()->find('migrate');
    $input = new ArrayInput(['--path' => $path = 'database/migrations/'.$name . '.php']);
    $output = new ConsoleOutput;
    try {
      $command->run($input, $output);
      $this->fixMigrateBatch($name, $batch);
    } catch (\Throwable $e) {
      // 删除无效的迁移文件
      if (@unlink(base_path($path))) $this->info('removed migrate file: '. $path);
      throw $e;
    }
  }

  // 回滚批次并删除
  protected function rollback($batch) {
    $command = $this->getApplication()->find('migrate:rollback');
    $output = new ConsoleOutput;
    try {
      $migrations = $this->getImportedByBatch($batch);
      foreach ($migrations as $name) {
        $file = base_path($path = 'database/migrations/'.$name . '.php');
        if (!file_exists($file)) continue;
        $input = new ArrayInput(['--path' => $path]);
        $command->run($input, $output);
        @unlink($file);
      }
    } catch (\Throwable $e) {
      throw $e;
    }
  }
}

