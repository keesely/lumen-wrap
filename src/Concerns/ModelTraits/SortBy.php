<?php
namespace Lx\Concerns\ModelTraits;

trait SortBy {

  // withSortBy : array | string by split(';')
  // like: ['id' => 'desc', 'name' => 'asc'] OR
  // like: ['FIELD(id, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10)' => 'DESC', 'id' => 'ASC'] OR
  // like: 'id,DESC;name,ASC' OR
  // like: 'FIELD(id, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10),DESC;name,ASC'
  public function scopeSortBy($builder, $sortby) {
    $sortby = is_array($sortby) ? $sortby : explode(';', $sortby);
    $sortby = array_keys($sortby) !== range(0, count($sortby) - 1)
      ? collect($sortby)->map(function($v, $k) {
        if (strpos($k, 'FIELD(') === 0) $k = \DB::raw($k);
        return [$k, $v];
      })->values()->toArray()
      : array_map(function($v) {
        $v = trim($v);
        if (strpos($v, 'FIELD(') === 0) {
          $field = substr($v, 6, strpos($v, ')') - 6);
          $field = str_replace(' ', '', $field);
          $field = 'FIELD(' . $field . ')';
          $by = substr($v, strpos($v, ')') + 1);
          $by = array_values(array_filter(explode(',', $by)))[0] ?? 'ASC';
          return [\DB::raw($field), $by];
        }
        list($field, $by) = array_values(array_filter(explode(',', $v)));
        return [$field, $by];
      }, $sortby);
    foreach ($sortby as $v) {
      list($field, $by) = $v;
      if ($field instanceof \Illuminate\Database\Query\Expression) {
        $builder->orderByRaw($field . ' ' . $by);
      } else {
        $builder->orderBy($field, $by);
      }
    }
    return $builder;
  }
}
