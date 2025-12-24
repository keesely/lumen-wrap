<?php

use Illuminate\Database\Eloquent\Model as BaseModel;

class ModelTest extends BaseModel {
  use Lx\Concerns\Searcher;
}

class ModelSearchTest extends TestCase
{

  use Lx\Concerns\Searcher;

  // declarative model conditions for url query
  protected $examples = [
    '_cols' => 'id,org_id,school_id,school.org:id,name',
    '_with' => [
      'school:org_id,name',
      'org:id,name&_where(status=1;LIKE:name,"%美术%")&_sort(id,desc)',
    ],
    '_with_count' => 'school',
    '_where' => [
      'IS_NOT_NULL:org_id',
      'IS_NULL:school_id',
      'IN:status,1,2,3,4',
      'BETWEEN:id,1,100',
      'LIKE:name,"%,%"',
      'NOLIKE:name,"%;%"',
      'OR:(IN:status,1,2)AND(org_id,>,10)',
      'OR:(LIKE:name,%美%)AND(status,1)AND(OR:status,3)',
      'RAW:"JSON_CONTAINS(attach, \'{\"key\": \"value\"}\')"',
      'JSONCONTAINS:attach->value_id,1',
      'id,>,10',
      'status,"2"',
      'value,"-1"',
      'value,>,"-1.2"',
    ],
    '_join' => [
      'INNERJOIN:school,school.id=school_id',
      'LEFTJOIN:org,org.id=org_id',
      'JOIN:org,org.id=org_id',
    ],
    '_group' => 'org_id',
    '_having' => 'COUNT(id),>,1',
    '_limit' => '0,10',
    '_append' => 'status_desc,org_name',
    '_distinct' => 'org_id',
    '_sort' => 'id,desc;org_id,asc;status:3,2,4,1,asc;raw(status NULLS LAST)',
  ];


  public function encodeExamples() {
    $examples = $this->examples;
    foreach ($examples as $key => $value) {
      if (is_array($value)) {
        $examples[$key] = implode(';', $value);
      }
    }
    return $examples;
    //return http_build_query($examples);
  }

  public function testParseAST() {
    $examples = $this->encodeExamples($this->examples);
    $ast = $this->parseAST($examples);
    $aseertEquals = [
      '_cols' => ['id', 'org_id', 'school_id', 'school.org:id', 'school.org:name'],
      '_with' => [
        'school:org_id,name',
        'org:id,name' => [
          'status' => '1',
          'name' => [
            'where' => [
              ['func' => 'WhereLkie', 'key' => 'name', 'value' => '%美术%'],
            ],
            'sort' => [
              ['field' => 'id', 'order' => 'desc']
            ]
          ]
        ]
      ],
      '_with_count' => ['school'],
      '_where' => [
        ['func' => 'WhereNotNull', 'value' => 'org_id'], // 'IS NOT NULL'
        ['func' => 'WhereNull', 'value' => 'school_id'], // 'IS NULL'
        ['func' => 'WhereIn', 'value' => ['status', [1, 2, 3, 4]]], // 'IN'
        ['func' => 'WhereBetween', 'value' => ['id', [1, 100]]], // 'BETWEEN'
        ['func' => 'WhereLike', 'value' => ['name', '%,%']], // 'LIKE'
        ['func' => 'WhereNotLike', 'value' => ['name', '%;%']], // 'NOT LIKE'
        [
          'func' => 'orWhere',
          'value' => [
            ['func' => 'WhereIn', 'value' => ['status', [1, 2]]],
            ['func' => 'where', 'value' => ['org_id', '>', 10]],
          ]
        ],
        [
          'func' => 'orWhere', 
          'value' => [
            ['func' => 'WhereLike', 'value' => ['name', '%美%']],
            ['func' => 'where', 'value' => ['status', 1]],
            ['func' => 'orWhere', 'value' => ['status', 3]],
          ],
        ], // 'OR'

        ['func' => 'WhereRaw', 'value' => 'JSON_CONTAINS(attach, \'{"key": "value"}\')'], // 'RAW'
        ['func' => 'WhereJsonContains', 'value' => ['attach->value_id', 1]], // 'JSON_CONTAINS'
        ['func' => 'where', 'value' => ['id', '>', 10]], // '>'
        ['func' => 'where', 'value' => ['status', '2']], // '='
        ['func' => 'where', 'value' => ['value', '-1']], // '='
        ['func' => 'where', 'value' => ['value', '>', '-1.2']], // '>'
      ],
      '_join' => [
        ['school', 'INNER JOIN', 'school.id=school_id'],
        ['org', 'LEFT JOIN', 'org.id=org_id'],
        ['org', 'JOIN', 'org.id=org_id'],
      ],
      '_group' => ['org_id'],
      '_having' => ['COUNT(id)', '>', 1],
      '_limit' => [0, 10],
      '_append' => ['status_desc', 'org_name'],
      '_distinct' => ['org_id'],
      '_sort' => ['id', 'desc', 'org_id', 'asc', 'status', '3,2,4,1', 'asc', 'raw(status NULLS LAST)'],
    ];

    dd($ast);

    foreach ($ast as $key => $value) {
      if (!is_array($value)) continue;
      foreach ($value as $k => $v) {
        ksort($v);
        $assert = $aseertEquals[$key][$k] ?? null;
        ksort($assert);
        $this->info($assert, ' => ', $v);
        //$this->assertEquals($assert, $v);
      }
    }

    $wheres = implode(';', [
      'org_id,>,1',
      'In:status,1,2,3,4',
      'OR:(NOLIKE:alias_name,%收银%)AND(upstream->acctId,A33512506)',
      'OR:(IS_NULL:alias_name)AND'
      //'OR:(default_flag,1)&(ISNOTNULL:deleted_at)',
      //'IS_NOT_NULL:deleted_at',
      //'ISNOTNULL:deleted_at',
      //'ISNULL:deleted_at',
    ]);
    //$astWhere = $this->parseWheres($wheres);
    $mode = ModelTest::select('id')->withTrashed();
    $mode->search(['_where' => $wheres]);
    //$mode->whereByArgs($wheres);
    //$mode->orderBy('deleted_at', 'desc');
    //$mode->orderByNULLS('deleted_at', 'first');
    //$mode->orderByNULLS('deleted_at');
    //$this->scopeWhereByASTArgs($mode, $astWhere);
    dd($mode->toSql(), $mode->getBindings());
  }

  public function scopeWhereByASTArgs($builder, $args) {
    foreach ($args as $where) {
      $func = $where['func'] ?? null;
      $value = $where['value'] ?? null;
      if (!$func || !$value) continue;
      if (is_array($value)) {
        $isSubCond = count(array_filter(Arr::pluck($value, 'func'))) > 0;
        if ($isSubCond) {
          $builder->$func(function($query) use ($value) {
            $this->scopeWhereByASTArgs($query, $value);
          });
        }
        else $builder->$func(...$value);
      }
    }
    return $builder;
  }

  public function parseAST($examples) {
    $whereStr = $examples['_where'] ?? '';
    $ast = [];
    $ast['_where'] = $this->parseWheres($whereStr);
    return $ast;
  }

  // 测试并列或查询
  public function testPlaceOr() {
    $example = [
      '_where' => implode(';', [
        //'id,>=,1;OR:(id));state,<=,3;OR:(state)',
        //'OR:(id,>=,"1:1")AND(state,<=,3)',
        //'OR:(ISNULL:id)AND(ISNULL:state)',
        //'(id,>=,"1:1")OR(state,3)',
        '(ISNULL:id)OR(ISNOTNULL:state)',
        'OR:(id,>=,"11:12:00")AND(LIKE:name,"%美术%")',
        'OR:(id,>=,"11:12:00")OR(LIKE:name,"%美术%")',
        'IN:status,1,2,3,4',
        'BETWEEN:id,1,100',
        'LIKE:name,"%美术%"',
        'NOLIKE:name,"%美术%"',
      ]),
    ];

    $row = ModelTest::search($example);
    dd($row->toSql(), $row->getBindings());

  }
}
