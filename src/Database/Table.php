<?php

namespace Lx\Database;

use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lx\Database\SchemaException;
use Lx\Database\Schema\Column;
use Closure;
use DB;

class Table {

  protected $table;

  protected $connection;

  protected $foreignKeys = [];

  const FOREIGN_HANDLERS = [
    'onUpdate'         , 'onDelete'         ,
    'cascadeOnUpdate'  , 'cascadeOnDelete'  ,
    'restrictOnUpdate' , 'restrictOnDelete' ,
    'nullOnUpdate'     , 'nullOnDelete'     ,
    'noActionOnUpdate' , 'noActionOnDelete' ,
  ];

  public function __construct($table, $connection = null) {
    $this->table = $table;
    $this->getConnection($connection);
  }

  public function getConnection($connection = null) {
    if (!$connection) $this->connection = Schema::getConnection($connection);
    if (!$this->connection) $this->connection = DB::connection();
    return $this->connection;
  }


  /**
   * Check if table exists
   *
   * @return bool
   * */
  public function hasTable() {
    return Schema::hasTable($this->table);
  }

  /**
   * Check if table has index
   * 
   * @param array $columns [string, string, ...]
   * @param string $type [index, unique, primary, ...]
   * @param string [$table] table name (optional, default: $this->table)
   * 
   * @return bool
   * */
  public function hasIndex($columns, $indexType = 'unique', $table = null) {
    $table = $table ?: $this->table;
    return Schema::hasIndex(
      $table, 
      $this->generateIndexName($columns, $indexType, $table),
      $indexType,
    );
  }

  /**
   * set timestamps columns
   * 
   * @return array
   * */
  public function timestamps($timestamp = null) {
    $timestamps = $timestamp ? ['created_at', 'updated_at', 'deleted_at'] : null;

    if (is_array($timestamp)) {
      $timestamps = array_merge($timestamps, $timestamp);
    }

    if (!is_array($timestamps)) return [];

    $cols = [];
    foreach ($timestamps as $col) {
      $cols[$col] = fn($tab) => $tab('datetime')->nullable();
    }
    return $cols;
  }

  /**
   * Generate index name from columns
   *
   * @param array $columns [string, string, ...]
   * @param string $type [index, unique, primary, ...]
   * @param string [$table] table name (optional, default: $this->table)
   *
   * @return string ex: '[table name]_[...column1_column2]_[index type]'
   * */
  protected function generateIndexName($columns, $type, $table = null): string {
    $table = $table ?: $this->table;
    $columns = is_array($columns) ? $columns : [$columns];
    $index = strtolower($table.'_'.implode('_', $columns).'_'.$type);
    return str_replace(['-', '.'], '_', $index);
  } 

  /**
   * Format fieldset for migration
   * 
   * @return array
   * */
  protected function formatFieldset(array $fieldset, $timestamps = null, $table = null) {
    $table = $table ?: $this->table;

    $fieldset = array_merge($fieldset, $this->timestamps($timestamps));

    $perKey = null;
    foreach ($fieldset as $column => &$set) {
      $set = $set(function () use($perKey, $table, $column) {
        $args = func_get_args();

        $column = (new Column($table, $column))->columnType(...$args);
        if (null != $perKey) $column->after($perKey);
        return $column;
      });
      $perKey = $column;
    }
    return $fieldset;
  }

  /**
   * Format forign key to migration
   * 
   * @return array
   * */
  protected function formatForeignKeys($foreign, array $refer) {
    @list($references, $table, $constraint) = $refer;
    $foreign = array_values(is_array($foreign) ? $foreign : [$foreign]);
    $references = array_values(is_array($references) ? $references : [$references]);
    $key = $this->generateIndexName($foreign, 'foreign');

    $constraint = $constraint ?: [];
    $constraint = array_keys(array_values($constraint)) === array_keys($constraint) ?
      array_flip($constraint) : $constraint;

    $onUpdate = $onDelete = 'restrict';

    foreach ($constraint as $handler => $val) {
      if (is_int($handler)) [$handler, $val] = [$val, 'restrict'];
      if (!in_array($handler, static::FOREIGN_HANDLERS)) continue;
      if (strpos($handler, 'cascade') !== false) $val = 'cascade';
      if (strpos($handler, 'restrict') !== false) $val = 'restrict';
      if (strpos($handler, 'null') !== false) $val = 'null';
      if (strpos($handler, 'noAction') !== false) $val = 'noAction';
      if (strpos($handler, 'onUpdate') !== false) $onUpdate = $val;
      if (strpos($handler, 'onDelete') !== false) $onDelete = $val;
    }

    return [
      'name' => $key,
      'columns' => $foreign,
      'foreign_table' => $table,
      'foreign_columns' => $references,
      'on_update' => $onUpdate,
      'on_delete' => $onDelete,
    ];
  }

  protected function setColumns(Blueprint $blueprint, array $fieldset) {
    foreach ($fieldset as $name => $col) {
      if ($col->columnType && !is_array($col->columnType))
        throw new SchemaException("Table: {$col->blueprint} -> {$name} typeof err");
      if (!$this->setColumn($blueprint, $col))
        throw new SchemaException("Table: {$col->blueprint} -> {$name} set Fail");
    }
    return $blueprint;
  }

  protected function setColumn(Blueprint $blueprint, Column $column) {
    $table = $this->table ?: $blueprint->getTable();

    // 重命名字段
    if ($column->to && Schema::hasColumn($blueprint, $column->to)) {
      $blueprint = $blueprint->renameColumn($column->to, $column->from ?: $column->name);
    }

    $set = $this->setColumnType($column, $blueprint);
    if (!$set) throw new SchemaException("Table `{$table}` Column `{$column->name}` set Fail");

    $this->mergeAttributeInColumn($column, $set);

    if ($column->has) $set->change();
    return $set;
  }

  protected function setColumnType(Column $column, Blueprint $blueprint) :ColumnDefinition {
    list($table, $name) = [$column->table, $column->name];
    @list($typeof, $length) = $column->columnType;

    switch($typeof) {
      case 'varchar':
      case 'char':
        $set = $blueprint->string($name, $length);
        break;
      case 'email':
        $set = $blueprint->string($name);
        break;
      case 'tinyint':
      case 'int':
      case 'bigint':
      case 'mediumint':
        $method = str_replace('int', 'Integer', $typeof);
        $set = $blueprint->$method($name, $column->increment == true);
        break;
      case 'double':
        $set = $blueprint->double($name, ...(!is_array($length)? [$length]: $length));
        break;
      case 'decimal':
        if (!is_array($length) || count($length) < 1) 
          throw new SchemaException("TABLE: {$table} FIELDSET {$name} TYPEOF decimal, BUT LENGTH is not array['precision', 'scale']");
        $set = $blueprint->decimal($name, ...$length);
        break;
      case 'rememberToken':
      case 'remember_token':
        $set = $blueprint->string($name, 128)->nullable();
        break;
      case 'id':
        $set = $blueprint->bigIncrements($name);
        break;
      case 'enum':
        //if (!is_array($length)) {
        //  throw new SchemaException("TABLE: {$table} FIELDSET {$name} TYPEOF enum, BUT LENGTH is not array");
        //}
        //$set = $blueprint->set($name, $length);
        if (!Schema::hasColumn($table, $name)) $set = $blueprint->set($name, $length);
        else {
          $type = Schema::getColumnType($table, $name);
          $set = $blueprint->set($name, $length);
        }
        break;
      case 'set':
        $set = $blueprint->set($name, $length);
        break;
      default:
        $args = array_filter([$name, $length]);
        $set = $blueprint->$typeof(...$args);
        break;
    }
    return $set;
  }

  protected function mergeAttributeInColumn(Column $column, ColumnDefinition &$define) {
    list($table, $name) = [$column->table, $column->name];

    foreach ($column->getAttributes() as $nk => $val) {
      switch($nk) {
      case 'unique':
        $hasColumn = $column->has;
        $hasUnique = $this->hasIndex($name, 'unique');
        if (!$hasColumn && true == $val) $define->unique();
        elseif ($hasColumn) {
          if (false === $val && $hasUnique) $define->dropUnique(
            $tis->generateIndexName($name, 'unique', $table)
          );
          elseif (false !== $val && !$hasUnique) $define->unique();
        }
        break;
      case 'index':
        if (false !== $val && !$this->hasIndex($name, 'index')) $define->index();
        break;
      case 'after':
        if (Schema::hasColumn($table, $val)) $define->after($val);
        break;
      case 'virtualAs':
        if (Schema::hasColumn($table, $name)) Schema::dropColumns($table, [$name]);
        $define->$nk($val);
        break;
      case 'primary':
        // auto increment has primary
        $canPrimary = !$column->nullable && (!$column->autoIncrement || !$column->increment);

        if (!$canPrimary && !$this->hasIndex($name, 'primary')) $define->primary();
        break;
      case 'foreign':
        if (is_array($val)) $this->foreignKeys[$name] = $val;
        break;
      default:
        $define->$nk($val);
        break;
      }
    }

    $define->comment(is_string($column->comment) ? $column->comment : null);
    return $define;
  }

  public function foreignsBinding(Blueprint $blueprint) {
    $keys = [];
    $foreignKeys = [];

    foreach (Schema::getForeignKeys($this->table) as $key) {
      $name = $key['name'];
      unset($key['foreign_schema']);
      $keys[$name] = $key;
    }

    foreach ($this->foreignKeys as $foreign => $refer) {
      $refer = $this->formatForeignKeys($foreign, $refer);
      $hit = $keys[$refer['name']] ?? false;
      ksort($refer);
      if ($hit) ksort($hit);
      if ($hit == $refer) continue;
      $foreignKeys[$refer['name']] = $refer;
    }

    foreach ($foreignKeys as $key => $refer) {
      if (isset($keys[$key])) $blueprint->dropForeign($key);
      $foreign = $refer['columns'];
      $table = $refer['foreign_table'];
      $references = $refer['foreign_columns'];

      $fk = $blueprint->foreign($foreign)->references($references)->on($table);
      $fk->onDelete($refer['on_delete']);
      $fk->onUpdate($refer['on_update']);
    }

    // foreach ($this->foreignKeys as $foreign => $refer) {
    //   @list($references, $table, $constraint) = $refer;
    //   $key = $this->generateIndexName($foreign, 'foreign');
    //   $has = $this->hasIndex($foreign, 'foreign');
    //   dd($key, $has);
    //   if ($has) $blueprint->dropIndex($key);

    //   $fk = $blueprint->foreign($foreign)->references($references)->on($table);
    //   $constraint = $constraint ?: [];
    //   $constraint = array_keys(array_values($constraint)) === array_keys($constraint) ?
    //     array_flip($constraint) : $constraint;
    //     
    //   foreach ($constraint as $handler => $val) {
    //     if (is_int($handler)) {
    //       $handler = $val;
    //       $val = 'RESTRICT';
    //     }
    //     if (!method_exists($fk, $handler)) continue;
    //     $val = is_int($val) ? 'RESTRICT' : $val;
    //     $val = is_array($val) ? $val : [$val];
    //     $fk->$handler(...$val);
    //   }
    // }
    $this->foreigns = [];
    return $this;
  }

  /**
   * Build table struct to migration
   * 
   * @param array $fieldset [column => type, ...]
   * @param array $options [table, timestamps, migration, comment, engine, drop_columns, ...]
   *
   * @return void
   * */
  public function build(array $fieldset, array $options = null) {
    $options = is_array($options) ? $options : [];
    $table = $options['table'] ?? $this->table;
    $timestamps = $options['timestamps'] ?? null;
    $fieldset = $this->formatFieldset($fieldset, $timestamps, $table);

    $this->blueprint = null;
    $options['has'] = $has = Schema::hasTable($table);
    $method = $has ? 'table' : 'create';
    //dd($table, $fieldset, $options);

    Schema::$method($table, function(Blueprint $blueprint) use($fieldset, $options) {
      $this->setColumns($blueprint, $fieldset)->comment($options['comment'] ?? null);

      if ($engine = $options['engine'] ?? null) $blueprint->engine = $engine;

      if (is_array($dropColumns = $options['drop_columns'] ?? null)) {
        foreach ($dropColumns as $column) {
          if (Schema::hasColumn($blueprint->getTable(), $column)) $blueprint->dropColumn($column);
        }
      }

      if ($this->foreignKeys) $this->foreignsBinding($blueprint);

      $migration = $options['migration'] ?? false;
      if ($migration instanceof Closure) $migration($blueprint, $this);
      $this->blueprint = $blueprint;
    });
  }

  public function getName() {
    return $this->table;
  }
}
