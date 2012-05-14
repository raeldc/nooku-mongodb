<?php

abstract class ComMongoDatabaseDocumentAbstract extends KObject
{
    protected $_database;
    protected $_name;

    /**
     * Name of the identity column in the document
     *
     * @var string
     */
    protected $_identity_column;

    /**
     * Array of column mappings by column name
     *
     * @var array
     */
    protected $_column_map = array();

    public function __construct(KConfig $config)
    {
        parent::__construct($config);

        $this->_database = $config->database;
        $this->_name = $config->name;

        // Set the identity column
        $this->_identity_column = $config->identity_column;

        //Set the default column mappings
        $this->_column_map = $config->column_map ? $config->column_map->toArray() : array();
        if(!isset( $this->_column_map['id']) && isset($this->_identity_column)) {
            $this->_column_map['id'] = $this->_identity_column;
        }

        // TODO: Set the column filters
        if(!empty($config->filters) && false)
        {
            foreach($config->filters as $column => $filter) {
                $this->getColumn($column, true)->filter = KConfig::toData($filter);
            }
        }

        // Mixin a command chain
        $this->mixin(new KMixinCommand($config->append(array('mixer' => $this))));

        // Mixin the behavior interface
        $this->mixin(new KMixinBehavior($config));
    }

    protected function _initialize(KConfig $config)
    {
        // TODO: Set the database to be a singleton, use com:application.database
        $database = $this->getService('com://site/mongo.database.adapter.document');
        $package = $this->getIdentifier()->package;
        $name    = $this->getIdentifier()->name;

        $config->append(array(
            'command_chain'     => $this->getService('koowa:command.chain'),
            'event_dispatcher'  => $this->getService('koowa:event.dispatcher'),
            'dispatch_events'   => false,
            'enable_callbacks'  => false,

            'database'          => $database,
            'behaviors'         => array(),
            'filters'           => array(),
            'name'              => empty($package) ? $name : $package.'_'.$name,
        ));

        parent::_initialize($config);
    }

    public function find($query = null, $mode = KDatabase::FETCH_ROWSET)
    {
        //Create commandchain context
        $context            = $this->getCommandContext();
        $context->operation = KDatabase::OPERATION_SELECT;
        $context->query     = $query;
        $context->mode      = $mode;

        if($this->getCommandChain()->run('before.find', $context) !== false)
        {
            if ($context->query)
            {
                $context->query->from($this->_name);
                $data = $this->_database->find($context->query, $context->mode);

                //Map the columns
                if (($context->mode != KDatabase::FETCH_FIELD) && ($context->mode != KDatabase::FETCH_FIELD_LIST))
                {
                    if($context->mode % 2)
                    {
                        foreach($data as $key => $value) {
                            $data[$key] = $this->mapColumns($value, true);
                        }
                    }
                    else $data = $this->mapColumns(KConfig::unbox($data), true);
                }
            }

            switch($context->mode)
            {
                case KDatabase::FETCH_ROW    :
                {
                    $context->data = $this->getRow();
                    if(isset($data) && !empty($data)) {
                       $context->data->setData($data, false)->setStatus(KDatabase::STATUS_LOADED);
                    }

                    break;
                }

                case KDatabase::FETCH_ROWSET :
                {
                    $context->data = $this->getRowset();

                    if(isset($data) && !empty($data)) {
                        $context->data->addData($data, false);
                    }
                    break;
                }

                default : $context->data = $data;
            }

            $this->getCommandChain()->run('after.find', $context);
        }

        return $context->data;
    }

    /**
     * Table insert method
     *
     * @param  object       A KDatabaseRow object
     * @return bool|integer Returns the number of rows inserted, or FALSE if insert query was not executed.
     */
    public function insert( KDatabaseRowInterface $row )
    {
        //Create commandchain context
        $context            = $this->getCommandContext();
        $context->operation = KDatabase::OPERATION_INSERT;
        $context->data      = $row;
        $context->query     = null;
        $context->name      = $this->_name;

        if($this->getCommandChain()->run('before.insert', $context) !== false)
        {
            // @TODO: Prepare data, running validation, filters, mappings, etc.
            //$context->data->prepare();
            $data = $this->mapColumns($context->data->getData());

            //Execute the insert query
            $data = $this->_database->insert($context->name, $context->data->toArray());

            $context->data->setData($this->mapColumns($data, true), false)->setStatus(KDatabase::STATUS_CREATED);

            $this->getCommandChain()->run('after.insert', $context);
        }

        return $context->data;
    }

    /**
     * Table update method
     *
     * @param  object           A KDatabaseRow object
     * @return boolean|integer  Returns the number of rows updated, or FALSE if insert query was not executed.
     */
    public function update( KDatabaseRowInterface $row)
    {
        //Create commandchain context
        $context            = $this->getCommandContext();
        $context->operation = KDatabase::OPERATION_UPDATE;
        $context->data      = $row;
        $context->name      = $this->_name;
        $context->affected  = false;

        if($this->getCommandChain()->run('before.update', $context) !== false)
        {
            // TODO: Prepare data, running validation, filters, mappings, etc.
            //$context->data->prepare();

            $query = $this->getQuery();

            if (!$row->isNew())
            {
                // TODO: map $row->id to $row->_id. Query with all the unique keys of row.
                $query->where($this->getIdentityColumn(), '=', $context->data->id);

                // Convert object to array first
                $data = $context->data->toArray();

                //Execute the update query
                $context->affected = $this->_database->update($context->name, $query, $data);

                if(((integer) $context->affected) > 0)
                {
                    //Reverse apply the column mappings and set the data in the row
                    $context->data->setData($data, false)
                                  ->setStatus(KDatabase::STATUS_UPDATED);
                }
                else $context->data->setStatus(KDatabase::STATUS_FAILED);

                //Set the query in the context
                $context->query = $query;
            }
            else $context->data->setStatus(KDatabase::STATUS_FAILED);

            $this->getCommandChain()->run('after.update', $context);
        }

        return $context->affected;
    }

    /**
     * Table delete method
     *
     * @param  object       A KDatabaseRow object
     * @return bool|integer Returns the number of rows deleted, or FALSE if delete query was not executed.
     */
    public function delete( KDatabaseRowInterface $row)
    {
        //Create commandchain context
        $context            = $this->getCommandContext();
        $context->operation = KDatabase::OPERATION_DELETE;
        $context->data      = $row;
        $context->name      = $this->_name;
        $context->affected  = false;

        if($this->getCommandChain()->run('before.delete', $context) !== false)
        {
            // TODO: Prepare data, running validation, filters, mappings, etc.
            //$context->data->prepare();

            $query = $this->getQuery();

            if (!$row->isNew())
            {
                // TODO: map $row->id to $row->_id. Query with all the unique keys of row.
                $query->where('id', '=', $context->data->id);

                // Convert object to array first
                $data = $context->data->toArray();

                //Execute the update query
                $context->affected = $this->_database->delete($context->name, $query, $data);

                if(((integer) $context->affected) > 0)
                {
                    //Reverse apply the column mappings and set the data in the row
                    $context->data->setData($data, false)
                                  ->setStatus(KDatabase::STATUS_DELETED);
                }
                else $context->data->setStatus(KDatabase::STATUS_FAILED);

                //Set the query in the context
                $context->query = $query;
            }
            else $context->data->setStatus(KDatabase::STATUS_FAILED);

            $this->getCommandChain()->run('after.delete', $context);
        }

        return $context->affected;
    }

    /**
     * Count Results of the Query
     *
     * @param   mixed   KDatabaseQuery object or query string or null to count all rows
     * @return  int     Number of rows
     */
    public function count($query = null)
    {
        return $this->_database->count($query->from($this->_name));
    }

    /**
     * Gets the table schema name without the table prefix
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Get an instance of a row object for this table
     *
     * @param   array An optional associative array of configuration settings.
     * @return  KDatabaseRowInterface
     */
    public function getRow(array $options = array())
    {
        $identifier         = clone $this->getIdentifier();
        $identifier->path   = array('database', 'row');
        $identifier->name   = KInflector::singularize($identifier->name);

        //The row default options
        $options['document'] = $this;
        $options['identity_column'] = $this->mapColumns($this->getIdentityColumn(), true);

        return $this->getService($identifier, $options);
    }

    /**
     * Get an instance of a rowset object for this table
     *
     * @param   array An optional associative array of configuration settings.
     * @return  KDatabaseRowInterface
     */
    public function getRowset(array $options = array())
    {
        $identifier         = clone $this->getIdentifier();
        $identifier->path   = array('database', 'rowset');

        //The rowset default options
        $options['document'] = $this;
        $options['identity_column'] = $this->mapColumns($this->getIdentityColumn(), true);

        return $this->getService($identifier, $options);
    }

    /**
     * Gets the identitiy column of the document.
     *
     * @return string
     */
    public function getIdentityColumn()
    {
        $result = null;
        if(isset($this->_identity_column)) {
            $result = $this->_identity_column;
        }

        return $result;
    }

    /**
     * Table map method
     *
     * This functions maps the column names to those in the table schema
     *
     * @param  array|string An associative array of data to be mapped, or a column name
     * @param  boolean      If TRUE, perform a reverse mapping
     * @return array|string The mapped data or column name
     */
    public function mapColumns($data, $reverse = false)
    {
        $map = $reverse ? array_flip($this->_column_map) : $this->_column_map;

        $result = null;
        if(is_array($data))
        {
            $result = array();
            foreach($data as $column => $value)
            {
                if(isset($map[$column])) {
                    $column = $map[$column];
                }

                $result[$column] = $value;
            }
        }

        if(is_string($data))
        {
            $result = $data;
            if(isset($map[$data])) {
                $result = $map[$data];
            }
        }

        return $result;
    }

    public function getQuery($query = null)
    {
        static $instance;

        if ($query instanceof ComMongoDatabaseQueryDocument) {
            return $query;
        }

        if (is_null($instance))
        {
            $instance = new ComMongoDatabaseQueryDocument();
        }

        if (is_array($query))
        {
            foreach ($query as $key => $value) {
                $instance->where($key, '=', $value);
            }
        }

        return $instance;
    }

    public function getSchema()
    {
        static $schema;

        if (is_null($schema)) {
            $schema = array();
        }

        $identifier = (string) $this->getIdentifier();
        if (!isset($schema[$identifier])) {
            $schema[$identifier] = new SDatabaseSchemaDocument();
        }

        return $schema[$identifier];
    }

    /**
     * Gets the behaviors of the table
     *
     * @return array    An asscociate array of table behaviors, keys are the behavior names
     */
    public function getBehaviors()
    {
        return $this->getSchema()->behaviors;
    }

    public function getDatabase()
    {
        return $this->_database;
    }

    /**
     * Search the behaviors to see if this table behaves as.
     *
     * Function is also capable of checking is a behavior has been mixed succesfully
     * using is[Behavior] function. If the behavior exists the function will return
     * TRUE, otherwise FALSE.
     *
     * @param  string   The function name
     * @param  array    The function arguments
     * @throws BadMethodCallException   If method could not be found
     * @return mixed The result of the function
     */
    public function __call($method, array $arguments)
    {
        // If the method is of the form is[Bahavior] handle it.
        $parts = KInflector::explode($method);

        if($parts[0] == 'is' && isset($parts[1]))
        {
            if($this->hasBehavior(strtolower($parts[1]))) {
                 return true;
            }

            return false;
        }

        return parent::__call($method, $arguments);
    }
}