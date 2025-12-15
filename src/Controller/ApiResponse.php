<?php

namespace Lx\Controller;

use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as ModelBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class ApiResponse {

  public function __construct(
    protected $response,
    protected Request $request
  ) {}


  public function __invoke($controller) {
    $resp = $this->response;
    if ($resp instanceof ModelBuilder) {
      return $this->responseWithModelBuilder($resp, $controller);
    }

    if ($resp instanceof Model) {
      return $this->responseWithModel($resp, $controller);
    }

    if ($resp instanceof QueryBuilder) {
      return $this->responseWithQuery($resp, $controller);
    }

    if ($resp instanceof Paginator) {
      return $this->responseWithSimplePages($resp, $controller);
    }

    if ($resp instanceof LengthAwarePaginator) {
      return $this->responseWithPages($resp, $controller);
    }

    return $resp;
  }

  public function responseWithSimplePages(Paginator $response, $controller) {
      $controller->extend('pages', array_combine([
        'current', 'previous', 'next', 'from', 'to', 'limit', 'simple',
      ], [
        $current = $response->currentPage(),
        $current > 1 ? $current - 1 : 1,
        $response->hasMorePages() ? $current + 1 : $current,
        $response->firstItem(),
        $response->lastItem(),
        $response->perPage(),
        true,
      ]));
      return $controller->result($response->items());
  }

  public function responseWithPages(LengthAwarePaginator $response, $controller) {
    $controller->extend('pages', array_combine([
      'current', 'previous', 'next', 'last', 'total', 'limit'
    ], [
      $current = $response->currentPage(),
      $current > 1 ? $current - 1 : 1,
      $response->hasMorePages() ? $current + 1 : $current,
      $response->lastPage(),
      $response->total(),
      $response->perPage(),
    ]));
    return $controller->result($response->items());
  }

  public function responseWithModelBuilder(ModelBuilder $response, $controller) {
    $req = $this->request;
    $length = (int) array_values(array_filter([
      $req->input('per_page'),
      $req->input('limit'),
      $req->input('length'),
      15,
    ]))[0];
    $model = $response->paginate($length);
    return $this->responseWithPages($model, $controller);
  }

  public function responseWithQuery(QueryBuilder $response, $controller) {
    $req = $this->request;
    $length = (int) array_values(array_filter([
      $req->input('per_page'),
      $req->input('limit'),
      $req->input('length'),
      15,
    ]))[0];
    $model = $response->paginate($length);
    return $this->responseWithPages($model, $controller);
  }

  public function responseWithModel(Model $response, $controller) {
    return $controller->result($response);
  }
}
