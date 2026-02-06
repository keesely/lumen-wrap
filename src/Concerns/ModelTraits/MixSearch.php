<?php
namespace Lx\Concerns\ModelTraits;

trait WithAppend {

  /**
   * 解析字符串转with , 例如:
   * 1. 'a;b;c' => ['a', 'b', 'c']
   * 2. 'a:aa,ab,ac;b:ba,bb,bc' => ['a:aa,ab,ac', 'b:ba,bb,bc']
   * 3. 'a.b:aa,ab,ac;b.c:ba,bb,bc' => ['a.b:aa,ab,ac', 'b.c:ba,bb,bc']
   * */
  protected function parseWith($value) {
    $dot = ';';
    $hasSemic = false;
    $value = is_array($value) ? $value : explode($dot, $value);
    $value = array_filter(array_map('trim', $value));
    if ($hasSemic) array_map(self::class.'::parseWith', $value);
    return $value;
  }

  protected function formatFunc($mapFunc) {
    if (strpos($mapFunc, 'in:') === 0) {
      $inValue = static::parseInValue(substr($mapFunc, 3));
      return function ($value) use($inValue) {
        $value = trim($value);
        return in_array($value, $inValue) ? $value : false;
      };
    }
    if (trim($mapFunc) == 'date') return function($value) {
      return carbon($value)->toDateString();
    };
    if (strpos($mapFunc, 'date_format:') === 0) {
      $format = str_replace('date_format:', '', $mapFunc);
      if ($format) {
        return function ($value) use($format) {
          return carbon($value)->format($format);
        };
      }
    }
    return $mapFunc;
  }

  protected function parseBetweenIn($between, $format = 'Y-m-d', $step = '1days'): array {
    $between = static::parseInValue($between, 'date_format:'.$format);
    if (!$between) return [];
    // 取数字
    $_step = intval($step);
    $_unit = trim(substr($step, strlen(strval($_step))));
    $units = [
      'seconds' => 'addSecond',
      'minutes' => 'addMinute',
      'hours' => 'addHour',
      'days' => 'addDay',
      'weeks' => 'addWeek',
      'months' => 'addMonth',
      'years' => 'addYear',
    ];

    if (count($between) == 1 && $_step > 0 && ($stepUnit = $units[$_unit] ?? 'addDay')) {
      $between[] = carbon($between[0])->$stepUnit($_step)->format($format);
    }
    return $between;
  }

  public function scopeBetweenIn($builder, $field, $between, $format = 'Y-m-d', $step = '0days') {
    $between = static::parseBetweenIn($between, $format, $step);
    if (2 === count($between)) $builder->whereBetween($field, $between);
    elseif (1 === count($between)) $builder->where($field, current($between));
  }
 
  public function scopeBetweenInDate($builder, $field, $between, $format = 'Y-m-d', $step = 'days') {
    $field = $this->db()->raw('DATE(`'.$field.'`)');
    return $builder->betweenIn($field, $between, $format, $step);
  }

  public function scopeJsonOverlaps($builder, $field, array $arr) {
    $field = strpos($field, '.') !== false || strpos($field, '->') !== false ? $field : '`'.$field.'`';
    $arr = "[\"".implode("\",\"", $arr)."\"]";
    return $builder->whereRaw("JSON_OVERLAPS({$field}, '{$arr}') > ?", 0);
  }

  /**
   * 合并字段模糊查询
   * @param {string} $word 关键字
   * @param {array|string} $fields 合并字段｜默认则取表内所有字段
   * @param {bool} $dual 是否双模查询 LIKE '%?%'
   * */
  public function scopeHasKeyword($builder, $word, $fields = null, $dual = false) {
    $fields = static::parseInValue($fields ?: $this->getFields());
    $concat = collect($fields)->map(function ($item) {
      return "IFNULL({$item}, '')";
    })->values()->toArray();
    $concat = 'CONCAT('.implode(',', $concat).')';
    $word = $dual ? '%'.$word : $word;
    $builder->whereRaw("{$concat} LIKE ?", $word.'%');
  }

  // 清理
  public function fillCleanAttributes(Model &$row, array $fillKeys = []) {
    foreach ($fillKeys as $key) {
      if ($row->$key) unset($row->$key);
    }
    return $row;
  }

}
