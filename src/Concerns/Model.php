<?php

namespace Lx\Concerns;

use Illuminate\Support\Arr;
use DateTimeInterface;

trait Model {

  /**
   * @define ignore fields in fillable
   * @var array
   * */
  //protected $ignored = [];


  /**
   * @define ignore fields in fillable extra attributes
   * @var array
   * */
  protected $_extrattrs = [];

  /**
   * Prepare a date for array / JSON serialization.
   *
   * @Change autoset timezone from request header: x-timezone
   *
   * @param  \DateTimeInterface  $date
   * @return string
   */
  protected function serializeDate(DateTimeInterface $date)
  {
    $tz = array_values(array_filter([
      request()->header('x-timezone'),
      config('app.timezone'),
      'UTC',
    ]))[0];
    return $date->tz($tz)->format('Y-m-d H:i:s');
  }

  /**
   * Resets the model's attributes fillable
   *
   * @param array|Collection $data 
   * @param array $ignored = []
   *
   * @return Illuminate\Database\Eloquent\Model
   * */
  protected function fills(array|Collection $data, array $ignored = []) {
    $keys = is_array($data) ? array_keys($data) : $data->keys();
    if(count($ignored) > 0) $this->ignored = $ignored;
    $this->fillable = $keys;
    return $this->fill($data);
  }

  static function boot() {
    parent::boot();

    // custom event monitor in model observes
    foreach ([
      'retrieved' => ['afterRetrieved', 'afterFetched'],
      'creating'  => ['beforeCreate'  , 'beforeCreating'],
      'updating'  => ['beforeUpdate'  , 'beforeUpdating'],
      'deleting'  => ['beforeDelete'  , 'beforeDeleting'],
      'created'   => 'afterCreated',
      'updated'   => 'afterUpdated',
      'deleted'   => 'afterDeleted',
      'saving'    => ['beforeSave', 'beforeSaving', 'fireSavingEvent'],
      'saved'     => ['afterSave' , 'afterSaved'  , 'fireSavedEvent'],
    ] as $ob => $observes) {
      static::$ob(fn($model) => $model->fireModelEvents($model, $observes));
    }
  }

  /**
   * Fire model events
   * @param $model
   * @param closure|string|array $observes
   *
   * @return void
   * */
  public function fireModelEvents($model, closure|string|array $observes) {
    foreach ((is_array($observes) ? $observes : [$observes]) as $observe) {
      if (is_callable($observe)) {
        $observe($model);
        continue;
      }
      is_string($observe) && method_exists($model, $observe) && $model->$observe($model);
    }
  }

  /**
   * Fire envent at before saving
   * */
  public function fireSavingEvent($row) {
    if (is_array($row->ignored) && count($row->ignored)) {
      $attrs = collect($row->attributes);
      $row->attributes = $attrs->except($row->ignored)->toArray();
      $row->_extrattrs = $attrs->only($row->ignored)->toArray();
    }

    if ($row->isDirty() && ($dirty = $row->getDirty()) && method_exists($row, 'beChange')) {
      $row->beChange(
        $original = collect($row->getOriginal())->only(array_keys($dirty)),
        $values = collect($row->toArray())->only(array_keys($dirty)),
      );
    }
  }

  /**
   * Fire envent at after saved
   * */
  public function fireSavedEvent($row) {
    $row->attributes = array_merge($row->attributes, $this->_extrattrs ?: []);
    $this->_extrattrs = [];
  }
}
