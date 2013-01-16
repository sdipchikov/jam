<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Jam Core
 *
 * This core class is the main interface to all
 * models, builders, and meta data.
 *
 * @package    Jam
 * @category   Base
 * @author     Ivan Kerin
 * @copyright  (c) 2011-2012 Despark Ltd.
 * @author     Jonathan Geiger
 * @copyright  (c) 2010-2011 Jonathan Geiger
 * @license    http://www.opensource.org/licenses/isc-license.txt
 */
abstract class Kohana_Jam {

	/**
	 * @var  string  The prefix to use for all model's class names
	 *               This can be overridden to allow you to place
	 *               models and builders in a different location.
	 */
	protected static $_model_prefix = 'Model_';

	/**
	 * @var  string  This prefix to use for all model's field classes
	 *               This can be overridden to allow you to place
	 *               field classes in a different location.
	 */
	protected static $_field_prefix = 'Jam_Field_';

	/**
	 * @var  string  This prefix to use for all behavior classes
	 *               This can be overridden to allow you to place
	 *               behavior classes in a different location.
	 */
	protected static $_behavior_prefix = 'Jam_Behavior_';

	/**
	 * @var  string  This prefix to use for all model's association classes
	 *               This can be overridden to allow you to place
	 *               association classes in a different location.
	 */
	protected static $_association_prefix = 'Jam_Association_';	

	/**
	 * @var  string  This prefix to use for all model's form classes
	 *               This can be overridden to allow you to place
	 *               form classes in a different location.
	 */
	protected static $_form_prefix = 'Jam_Form_';	

	/**
	 * @var  string  This prefix to use for all attribute's validator rule classes
	 *               This can be overridden to allow you to place
	 *               form classes in a different location.
	 */
	protected static $_validator_rule_prefix = 'Jam_Validator_Rule_';	

	/**
	 * @var  array  Contains all of the meta classes related to models
	 */
	public static $_models = array();

	public static function build($model, $attributes = array())
	{
		$class = Jam::class_name($model);

		$object = new $class();

		return $object->set($attributes);
	}

	public static function create($model, $attributes = array())
	{
		return Jam::build($model, $attributes)->save();
	}

	/**
	 * Gets a particular set of metadata about a model. If the model
	 * isn't registered, it will attempt to register it.
	 *
	 * FALSE is returned on failure.
	 *
	 * @param   string|Jam_Model  $model
	 * @return  Jam_Meta
	 */
	public static function meta($model)
	{
		$model = Jam::model_name($model);

		if ( ! isset(Jam::$_models[$model]))
		{
			if ( ! Jam::register($model))
			{
				return FALSE;
			}
		}

		return Jam::$_models[$model];
	}

	/**
	 * Factory for instantiating fields.
	 *
	 * @param   string  $type
	 * @param   mixed   $options
	 * @return  Jam_Field
	 */
	public static function field($type, $options = NULL)
	{
		$field = Jam::$_field_prefix.$type;

		return new $field($options);
	}

	/**
	 * Factory for instantiating associations.
	 *
	 * @param   string  $type
	 * @param   mixed   $options
	 * @return  Jam_Association
	 */
	public static function association($type, $options = NULL)
	{
		$association = Jam::$_association_prefix.$type;

		return new $association($options);
	}


	/**
	 * Factoring for instantiating behaviors.
	 *
	 * @param   string  $type
	 * @param   mixed   $options
	 * @return  Jam_Behavior
	 */
	public static function behavior($type, $options = array())
	{
		$behavior = Jam::$_behavior_prefix.$type;

		return new $behavior($options);
	}

	/**
	 * Factoring for instantiating behaviors.
	 *
	 * @param   string  $type
	 * @param   mixed   $options
	 * @return  Jam_Validator_Rule
	 */
	public static function validator_rule($type, $options = array())
	{
		$rule = Jam::$_validator_rule_prefix.$type;

		return new $rule($options);
	}



	/**
	 * Automatically loads a model, if it exists,
	 * into the meta table.
	 *
	 * Models are not required to register
	 * themselves; it happens automatically.
	 *
	 * @param   string  $model
	 * @return  boolean
	 */
	public static function register($model)
	{
		$class = Jam::class_name($model);
		$model = Jam::model_name($model);

		// Don't re-initialize!
		if (isset(Jam::$_models[$model]))
		{
			return TRUE;
		}

		 // Can we find the class?
		if (class_exists($class))
		{
			// Prevent accidentally trying to load ORM or Sprig models
			if ( ! is_subclass_of($class, "Jam_Validated"))
			{
				return FALSE;
			}
		}
		else
		{
			return FALSE;
		}

		// Load it into the registry
		Jam::$_models[$model] = $meta = new Jam_Meta($model);

		// Let the intialize() method override defaults.
		call_user_func(array($class, 'initialize'), $meta);

		// Finalize the changes
		$meta->finalize($model);

		return TRUE;
	}

	/**
	 * Returns the class name of a model
	 *
	 * @param   string|Jam_Validated  $model
	 * @return  string
	 */
	public static function class_name($model)
	{
		if ($model instanceof Jam_Validated)
		{
			return strtolower(get_class($model));
		}
		else
		{
			return strtolower(Jam::$_model_prefix.$model);
		}
	}

	/**
	 * Returns the model name of a class
	 *
	 * @param   string|Jam_Validated  $model
	 * @return  string
	 */
	public static function model_name($model)
	{
		if ($model instanceof Jam_Validated)
		{
			$model = get_class($model);
		}

		$prefix_length = strlen(Jam::$_model_prefix);

		// Compare the first parts of the names and chomp if they're the same
		if (strtolower(substr($model, 0, $prefix_length)) === strtolower(Jam::$_model_prefix))
		{
			$model = substr($model, $prefix_length);
		}

		return strtolower($model);
	}

	/**
	 * Returns the prefix to use for all models and builders.
	 *
	 * @return  string
	 */
	public static function model_prefix()
	{
		return Jam::$_model_prefix;
	}

	/**
	 * Returns the prefix to use for all fields.
	 *
	 * @return  string
	 */
	public static function field_prefix()
	{
		return Jam::$_field_prefix;
	}

	/**
	 * Returns the prefix to use for forms.
	 *
	 * @return  string
	 */
	public static function form_prefix()
	{
		return Jam::$_form_prefix;
	}


	/**
	 * Returns the prefix to use for all behaviors.
	 *
	 * @return  string
	 */
	public static function behavior_prefix()
	{
		return Jam::$_behavior_prefix;
	}

	public static function clear_cache($name = NULL)
	{
		if ($name !== NULL)
		{
			unset(Jam::$_models[$name]);
		}
		else
		{
			Jam::$_models = array();
		}
	}

	public static function delete($model)
	{
		return new Jam_Query_Builder_Delete($model);
	}

	public static function update($model)
	{
		return new Jam_Query_Builder_Update($model);
	}

	public static function insert($model)
	{
		return new Jam_Query_Builder_Insert($model);
	}

	public static function select($model)
	{
		return new Jam_Query_Builder_Select($model);
	}

	public static function all($model)
	{
		return new Jam_Query_Builder_Collection($model);
	}

	protected static function find_or($method, $model, array $values)
	{
		$collection = new Jam_Query_Builder_Collection($model);
		$converted_keys = array();
		foreach ($values as $key => $value) 
		{
			$key = Jam_Query_Builder::resolve_meta_attribute($key, Jam::meta($model), $value);

			$collection->where($key, '=', $value);
			$converted_keys[$key] = $value;
		}

		if ($item = $collection->first())
			return $item;

		return call_user_func($method, $model, $converted_keys);
	}

	public static function find_or_build($model, array $values)
	{
		return Jam::find_or('Jam::build', $model, $values);
	}

	public static function find_or_create($model, array $values)
	{
		return Jam::find_or('Jam::create', $model, $values);
	}

	public static function find($model, $key)
	{
		$collection = new Jam_Query_Builder_Collection($model);
		$collection->where_key($key);
		return is_array($key) ? $collection : $collection->first();
	}

	public static function find_insist($model, $key = NULL)
	{
		$result = Jam::find($model, $key);

		if (is_array($key))
		{
			$missing = array_diff(array_values($key), array_values($result->ids()));
		}
		else
		{
			$missing = $result ? array() : array($key);
		}
		
		if ($missing)
			throw new Jam_Exception_NotFound(":model (:missing) not found", $model, array(':missing' => join(', ', $missing)));
	
		return $result;
	}

	public static function permit(array $permit = array(), array $data = array())
	{
		return Jam_Validator_Attributes::factory($permit)->data($data)->clean();
	}

	public static function form($model, $class = NULL)
	{
		if ($class === NULL)
		{
			$class = Kohana::$config->load('jam.default_form');
		}
		
		if (is_string($model))
		{
			$model = Jam::factory($model);
		}

		if (class_exists(Jam::$_form_prefix.$class))
		{
			$class = Jam::$_form_prefix.$class;
		}

		return new $class($model);
	}

} // End Kohana_Jam