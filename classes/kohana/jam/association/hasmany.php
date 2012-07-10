<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Handles has one to relationships
 *
 * @package    Jam
 * @category   Associations
 * @author     Ivan Kerin
 * @copyright  (c) 2010-2011 OpenBuildings
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
abstract class Kohana_Jam_Association_HasMany extends Jam_Association_Collection {

	public $as;

	public $foreign_default = 0;

	public $count_cache = NULL;

	/**
	 * Automatically sets foreign to sensible defaults.
	 *
	 * @param   string  $model
	 * @param   string  $name
	 * @return  void
	 */
	public function initialize(Jam_Meta $meta, $model, $name)
	{
		// Empty? The model defaults to the the singularized name
		// of this field, and the field defaults to this field's model's foreign key
		if (empty($this->foreign))
		{
			$this->foreign = Inflector::singular($name).'.'.Jam::meta($model)->foreign_key();
		}
		// We have a model? Default the field to this field's model's foreign key
		elseif (FALSE === strpos($this->foreign, '.'))
		{
			$this->foreign = $this->foreign.'.'.Jam::meta($model)->foreign_key();
		}

		parent::initialize($meta, $model, $name);

		// Polymorphic associations
		if ($this->as)
		{
			if ( ! is_string($this->as))
			{
				$this->as = $this->model;
			}
			$this->foreign['as'] = $this->as.'_model';
			$this->foreign['field'] = $this->as.'_id';
		}

		// Count Cache
		if ($this->count_cache)
		{
			if ($this->is_polymorphic())
				throw new Kohana_Exception('Cannot use count cache on polymorphic associations');
			
			if ($this->count_cache === TRUE)
			{
				$this->count_cache = $name.'_count';
			}

			$meta->field($this->count_cache, Jam::field('integer', array('default' => 0, 'allow_null' => FALSE)));
		}
		

	}

	public function before_save(Jam_Model $model, $collection, $is_loaded)
	{
		parent::before_save($model, $collection, $is_loaded);

		if ($this->count_cache AND $collection !== NULL)
		{
			$model->{$this->count_cache} = count($collection);
		}
	}

	public function update_count_cache(Jam_model $model, $count = NULL)
	{
		if ($this->count_cache)
		{
			if ($count === NULL)
			{
				$count = $model->builder($this->name)->count();
			}
			else
			{
				$count = max(0, $model->{$this->count_cache} + $count);
			}

			Jam::query($this->model, $model->id())->value($this->count_cache, $count)->update();
		}
	}

	public function join(Jam_Builder $builder, $alias = NULL, $type = NULL)
	{
		$join = parent::join($builder, $type)
			->join($this->foreign(NULL, $alias), $type)
			->on($this->foreign('field', $alias), '=', "{$this->model}.:primary_key");
		
		if ($this->as)
		{
			$join->on($this->foreign('as', $alias), '=', DB::expr('"'.$this->model.'"'));
		}

		return $join;
	}

	public function builder(Jam_Model $model)
	{
		$model->loaded_insist();
		$builder = parent::builder($model)
			->where($this->foreign('field'), '=', $model->id());

		if ($this->as)
		{
			$builder->where($this->foreign('as'), '=', $model->meta()->model());
		}

		return $builder;
	}

	public function delete(Jam_Model $model, $key)
	{
		switch ($this->dependent) 
		{
			case Jam_Association::DELETE:
				foreach ($this->get($model) as $item) 
				{
					$item->delete();
				}
			break;
			case Jam_Association::ERASE:
				$this->builder($model)->delete();
			break;
			case Jam_Association::NULLIFY:
				$this->nullify_builder($model)->update();
			break;
		}
	}

	public function nullify_builder(Jam_Model $model)
	{
		$builder = $this->builder($model)
			->value($this->foreign('field'), $this->foreign_default);

		if ($this->as)
		{
			$builder->value($this->foreign('as'), NULL);
		}

		return $builder;
	}

	public function affected_model_ids(Jam_Model $model, Jam_Collection $collection)
	{
		$affected_ids = array();
		foreach ($collection as $item) 
		{
			$affected_ids[] = $item->original($this->foreign['field']);
		}
		unset($affected_ids[array_search($model->id(), $affected_ids)]);
		return $affected_ids;
	}

	public function after_save(Jam_Model $model, $collection, $is_changed)
	{
		parent::after_save($model, $collection, $is_changed);

		if ($this->count_cache AND $collection AND $collection->changed())
		{
			$affected_ids = $this->affected_model_ids($model, $collection);
		}

		if ($is_changed AND $collection AND $collection->changed())
		{
			$this->preserve_collection_changes($model, $collection);

			list($old_ids, $new_ids) = $this->diff_collection_ids($model, $collection);

			if (array_filter($old_ids))
			{
				$this->nullify_builder($model)->key($old_ids)->update();
			}
			
			if ($new_ids)
			{
				$new_items_builder = Jam::query($this->foreign())
					->key($new_ids)
					->value($this->foreign('field'), $model->id());

				if ($this->as)
				{
					$new_items_builder->value($this->foreign('as'), $model->meta()->model());
				}

				$new_items_builder->update();
			}

			if ($this->count_cache AND ! empty($affected_ids))
			{
				foreach (Jam::query($this->model)->find($affected_ids) as $item) 
				{
					$this->update_count_cache($item);
				}
			}
		}
	}

	/**
	 * See if the association is polymorphic
	 * @return boolean 
	 */
	public function is_polymorphic()
	{
		return (bool) $this->as;
	}

	public function assign_relation(Jam_Model $model, $item)
	{
		if ($item instanceof Jam_Model)
		{
			$item->set($this->foreign['field'], $model->id());

			if ($this->is_polymorphic())
			{
				$item->set($this->foreign['as'], $model->meta()->model());
			}
		}
		return parent::assign_relation($model, $item);
	}


} // End Kohana_Jam_Association_HasMany
