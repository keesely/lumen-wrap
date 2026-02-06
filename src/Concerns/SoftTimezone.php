<?php
/**
 * 
 * @fileName Concerns/SoftTimezone.php
 * @category PHP
 * @package Lumen-Wrap
 * @author Kee Guo <chinboy2012@gmail.com> 
 * @since 04/02/2026
 * @version Concerns/SoftTimezone.php 2026.02.04
 * */
namespace Lx\Concerns;

use DateTimeInterface;

trait SoftTimezone {

	// protected function fromTimezone() {
	// 	return array_values(array_filter([
	// 		config('app.timezone'),
	// 		date_default_timezone_get(),
	// 		'UTC',
	// 	]))[0];
	// }

	/**
	 * Prepare a date for array / JSON serialization to standard date
	 *
	 * @param  \DateTimeInterface  $date
	 * @return string
	 */
	protected function serializeStandardTzDate(DateTimeInterface $date)
	{
		$time = carbon($date . ' UTC');
		return $this->serializeDate($time);
	}

	public function fromDateTime($time) {
		return empty($time) ? $time 
			: parent::fromDateTime($this->asDateTime($time)->tz('UTC'));
	}

	/**
	 * Add the casted attributes to the attributes array.
	 *
	 * @param  array  $attributes
	 * @param  array  $mutatedAttributes
	 * @return array
	 */
	protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes)
	{
		foreach ($this->getCasts() as $key => $value) {
			if (! array_key_exists($key, $attributes) ||
				in_array($key, $mutatedAttributes)) {
				continue;
			}

			// Here we will cast the attribute. Then, if the cast is a date or datetime cast
			// then we will serialize the date for the array. This will convert the dates
			// to strings based on the date format specified for these Eloquent models.
			$attributes[$key] = $this->castAttribute(
				$key, $attributes[$key]
			);

			// If the attribute cast was a date or a datetime, we will serialize the date as
			// a string. This allows the developers to customize how dates are serialized
			// into an array without affecting how they are persisted into the storage.
			if (isset($attributes[$key]) && in_array($value, ['date', 'datetime', 'immutable_date', 'immutable_datetime'])) {
				$attributes[$key] = $this->serializeStandardTzDate($attributes[$key]);
			}

			if (isset($attributes[$key]) && ($this->isCustomDateTimeCast($value) ||
				$this->isImmutableCustomDateTimeCast($value))) {
				$attributes[$key] = $attributes[$key]->format(explode(':', $value, 2)[1]);
			}

			if ($attributes[$key] instanceof DateTimeInterface &&
				$this->isClassCastable($key)) {
				$attributes[$key] = $this->serializeStandardTzDate($attributes[$key]);
			}

			if (isset($attributes[$key]) && $this->isClassSerializable($key)) {
				$attributes[$key] = $this->serializeClassCastableAttribute($key, $attributes[$key]);
			}

			if ($this->isEnumCastable($key) && (! ($attributes[$key] ?? null) instanceof Arrayable)) {
				$attributes[$key] = isset($attributes[$key]) ? $this->getStorableEnumValue($this->getCasts()[$key], $attributes[$key]) : null;
			}

			if ($attributes[$key] instanceof Arrayable) {
				$attributes[$key] = $attributes[$key]->toArray();
			}
		}

		return $attributes;
	}

	/**
	 * Get the value of an attribute using its mutator for array conversion.
	 * 使用赋值器获取值以便数组转换
	 *
	 * @param  string  $key
	 * @param  mixed  $value
	 * @return mixed
	 */
	protected function mutateAttributeForArray($key, $value)
	{
		if ($this->isClassCastable($key)) {
			$value = $this->getClassCastableAttributeValue($key, $value);
		} elseif (isset(static::$getAttributeMutatorCache[get_class($this)][$key]) &&
			static::$getAttributeMutatorCache[get_class($this)][$key] === true) {
			$value = $this->mutateAttributeMarkedAttribute($key, $value);

			$value = $value instanceof DateTimeInterface
				? $this->serializeStandardTzDate($value)
				: $value;
		} else {
			$value = $this->mutateAttribute($key, $value);
		}

		return $value instanceof Arrayable ? $value->toArray() : $value;
	}

	/**
	 * Add the date attributes to the attributes array.
	 * 添加日期到 attributes 数组(created_at,updated_at,deleted_at...)
	 *
	 * @param  array  $attributes
	 * @return array
	 */
	protected function addDateAttributesToArray(array $attributes)
	{
		foreach ($this->getDates() as $key) {
			if (! isset($attributes[$key])) {
				continue;
			}

			$attributes[$key] = $this->serializeStandardTzDate(
				$this->asDateTime($attributes[$key])
			);
		}

		return $attributes;
	}

	protected function isDatetime($value) {
		// 判断是否是日期时间
		if ($value instanceof DateTimeInterface) return true;
		if (false === ($time = strtotime($value))) return false;
		return false !== date('c', $time);
	}

	/**
	 * Transform a raw model value using mutators, casts, etc.
	 *
	 * @param  string  $key
	 * @param  mixed  $value
	 * @return mixed
	 */
	protected function transformModelValue($key, $value)
	{
		if ($this->isDatetime($value)) {
			$value = $this->serializeStandardTzDate(carbon($value));
		}
		return parent::transformModelValue($key, $value);
	}

}
