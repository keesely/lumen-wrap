<?php
/**
 * 
 * @fileName HasJsonRelations.php
 * @category PHP
 * @package void
 * @author Author 
 * @since 11/01/2022
 * @version HasJsonRelations.php 2022.01.11
 * */
namespace Lx\Concerns;

use Staudenmeir\EloquentJsonRelations\HasJsonRelationships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasJsonRelations {

  use HasJsonRelationships;

  /**
   * Instantiate a new BelongsToJson relationship.
   *
   * @param \Illuminate\Database\Eloquent\Builder $query
   * @param \Illuminate\Database\Eloquent\Model $child
   * @param string $foreignKey
   * @param string $ownerKey
   * @param string $relation
   * @return \Staudenmeir\EloquentJsonRelations\Relations\BelongsToJson
   */
  protected function newBelongsToJson(Builder $query, Model $child, $foreignKey, $ownerKey, $relation) {
    return new BelongsToJson($query, $child, $foreignKey, $ownerKey, $relation);
  }
}
