<?php

namespace Lx\Concerns\ModelTraits;

trait WhereArgs {

  protected $whereBinds = [];

  static protected $COND_MAPS = [
    // func, argslen
    'ISNULL'      => ['WhereNull', 1],
    'IS_NULL'      => ['WhereNull', 1],
    'ISNOTNULL'  => ['WhereNotNull', 1],
    'IS_NOT_NULL'  => ['WhereNotNull', 1],
    'IN'           => ['WhereIn', 2],
    'BETWEEN'      => ['WhereBetween', 2],
    'LIKE'         => ['WhereLike', 2],
    'NOLIKE'       => ['WhereNotLike', 2],
    'OR'           => ['orWhere', -1],
    'RAW'          => ['WhereRaw', 1],
    'JSONCONTAINS' => ['WhereJsonContains', 2],
    'WHERE'        => ['where', -1],
  ];

  public function parseWheres($whereStr): array {
    // if is base64 encode
    $first = strtolower(substr($whereStr, 0, 7));
    if ('base64:' == $first) $whereStr = base64_decode(substr($whereStr, 7));
    // 转换括号(括号、中括号)、引号(包括双引号)内值为占位符并且提取出来 | 排除转义字符
    $index = count($this->whereBinds ?: []);

    // 如果有转义字符，则替换为编码: \" => \x22 
    $whereStr = preg_replace_callback('/\\\\./', function($matches) {
      $value = str_replace("\\", "", $matches[0]);
      return '\\x'.dechex(ord($value));
    }, $whereStr);

    $whereStr = preg_replace_callback('/\(([^()]*)\)|\[([^\[\]]*)\]|"([^"]*)"/', function($matches) use (&$index) {
      $value = end($matches);
      $value = is_numeric($value) ? '\"'.$value : $value;
      $this->whereBinds[] = $value;
      return '(?#'.$index++.')';
    }, $whereStr);
    $where = explode(';', $whereStr);

    $parse = [];
    foreach ($where as $cond) $parse[] = $this->parseWhereCond($cond);
    return $parse;
  }

  public function parseWhereCond($cond) {
    $expr = strpos($cond, ':');
    if ($expr === false) $cond = 'WHERE:'.$cond;
    list($key, $value) = explode(':', $cond, 2);
    $key = strtoupper($key);
    $func = static::$COND_MAPS[$key] ?? null;
    if (!$func) $func = ['where', -1];
    list($func, $argslen) = $func;
    $argslen = $argslen > 0 ? $argslen : null;

    $iscond = strpos($value, '&') !== false;
    if ($iscond) $value = explode('&', $value);
    else $value = explode(',', $value, $argslen ?: null);

    $value = array_map(function($v) {
      $value = explode(',', $v);
      return count($value) > 1 ? $value : $v;
    }, $value);

    $value = $this->parseWhereValue($value, $iscond, 'RAW' == $key);
    $value = count($value) > 1 ? $value : ($value[0] ?? null);
    return ['func' => $func, 'value' => $value];
  }

  public function parseWhereValue ($value, $iscond = false, $debug = false) {
    if (is_array($value)) return array_map(function($v) use ($iscond, $debug) {
      return $this->parseWhereValue($v, $iscond, $debug);
    }, $value);

    $value = preg_replace_callback('/\(\?#(\d+)\)/', function($matches) {
      $index = $matches[1] ?? -1;
      return $this->whereBinds[$matches[1]] ?? $matches[0];
    }, $value);

    if ($iscond) {
      @list($_key, $_value) = explode(':', $value, 2);
      if (isset(static::$COND_MAPS[strtoupper($_key)])) return $this->parseWhereCond($value);
      elseif (strpos($value, ',') !== false) return $this->parseWhereCond($value);
    }

    // 转义字符还原
    $value = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', function($matches) use($value) {
      return chr(hexdec($matches[1]));
    }, $value);
    if (preg_match('/^\\\"([A-Za-z\-\d.]+)$/', $value, $matches)) return strval(end($matches));
    return is_numeric($value) ? $value + 0 : $value;
  }

  public function scopeWhereByArgs($builder, $args) {
    $args = is_string($args) ? $this->parseWheres($args) : $args;

    foreach ($args as $where) {
      $func = $where['func'] ?? null;
      $value = $where['value'] ?? null;
      if (!$func || !$value) continue;

      if (is_array($value)) {
        $isSubCond = count(array_filter(array_column($value, 'func'))) > 0;
        if ($isSubCond) {
          $builder->$func(function($query) use ($value) {
            $query->whereByArgs($value);
          });
        }
        else $builder->$func(...$value);
      }
      else $builder->$func($value);
    }
    return $builder;
  }

  public function scopeOrderByNULLS($builder, $field, $nulls = 'LAST') {
    $nulls = strtoupper($nulls) == 'FIRST' ? [1, 0] : [0, 1];
    $field = strpos($field, '`') === false && strpos($field, '.') == false ? "`$field`" : $field;
    $field = "IF(ISNULL($field),".implode(',', $nulls).') DESC';
    $builder->orderByRaw($field);
  }

}
