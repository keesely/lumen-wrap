<?php

namespace Lx\Commands;

use DB;
use Cache;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Lx\Database\SchemaTable;

class MigrateTablesCommand extends Command {

  protected $signature = 'migrate:tables
{tables : 数据表结构文件名称}
{--seeds : 同时构造数据填充}';

  protected $description = '根据 database/tables 文件内容更新数据库';

  protected $_types = [];

  public function handle() {
    $tables = $this->getTablesFiles();
    foreach ($tables as $table) {
      $tabStructs = require $table;
      if (!is_array($tabStructs)) {
        $this->error('invalid table struct array in ('.$table.')');
        continue;
      }

      // 解析并转换为迁移文件
      // $migrations = [];
      // foreach ($tabStructs as $name => $struct) {
      //   $fields = $struct['fieldset'] ?? [];
      //   foreach ($fields as $field) {
      //     //$migrations[] = var_export($field, true);
      //     // 转换为 php 代码
      //     //$code = serialize(new \Laravel\SerializableClosure\SerializableClosure($field));
      //     //dd($code);
      //   }
      // }

      //return $this->info('migrating tables...');

      SchemaTable::importStructs($tabStructs, function ($table, $struct) {
        //$columns = count($table->getColumns());
        $this->info("migrated table {$table->getName()}.");

        if ($this->option('seeds')) {
          $seeds = $struct['seeds'] ?? [];
          if ($seeds instanceof Closure) $seeds();
          elseif (is_array($seeds)) {
            foreach ($seeds as $data) {
              DB::table($table->getName())->insertOrIgnore($data);
            }
          }
        }
      });

    }
  }

  protected function getTablesFiles() {
    $tables = $this->argument('tables');
    return collect(explode(',', $tables))->map(function($table) {
      $path = $this->laravel->basePath('database/tables/'.$table.'.php');
      return realpath($path);
    })->filter()->toArray();
  }

}

