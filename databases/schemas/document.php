<?php

class ComMongoDatabaseSchemaDocument extends KObject
{
	/**
	 * Collection name
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Table length
	 *
	 * @var integer
	 */
	public $length;

	/**
	 * The tables description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * List of fields
	 *
	 * Associative array of fields, where key holds the fields name and the value is
	 * an SDatabaseSchemaField object.
	 *
	 * @var	array
	 */
	public $fields = array();

	/**
	 * List of behaviors
	 *
	 * Associative array of behaviors, where key holds the behavior identifier string
	 * and the value is an KDatabaseBehavior object.
	 *
	 * @var	array
	 */
	public $behaviors = array();

	/**
	 * List of indexes
	 *
	 * Associative array of indexes, where key holds the index name and the
	 * and the value is an object.
	 *
	 * @var	array
	 */
	public $indexes = array();
}