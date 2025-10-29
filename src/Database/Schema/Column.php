<?php
/**
 * 
 * @fileName Column.php
 * @category PHP
 * @package void
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 30/05/2022
 * @version Column.php 2022.05.30
 * */

namespace Lx\Database\Schema;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

class Column extends ColumnDefinition {

  public function __construct($table, $column) {
    $this->attributes['table'] = $table;
    $this->attributes['column'] = $column;
    $this->attributes['name'] = $column;
    $this->attributes['has'] = Schema::hasColumn($table, $column);
  }

  public function columnType(...$types) {
    $this->columnType = $types;
    return $this;
  }

  public function foreign($references, $table, array $onHandlers = null) {
    $this->attributes['foreign'] = [$references, $table, $onHandlers];
    return $this;
  }

  public function hasExist() {
    return $this->attributes['has'];
  }
}
