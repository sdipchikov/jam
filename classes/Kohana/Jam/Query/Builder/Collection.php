<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * A collection of jam models from the database.
 * 
 * @package    Jam
 * @category   Associations
 * @author     Ivan Kerin
 * @copyright  (c) 2011-2012 Despark Ltd.
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
abstract class Kohana_Jam_Query_Builder_Collection extends Jam_Query_Builder_Select implements Countable, ArrayAccess, Iterator, Serializable {

	/**
	 * Create object of class Jam_Query_Builder_Collection
	 * @param  string $model 
	 * @return Jam_Query_Builder_Collection        
	 */
	public static function factory($model)
	{
		return new Jam_Query_Builder_Collection($model);
	}
	
	/**
	 * The result of this colleciton
	 * @var Database_Result
	 */
	protected $_result;

	/**
	 * The model of this collection
	 * @var Jam_Model
	 */
	protected $_model_template;

	/**
	 * Result Setter / Getter
	 * @param  Database_Result $result
	 * @return Database_Result|Jam_Query_Builder_Collection
	 */
	public function result(Database_Result $result = NULL)
	{
		if ($result !== NULL)
		{
			$this->_result = $result;
		}

		if ( ! $this->_result)
		{
			$this->_result = $this->execute();
		}

		return $this->_result;
	}

	/**
	 * Load the info for the collection result, as if was loaded from the database 
	 * @param  array  $fields 
	 * @return Jam_Query_Builder_Collection         
	 */
	public function load_fields(array $fields)
	{
		$this->_result = new Database_Result_Cached($fields, '', FALSE);
		return $this;
	}

	/**
	 * Get a jam model template to use for _Load_model
	 * @return Jam_Model 
	 */
	public function model_template()
	{
		if ( ! $this->_model_template)
		{
			$this->_model_template = Jam::build($this->meta()->model());
		}
		return $this->_model_template;
	}

	/**
	 * Use the model_template() to return the model for the row in the results
	 * @param  array $value 
	 * @return Jam_Model        
	 */
	protected function _load_model($value)
	{
		if ( ! $value)
			return NULL;

		$model = clone $this->model_template();
		$model = $model->load_fields($value);

		return $model;
	}

	/**
	 * Return all of the models in the result as an array.
	 *
	 *     // Indexed array of all models
	 *     $rows = $result->as_array();
	 *
	 *     // Associative array of models by "id"
	 *     $rows = $result->as_array('id');
	 *
	 *     // Associative array of fields, "id" => "name"
	 *     $rows = $result->as_array('id', 'name');
	 *
	 * @param   string  column for associative keys
	 * @param   string  column for values
	 * @return  array
	 */
	public function as_array($key = NULL, $value = NULL)
	{
		$key = Jam_Query_Builder::resolve_meta_attribute($key, $this->meta());
		if ($value === NULL)
		{
			return array_map(array($this, '_load_model'), $this->result()->as_array($key));
		}
		else
		{
			$value = Jam_Query_Builder::resolve_meta_attribute($value, $this->meta());
			return $this->result()->as_array($key, $value);
		}
	}

	/**
	 * Get the ids of the models in an array
	 * @return array 
	 */
	public function ids()
	{
		return $this->as_array(NULL, ':primary_key');
	}

	/**
	 * Return the first model
	 * @return Jam_Model 
	 */
	public function first()
	{
		return $this->_load_model($this->limit(1)->result()->rewind()->current());
	}

	/**
	 * Return the first model, throw Jam_Exception_NotFound if there was no result
	 * @return Jam_Model
	 * @throws Jam_Exception_NotFound
	 */
	public function first_insist()
	{
		$result = $this->first();
		if ( ! $result)
			throw new Jam_Exception_NotFound(":model not found", $this->meta()->model());

		return $result;
	}

	/**
	 * Implement Countable
	 * @return int 
	 */
	public function count()
	{
		return $this->result()->count();
	}

	/**
	 * Implement ArrayAccess
	 * 
	 * @param  int $offset 
	 * @return Jam_Model         
	 */
	public function offsetGet($offset)
	{
		$value = $this->result()->offsetGet($offset);
		if ( ! $value)
			return NULL;

		return $this->_load_model($value);
	}

	/**
	 * Implement ArrayAccess
	 * 
	 * @param  int $offset 
	 * @return boolean         
	 */
	public function offsetExists($offset)
	{
		return $this->result()->offsetExists($offset);
	}

	/**
	 * Implement ArrayAccess
	 */
	public function offsetSet($offset, $value)
	{
		throw new Kohana_Exception('Database results are read-only');
	}

	/**
	 * Implement ArrayAccess
	 */
	public function offsetUnset($offset)
	{
		throw new Kohana_Exception('Database results are read-only');
	}

	/**
	 * Implement Iterator
	 */
	public function rewind()
	{
		$this->result()->rewind();
	}

	/**
	 * Implement Iterator
	 * @return  Jam_Model 
	 */
	public function current()
	{
		$value = $this->result()->current();
		if ( ! $value)
			return NULL;

		return $this->_load_model($value);
	}

	/**
	 * Implement Iterator
	 * @return  int
	 */
	public function key()
	{
		return $this->result()->key();
	}

	/**
	 * Implement Iterator
	 */
	public function next()
	{
		$this->result()->next();
	}

	/**
	 * Implement Iterator
	 * @return  bool
	 */
	public function valid()
	{
		return $this->result()->valid();
	}

	public function serialize()
	{
		return serialize(array('fields' => $this->result()->as_array(), 'meta' => $this->meta()->model()));
	}

	public function unserialize($data)
	{
		$data = unserialize($data);
		$this->_meta = Jam::meta($data['meta']);
		$this->load_fields($data['fields']);
	}
}