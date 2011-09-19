<?php

abstract class ComMongoDatabaseDocumentAbstract extends KObject implements KObjectIdentifiable
{
	protected $_database;
	protected $_name;

	public function __construct(KConfig $config)
	{
		parent::__construct($config);

		$this->_database = $config->database;
		$this->_name = $config->name;
           
        // TODO: Set the column filters
        if(!empty($config->filters) && false) 
        {
            foreach($config->filters as $column => $filter) {
                $this->getColumn($column, true)->filter = KConfig::toData($filter);
            }       
        }
    
        // Mixin a command chain
        $this->mixin(new KMixinCommandchain($config->append(array('mixer' => $this))));
           
        // Set the table behaviors
        if(!empty($config->behaviors)) {
            $this->addBehavior($config->behaviors);
        } 
	}

	protected function _initialize(KConfig $config)
	{
		// TODO: Set the database to be a singleton, use com:application.database
		$database = KFactory::get('com://admin/mongo.database.adapter.document');
		$package = $this->_identifier->package;
        $name    = $this->_identifier->name;

		$config->append(array(
			'command_chain'     => new KCommandChain(),
            'dispatch_events'   => false,
            'enable_callbacks'  => false,

			'database' 			=> $database,
			'behaviors' 		=> array(),
			'filters'	 		=> array(),
			'name'		        => empty($package) ? $name : $package.'_'.$name,
		));
	
		parent::_initialize($config);
	}

	public function getIdentifier()
	{
		return $this->_identifier;
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
                $context->query->from($this->_name);
            
            switch($context->mode)
            {
                case KDatabase::FETCH_ROW    : 
                {
                	$data = ($context->query) ? $this->_database->find($context->query, KDatabase::FETCH_ROW) : array();
                    $context->data = $this->getRow();

                    if(isset($data) && !empty($data)) {
                       $context->data->setData($data, false)->setStatus(KDatabase::STATUS_LOADED);
                    }

                    break;
                }
                
                case KDatabase::FETCH_ROWSET : 
                {
                	$data = ($context->query) ? $this->_database->find($context->query, KDatabase::FETCH_ROWSET) : array();

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

        return KConfig::toData($context->data);
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
            // TODO: Prepare data, running validation, filters, mappings, etc.
            //$context->data->prepare();
            
            //Execute the insert query
            $context->data = $this->_database->insert($context->name, $context->data->toArray());

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
                $query->where('id', '=', $context->data->id);

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
     * Get an instance of a row object for this table
     *
     * @param	array An optional associative array of configuration settings.
     * @return  KDatabaseRowInterface
     */
    public function getRow(array $options = array())
    {
        $identifier         = clone $this->_identifier;
        $identifier->path   = array('database', 'row');
        $identifier->name   = KInflector::singularize($this->_identifier->name);
            
        //The row default options
        $options['document'] = $this; 
             
        return KFactory::get($identifier, $options); 
    }

    /**
     * Get an instance of a rowset object for this table
     *
     * @param	array An optional associative array of configuration settings.
     * @return  KDatabaseRowInterface
     */
    public function getRowset(array $options = array())
    {
        $identifier         = clone $this->_identifier;
        $identifier->path   = array('database', 'rowset');
            
        //The rowset default options
        $options['document'] = $this;

        return KFactory::get($identifier, $options);
    }

	public function getQuery()
	{
		static $instance;

		if (is_null($instance)) {
			$instance = new ComMongoDatabaseQueryDocument();
		}

		return $instance;
	}

	/**
     * Register one or more behaviors to the table
     *
     * @param   array   Array of one or more behaviors to add.
     * @return  KDatabaseTableAbstract
     */
    public function addBehavior($behaviors)
    {
        $behaviors = (array) KConfig::toData($behaviors);
                
        foreach($behaviors as $behavior)
        {
            if (!($behavior instanceof KDatabaseBehaviorInterface)) { 
                $behavior   = $this->getBehavior($behavior); 
            } 
              
		    // TODO: Add the behavior
            // $this->getSchema()->behaviors[$behavior->getIdentifier()->name] = $behavior;
            $this->getCommandChain()->enqueue($behavior);
        }
        
        return $this;
    }

    /**
     * Get a behavior by identifier
     *
     * @param  
     * @return KDatabaseBehaviorAbstract
     */
    public function getBehavior($behavior, $config = array())
    {
       if(!($behavior instanceof KIdentifier))
       {
            //Create the complete identifier if a partial identifier was passed
           if(is_string($behavior) && strpos($behavior, '.') === false )
           {
               $identifier = clone $this->_identifier;
               $identifier->path = array('database', 'behavior');
               $identifier->name = $behavior;
           }
           else $identifier = KIdentifier::identify($behavior);
       }
       
       if(!isset($this->getSchema()->behaviors[$identifier->name])) {
           $behavior = KDatabaseBehavior::factory($identifier, array_merge($config, array('mixer' => $this)));
       } else {
           $behavior = $this->getSchema()->behaviors[$identifier->name];
       }
       
       return $behavior;
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