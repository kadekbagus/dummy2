<?php namespace OrbitRelation;
/**
 * Extends Illuminate\Database\Eloquent\Relations\BelongsTo to provide an
 * ability to eager loads based on another condition not just the primary key.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Database\Eloquent\Relations\BelongsTo as BT;

class BelongsTo extends BT
{
	/**
	 * Gather the keys from an array of related models.
	 *
	 * @param  array  $models
	 * @return array
	 */
	protected function getEagerModelKeys(array $models)
	{
		$keys = array();

		// First we need to gather all of the keys from the parent models so we know what
		// to query for via the eager loading query. We will add them to an array then
		// execute a "where in" statement to gather up all of those related records.
		$expectedRelation = strtolower($this->relation);
		foreach ($models as $model)
		{
			$objectName = strtolower($model->object_name);
			if ( ! is_null($value = $model->{$this->foreignKey}))
			{
				if (! empty($objectName)) {
					if ($objectName === $expectedRelation) {
						$keys[] = $value;
					}
				} else {
					$keys[] = $value;
				}
			}
		}

		// If there are no keys that were not null we will just return an array with 0 in
		// it so the query doesn't fail, but will not return any results, which should
		// be what this developer is expecting in a case where this happens to them.
		if (count($keys) == 0)
		{
			return array(0);
		}

		return array_values(array_unique($keys));
	}
}
