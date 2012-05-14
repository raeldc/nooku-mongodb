<?php

class ComMongoDatabaseSchemaField extends KObject
{

	/**
	 * Field name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Field type
	 *
	 * @var	string
	 */
	public $type;

	/**
	 * Field length
	 *
	 * @var integer
	 */
	public $length;

	/**
	 * Field scope
	 *
	 * @var string
	 */
	public $scope;

	/**
	 * Field default value
	 *
	 * @var string
	 */
	public $default;

	/**
	 * Required Field
	 *
	 * @var bool
	 */
	public $required = false;

	/**
	 * Is the Field a primary key
	 *
	 * @var bool
	 */
	public $primary = false;

	/**
	 * Is the Field unqiue
	 *
	 * @var	bool
	 */
	public $unique = false;

	/**
	 * Related index Fields
	 *
	 * @var	bool
	 */
	public $related = array();

	/**
	 * Filter object
	 *
	 * Public access is allowed via __get() with $filter.
	 *
	 * @var	KFilter
	 */
	protected $_filter;

	/**
     * Implements the virtual $filter property.
     *
     * The value can be a KFilter object, a filter name, an array of filter
     * names or a filter identifier
     *
     * @param 	string 	The virtual property to set, only accepts 'filter'
     * @param 	string 	Set the virtual property to this value.
     */
    public function __set($key, $value)
    {
        if ($key == 'filter') {
        	$this->_filter = $value;
        }
    }

    /**
     * Implements access to $_filter by reference so that it appears to be
     * a public $filter property.
     *
     * @param   string  The virtual property to return, only accepts 'filter'
     * @return  mixed   The value of the virtual property.
     */
    public function __get($key)
    {
        if ($key == 'filter')
        {
           if(!isset($this->_filter)) {
                $this->_filter = $this->type;
            }

            if(!($this->_filter instanceof KFilterInterface)) {
                $this->_filter = KFilter::factory($this->_filter);
            }

            return $this->_filter;
        }
    }
}