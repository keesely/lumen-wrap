<?php

namespace Lx\Concerns\ModelTraits;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
//use App\Support\HasJsonRelations;

trait Searcher {

  //use HasJsonRelations;

  protected $whereBinds = [];

  protected function indexOf($value) {
    $key = $this->indexOfKey ?: ($this->primaryKey ?: 'id');
    if (is_array($key)) return $this->where(function($query) use($key, $value) {
      $key = array_unique(array_filter(array_merge($key, $this->primaryKey ?: 'id')));
      foreach ($key as $k) $query->orWhere($k, $value);
    })->firstOrFail();
    return $this->where($key, $value)->firstOrFail();
  }

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
    //if (!$func) $func = ['where', -1];
    if (!$func) return $this->parseWhereCond('WHERE:'.$cond);
    list($func, $argslen) = $func;
    $argslen = $argslen > 0 ? $argslen : null;

    $iscondAnd = strpos($value, 'AND') !== false;
    $iscondOr = strpos($value, 'OR') !== false;
    if ($iscondAnd) $value = explode('AND', $value);
    elseif ($iscondOr) $value = explode('OR', $value);
    else $value = explode(',', $value, $argslen ?: null);

    $value = array_map(function($v) use($iscondOr) {
      $cond = $iscondOr ? 'OR:' : '';
      $value = array_map(function($v) use($cond) { return $cond . trim($v); }, explode(',', $v));
      return count($value) > 1 ? $value : $cond . $v;
    }, $value);

    $value = $this->parseWhereValue($value, $iscondAnd || $iscondOr, 'RAW' == $key);
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
      elseif (strpos($value, ':') !== false) {
        $builder->$func(function($builder) use ($value) {
          list($_key, $_value) = explode(':', $value, 2);
          if (isset(static::$COND_MAPS[$_key])) {
            $cond = $this->parseWhereCond($value);
            $func = $cond['func'] ?? $func;
            $value = $cond['value'] ?? $value;
            $builder->$func($value);
          }
        });
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

  protected function unserialWhereIn($in) :array {
    $in = explode(',', $in);
    return array_filter(array_trim($in));
  }

  protected function parseInValue($value, $mapFunc = null): array {
    $dot = ',';
    $hasSemic = false;  // 是否有分号
    if (is_string($value) && ($hasSemic = (strpos($value, ';') !== false))) $dot = ';';
    $value = is_array($value) ? $value : explode($dot, $value);
    $value = array_filter(array_map('trim', $value));
    //if ($hasSemic) $value = array_map(self::class.'::parseInValue', $value);
    if ($mapFunc) $value = array_map(static::formatFunc($mapFunc), $value);
    return array_filter($value);
  }

  protected function parseInMixValue($value, $mapFunc = null): array {
    $dot = ',';
    $colon = null;
    $hasColon = false;  // 是否有冒号
    $hasSemic = false;  // 是否有分号
    if (is_string($value) && ($hasColon = (strpos($value, ':') !== false))) $colon = ':';
    if (is_string($value) && ($hasSemic = (strpos($value, ';') !== false))) $dot = ';';

    if ($hasColon) {
      $value = array_filter(is_array($value) ? $value : explode(';', $value));
      $arr = [];
      foreach ($value as $v) {
        @list($key, $val) = explode($colon, $v);
        if (!$val) $arr[] = array_filter(array_map('trim', explode(',', $key)));
        $arr[$key] = array_filter(array_map('trim', explode(',', $val)));
      }
      $value = array_filter($arr);
    }
    else {
      $value = is_array($value) ? $value : explode($dot, $value);
      $value = array_filter(array_map('trim', $value));
      if ($hasSemic) $value = array_map(self::class.'::parseInValue', $value);
    }
    if ($mapFunc) $value = array_map(static::formatFunc($mapFunc), $value);
    return array_filter($value);
  }

  /**
   * @apiDefine ModelSearch 通用查询器
   * @apiParam {String} _cols 查询字段, 逗号分隔
   * 例如: id,name,created_at
   * 或 id,name,realm:realm_id,name
   * @apiParam {String} _with 关联表获取
   * 例如: tab1;tab2;tab3:id,name
   * @apiParam {String} _in 枚举查询字段
   * 例如: id:1,2,3;name:张三,李四
   * @apiParam {String} _append 获取预设追加字段
   * @apiParam {String} _count 获取关联表数量
   * @apiParam {String} _sort 排序字段
   * 例如: id,desc;name,asc
   * @apiParam {String} _where 声明式查询条件
   * - 条件查询: id,>,1;name,张三;
   * - IN枚举查询: IN:status,1,2,3;IN:type,type1,type2,type3;
   * - LIKE查询: LIKE:name,%张三%;
   * - NOT LIKE查询: NOLIKE:name,%张三%;
   * - 或查询: OR:id,>,1;OR:(LIKE:name,%张三%);
   * - 并列或查询: OR:(id,>,1)&(LIKE:name,%张三%);
   * - 是空值：IS_NULL:field
   * - 非空值：IS_NOT_NULL:field
   * - 区间查询: BETWEEN:id,1,100
   * - JSON参数查询: attach->value_id,1;
   * - JSON查询2: JSONCONTAINS:attach->value_id,1;
   * - 原始查询: RAW:"JSON_CONTAINS(attach, '{\"key\": \"value\"}')";
   * - 字符串查询: price,>,"-1.2";
   * */
  public function scopeSearch($builder, $params) {
    $inputs = $params instanceof Collection ? $params : collect($params);
    $idents = ['_cols', '_fields', '_with', '_in', '_append', '_count', '_sort', '_where', '_has', '_hasmorph', '_belongs'];
    $whereKeys = $this->getFields();
    if (is_array($this->searchable)) $whereKeys = array_merge($whereKeys, $this->searchable ?: []);
    $where = $inputs->only($whereKeys)->toArray();
    $_wheres = $inputs->get('_where');
    $_has = $inputs->get('_has');
    $_hasmorph = $inputs->get('_hasmorph');
    $cols = $inputs->get('_cols', $inputs->get('_fields'));
    $with = $inputs->get('_with');
    $inValue = $inputs->get('_in');
    $append = $inputs->get('_append');
    $count = $inputs->get('_count');
    $sort = $inputs->get('_sort');
    $belongs = $inputs->get('_belongs');

    if ($belongs && $belongs = static::parseInMixValue($belongs)) {
      if (is_array($belongs)) {
        foreach ($belongs as $key => $value) {
          $value = implode(',', is_array($value) ? $value : [$value]);
          $val2 = [];
          preg_match_all('/\[(.+)\]/', $value, $matches);
          if (count($matches[1]) > 0) {
            $val2 = $matches[1][0];
            $value = str_replace("[$val2]", "", $value);
            $val2 = static::parseInValue($val2);
          }
          $value = static::parseInValue($value);
          $builder->belongs($key, $value, $val2);
        }
      }
    }

    if ($where) $builder->parseWhere($where);
    if ($_wheres) $builder->whereByArgs($_wheres);
    if ($_has) $builder->parseHas($_has);
    if ($_hasmorph) $builder->parseHasMorph($_hasmorph);
    if ($cols) $builder->select(static::parseInValue($cols));
    if ($with) $builder->with(static::parseInValue($with));
    if ($inValue && $inValue = static::parseInMixValue($inValue)) {
      foreach ($inValue as $field => $value) $builder->whereIn($field, $value);
    }

    if ($append && method_exists($this, 'scopeWithAppends'))
      $builder->withAppends(static::parseInValue($append));

    if ($count) return $builder->withCount(static::parseInValue($count));
    if ($sort = static::parseInValue($sort)) {
      foreach ($sort as $field) {
        if (is_array($field)) $builder->orderBy(...$field);
        else {
          list($field, $order) = array_pad(explode(',', $field), 2, 'asc');
          $builder->orderBy($field, $order);
        }
      }
      //foreach ($sort as $field => $order) $builder->orderBy($field, $order);
    }
    return $builder;
  }

  public function scopeParseWhere($builder, array $where) {
    foreach ($where as $field => $value) {
      if (is_array($value)) {
        foreach ($value as $val) $builder->parseWhere([$field => $val]);
      }
      else if (is_string($value) && strpos(strtolower($value), 'cnd::') === 0) {
        $value = mb_substr($value, 5);
        $value = explode(',', $value);
        $op = array_shift($value);
        $value = count($value) > 0 ? (count($value) > 1 ? $value : $value[0]) : null;

        if (strtolower($op) == 'in') $builder->whereIn($field, $value);
        else $builder->where($field, $op, $value);
      }
      else $builder->where($field, $value);
    }
    return $builder;
  }

  protected function parseHasArgs($has) {
    $has = array_filter(array_map('trim', explode(';', $has)));
    $whereHas = [];
    foreach($has as $item) {
      $item = array_filter(array_map('trim', explode(':', $item)));
      $tab = array_shift($item);
      if (!$item) continue;
      $item = count($item) > 1 ? implode(':', $item) : $item[0];
      $whereHas[$tab] = $whereHas[$tab] ?? '';
      $whereHas[$tab] = $whereHas[$tab] ? implode(';', [$whereHas[$tab], $item]) : $item;
    }
    return array_filter(
      array_map(function($item) {
        $item = trim($item);
        if (!$item) return null;
        return rtrim($item, ';').';';
      }, $whereHas)
    );
  }

  public function scopeParseHas($builder, $has) {
    $whereHas = $this->parseHasArgs($has);
    foreach ($whereHas as $tab => $item) {
      if (!method_exists($this, $tab)) continue;
      $hasFunc = $this->$tab();
      if ($hasFunc instanceof MorphTo) {
        $builder->whereHasMorph($tab, '*', function($query) use ($item) {
          if (method_exists($query, 'scopeWhereByArgs')) $query->whereByArgs($item);
          else $this->scopeWhereByArgs($query, $item);
        });
      }
      else
        $builder->whereHas($tab, function($query) use ($item) {
          if (method_exists($query, 'scopeWhereByArgs')) $query->whereByArgs($item);
          else $this->scopeWhereByArgs($query, $item);
        });
    }
    return $builder;
  }

  /**
   * 解析多态关联查询
   * 'morphto[->relation_key]:[CND:]field,[cond,]value'
   * */
  public function scopeParseHasMorph($builder, $has) {
    $whereHas = $this->parseHasArgs($has);
    $morphs = $this->morphMapping();
    if (!is_array($morphs)) return $builder;
    $maps = array_values($morphs);
    foreach ($whereHas as $tab => $item) {
      $map = '*';
      if (strpos($tab, '->') !== false) {
        list($tab, $map) = explode('->', $tab);
        $map = $morphs[$map] ?? '*';
      }
      if (!method_exists($this, $tab)) continue;
      $builder->whereHasMorph($tab, $map, function($query) use ($item) {
        if (method_exists($query, 'scopeWhereByArgs')) $query->whereByArgs($item);
        else $this->scopeWhereByArgs($query, $item);
      });
    }
    return $builder;
  }

  public function scopeWhereLike($builder, $field, $like) {
    return $builder->where($field, 'like', $like);
  }

  public function scopeWhereNotLike($builder, $field, $like) {
    return $builder->where($field, 'not like', $like);
  }

  // 注入自定义查询方法
  public function scopeLast($builder, ...$cols) {
    $builder->orderBy('id', 'desc');
    if (count($cols)) $builder->select(...$cols);
  }

  public function scopeBelongs($builder, $key, array $relations = [], array $cols = []) {
    @list($related, $foreign, $owner, $relation) = array_pad($relations, 4, null);

    $related = str_replace('/', '\\', $related);
    $relModel = class_exists($related) ? $related : 'App\\'.$related;
    if (!class_exists($relModel)) $relModel = 'App\Model\\'.$related;
    if (!class_exists($relModel)) return $builder;

    $builder->getModel()::resolveRelationUsing(
      $key,
      function ($orginalModel) use ($relModel, $foreign, $owner, $relation, $cols) {
        $rel = $orginalModel->belongsTo($relModel, $foreign, $owner, $relation);
        if (count($cols)) {
          $cols = array_filter(array_merge([$owner ? $owner : 'id'], $cols));
          $rel->select($cols);
        }
        return $rel;
      }
    );

    return $builder->with($key);
  }
}
