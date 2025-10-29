<?php

namespace Lx\Database\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\ColumnDefinition;
use Lx\Database\SchemaException;
use DB;
use Closure;

class Table {

  protected $conn;

  protected $table;

  protected $tableDetails;

  protected $foreignKeys = [];

  protected $blueprint;

  protected $dbalTypes = [];

  const TableDetailMethods = [
    'getUniqueConstraint',
    'getColumns',
    'getForeignKeyColumns',
    'getColumn',
    'getPrimaryKey',
    'getPrimaryKeyColumns',
    'getIndex',
    'getIndexes',
    'getUniqueConstraints',
    'getForeignKeys',
    'getComment',
    'getForeignKey',
    'getNamespaceName',
    'getShortestName',
    'getFullQualifiedName',
    'getName',
    'getQuotedName',
  ];

  public function __construct($table) {
    $this->table = $table;
  }

  protected function getSchemaConnection() {
    if (!$this->conn) {
      $this->conn = Schema::getConnection();
    }
    return $this->conn;
  }

  protected function getDoctrineSchemaManager() {
    return $this->getSchemaConnection()->getDoctrineSchemaManager();
  }

  protected function listTableDetails($table = null) {
    if (!$this->tableDetails || $table != $this->table) {
      $tableDetails = $this->getDoctrineSchemaManager()->listTableDetails($table ?: $this->table);
      if ($table != $this->table) {
        return $tableDetails;
      }
      $this->tableDetails = $tableDetails;
    }
    return $this->tableDetails;
  }

  public function getTableDetailBy($method, $table = null) {
    dd('getTableDetailBy', $method, $table);
    return $this->listTableDetails($table)->$method();
  }

  protected function generateIndexName($type, $columns, $tab = null) {
    $tab = $tab ?: $this->table;
    $columns = is_array($columns) ? $columns : [$columns];
    $index = strtolower($tab.'_'.implode('_', $columns).'_'.$type);
    return str_replace(['-', '.'], '_', $index);
  }

  protected function hasIndex($columns, $indexType = 'index', $table = null) {
    $table = $table ?: $this->table;
    return Schema::hasIndex(
      $table, 
      $this->generateIndexName($indexType, $columns, $table),
      $indexType,
    );
  }

  protected function hasUnique($columns, $table = null) {
    return $this->hasIndex($columns, 'unique', $table);
  }

  protected function hasPrimaryKey($columns, $table = null) {
    return $this->hasIndex($columns, 'primary', $table);
  }

  public function setComment($comment) {
    dd('setComment', $comment);
    return $this->listTableDetails()->setComment($comment);
  }

  protected function setColumn(Blueprint $blueprint, Column $column) {
    $table = $blueprint->getTable();

    if ($column->to && Schema::hasColumn($blueprint, $column->to)) {
      $blueprint = $blueprint->renameColumn($column->to, $column->from ?: $column->name);
    }

    $set = $this->setColumnType($column, $blueprint);
    if (!$set) throw new SchemaException("Table `{$table}` Column `{$column->name}` set Fail");

    $this->mergeAttributeInColumn($column, $set);

    if (Schema::hasColumn($table, $column->name)) $set->change();
    return $set;
  }

  protected function setColumns(Blueprint $blueprint, array $fieldset) {
    foreach ($fieldset as $name => $col) {
      if ($col->columnType && !is_array($col->columnType))
        throw new SchemaException("Table: {$col->blueprint} -> {$name} typeof err");
      $this->setColumn($blueprint, $col);
    }
    return $blueprint;
  }

  protected function mergeAttributeInColumn(Column $column, ColumnDefinition &$define) {
    list($table, $name) = [$column->table, $column->name];

    foreach ($column->getAttributes() as $nk => $val) {
      switch($nk) {
      case 'unique':
        if (false !== $val && !$this->hasUnique($name)) $define->unique();
        elseif (false === $val && $this->hasUnique($name)) $define->dropUnique($name);
        break;
      case 'index':
        if (false !== $val && !$this->hasIndex($table, $name)) $define->index();
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

        if (!$canPrimary && !$this->hasPrimary($table, $name)) $define->primary();
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
          $this->getDoctrineSchemaManager()
               ->getDatabasePlatform()
               ->registerDoctrineTypeMapping('set', $typeof);
        //  // 强制触发更新
        //  // if ($type === $typeof) {
        //  //   $statement = "ALTER TABLE `{$table}` MODIFY COLUMN `{$name}` varchar(255)";
        //  //   if ($column->after) $statement .= ' AFTER `'.$column->after.'`';
        //  //   DB::statement($statement);
        //  // }
          //  //$set = $blueprint->enum($name, $length);
          //$options = ['platformoptions' => compact('length', 'type')];
          //$set = $this->listTableDetails()->changeColumn($name, $options);
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

  protected function build(array $fieldset, array $options = null) {
    $options = is_array($options) ? $options : [];
    $table = $options['table'] ?? $this->table;
    $timestamps = $options['timestamps'] ?? null;
    $fieldset = $this->formatFieldset($fieldset, $timestamps, $table);

    $this->blueprint = null;
    $options['has'] = $has = Schema::hasTable($table);
    $method = $has ? 'table' : 'create';

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

  public function foreignsBinding(Blueprint $blueprint) {
    $keys = array_keys($this->getTableDetailBy('getForeignKeys'));

    foreach ($this->foreignKeys as $foreign => $refer) {
      @list($references, $table, $constraint) = $refer;
      if (in_array(($key = $this->generateIndexName('foreign', $foreign)), $keys)) {
        $blueprint->dropForeign($key);
      }
      $fk = $blueprint->foreign($foreign)->references($references)->on($table);
      $constraint = $constraint ?: [];
      $constraint = array_keys(array_values($constraint)) === array_keys($constraint) ?
        array_flip($constraint) : $constraint;
        
      foreach ($constraint as $handler => $val) {
        if (is_int($handler)) {
          $handler = $val;
          $val = 'RESTRICT';
        }
        $val = is_int($val) ? 'RESTRICT' : $val;
        $val = is_array($val) ? $val : [$val];
        $fk->$handler(...$val);
      }
    }
    $this->foreigns = [];
    return $this;
  }

  public function __call($name, $args) {
    if (in_array($name, self::TableDetailMethods)) {
      $options = array_merge([$name], $args);
      return $this->getTableDetailBy(...$options);
    }
    if (method_exists($this, $name)) return $this->$name(...$args);
    return null;
  }

  static function __callStatic($name, $args) {
    $obj = new self;
    return $obj->$name(...$args);
  }
}
