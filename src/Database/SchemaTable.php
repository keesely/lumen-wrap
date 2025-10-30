<?php

namespace Lx\Database;

use Lx\Database\DBALTypes;
//use Lx\Database\Schema\Table;
use Lx\Database\Table;

use Illuminate\Database\Schema\Blueprint;
use Doctrine\DBAL\Types\Type as DBALType;
use Doctrine\DBAL\Types\SimpleArrayType;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\ColumnDefinition;
use Closure;
use DB;

class SchemaTable {

  protected $imported = [];

  protected $conn = null;

  protected $foreigns = [];

  static protected $migrations = [];

  public function __construct() {
    DBALType::addType('tinyinteger' , DBALTypes\TinyIntegerType::class);
    DBALType::addType('timestamp'   , DBALTypes\TimestampType::class);
    DBALType::addType('uuid'        , DBALTypes\UUIDType::class);
    DBALType::addType('set'         , SimpleArrayType::class);
  }

  protected function createIndexName($tab, $type, array $columns) {
    $index = strtolower($tab.'_'.implode('_', $columns).'_'.$type);
    return str_replace(['-', '.'], '_', $index);
  }

  protected function tableStruct($tab, &$struct) {
    $fieldset = $this->formatFieldset($tab, $struct);
    $method = 'create';
    if (Schema::hasTable($tab)) {
      //$this->registerEnumType();
      $method = 'table';
    }

    Schema::$method($tab, function(Blueprint $table) use($fieldset, &$struct, $method) {
      $this->setColumns($fieldset, $table)
                     ->comment($struct['name'] ?? null);

      if (is_array($dropColumns = $struct['drop_columns'] ?? null)) {
        foreach ($dropColumns as $column) {
          if (Schema::hasColumn($table->getTable(), $column)) $table->dropColumn($column);
        }
      }

      if ($this->foreigns) $this->foreignsBinding($table);

      $migration = $struct['migration'] ?? false;
      if ($migration instanceof Closure) $migration($table, $this);
      $struct['table'] = $table;
      $struct['schema_method'] = $method;
    });
  }

  protected function importStructs(array $structs, Closure $callable = null) {
    $i = 0;
    foreach ($structs as $tab => $struct) {
      $i++;
      if (!is_array($struct)) {
        throw new SchemaException("TABLE {$tab} Struct is empty!");
      }

      $code = serialize(new \Laravel\SerializableClosure\SerializableClosure(function () use($tab, $struct) {
        return [$tab, $struct];
      }));
      $code = base64_encode($code);

      $stub = $this->getStub();
      $stub = file_get_contents($stub);
      $stub = str_replace('{{ table }}', $tab, $stub);
      $stub = str_replace('{{ code }}', $code, $stub);
      $type = Schema::hasTable($tab) ? 'update' : 'create';
      $istr = str_pad($i, 2, '0', STR_PAD_LEFT);
      $hash = substr(md5($code), 0, 8);

      // 查询是否已经导入
      if ($this->hasImported($tab, $hash)) continue;

      $migration_name = date('Y_m_d_His').$istr.'_'.str_replace(' ', '_', $tab).'_'.$type.'_'.$hash;
      file_put_contents(base_path('database/migrations/'.$migration_name.'.php'), $stub);
      continue;

      $table = new Table($tab);
      $table->build($struct['fieldset'] ?? [], [
        'timestamps'   => $struct['timestamps'] ?? false,
        'comment'      => $struct['comment'] ?? $struct['name'] ?? null,
        'drop_columns' => $struct['drop_columns'] ?? null,
        'migration'    => $struct['migration'] ?? null,
      ]);

      //$this->tableStruct($tab, $struct);
      if ($callable instanceof Closure) {
        $callable($table, $struct);
      }
    }
  }

  public function registerEnumType() {
    return $this->getSchemaConnection()
      ->getDoctrineSchemaManager()
      ->getDatabasePlatform()
      ->registerDoctrineTypeMapping('enum', 'enum');
  }

  protected function getStub() {
    return __DIR__.'/stubs/migration.stub';
  }

  protected function hasImported($tab, $hash) {
    if (!count(static::$migrations)) static::$migrations = DB::table('migrations')->pluck('migration');
    foreach (static::$migrations as $migration) {
      if (str_contains($migration, $tab) && str_contains($migration, $hash)) return true;
    }
    return false;
  }

  public function __call($name, $args) {
    if (method_exists($this, $name)) return $this->$name(...$args);
    return null;
  }

  static function __callStatic($name, $args) {
    $obj = new self;
    return $obj->$name(...$args);
  }

}
