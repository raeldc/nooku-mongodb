<?php

class ComMongoDatabaseRowDocument extends KDatabaseRowAbstract
{
	/**
     * Document object or identifier (type://app/COMPONENT.database.document.DOCUMENTNAME)
     *
     * @var string|object
     */
    protected $_document = false;

	/**
     * Object constructor
     *
     * @param   object  An optional KConfig object with configuration options.
     */
	public function __construct(KConfig $config = null)
	{
		parent::__construct($config);

		$this->_document = $config->document;

		// Reset the row
        $this->reset();

        // Reset the row data
        if(isset($config->data))  {
            $this->setData(KConfig::unbox($config->data), $this->_new);
        }
	}

	/**
	 * Initializes the options for the object
	 *
	 * Called from {@link __construct()} as a first step of object instantiation.
	 *
	 * @param 	object 	An optional KConfig object with configuration options.
	 * @return void
	 */
	protected function _initialize(KConfig $config)
	{
		$config->append(array(
			'document'        => $this->getIdentifier()->name,
		));

		parent::_initialize($config);
	}

	/**
	 * Saves the row to the database.
	 *
	 * This performs an intelligent insert/update and reloads the properties
	 * with fresh data from the table on success.
	 *
	 * @return boolean	If successfull return TRUE, otherwise FALSE
	 */
	public function save()
	{
	    $result = false;

	    if($this->isConnected())
	    {
	        if($this->_new) {
	            $result = $this->getDocument()->insert($this);
		    } else {
		        $result = $this->getDocument()->update($this);
		    }
	    }

		return (bool) $result;
    }

    /**
	 * Deletes the row from the database.
	 *
	 * @return boolean	If successfull return TRUE, otherwise FALSE
	 */
	public function delete()
	{
		$result = false;

		if($this->isConnected())
		{
            if(!$this->_new)
		    {
		        $result = $this->getDocument()->delete($this);

		        if($result !== false)
	            {
	                if(((integer) $result) > 0) {
	                    $this->_new = true;
	                }
                }
		    }
		}

		return (bool) $result;
	}

    /**
	 * Test the connected status of the row.
	 *
	 * @return	boolean	Returns TRUE if we have a reference to a live KDatabaseDocumentAbstract object.
	 */
    public function isConnected()
	{
	    return (bool) $this->getDocument();
	}

    /**
     * Method to get a document object
     *
     * Function catches KDatabaseDocumentExceptions that are thrown for documents that
     * don't exist. If no document object can be created the function will return FALSE.
     *
     * @return KDatabaseDocumentAbstract
     */
    public function getDocument()
    {
        if($this->_document !== false)
        {
            if(!($this->_document instanceof SDatabaseDocumentAbstract))
		    {
		        //Make sure we have a document identifier
		        if(!($this->_document instanceof KIdentifier)) {
		            $this->setDocument($this->_document);
			    }

		        try {
		            $this->_document = $this->getService($this->_document);
                } catch (KDatabaseDocumentException $e) {
                    $this->_document = false;
                }
            }
        }

        return $this->_document;
    }

    public function getTable()
    {
    	return $this->getDocument();
    }

    /**
     * Method to set a document object attached to the model
     *
     * @param   mixed   An object that implements KObject, an object that
     *                  implements KIdentifierInterface or valid identifier string
     * @throws  KDatabaseRowsetException    If the identifier is not a document identifier
     * @return  KModelDocument
     */
    public function setDocument($document)
	{
		if(!($document instanceof SDatabaseDocumentAbstract))
		{
			if(is_string($document) && strpos($document, '.') === false )
		    {
		        $identifier         = clone $this->getIdentifier();
		        $identifier->path   = array('database', 'document');
		        $identifier->name   = KInflector::tableize($document);
		    }
		    else  $identifier = $this->getIdentifier($document);

			if($identifier->path[1] != 'document') {
				throw new KDatabaseRowsetException('Identifier: '.$identifier.' is not a document identifier');
			}

			$document = $identifier;
		}

		$this->_document = $document;

		return $this;
	}

	/**
	 * Load the row from the database using the data in the row
	 *
	 * @return object	If successfull returns the row object, otherwise NULL
	 */
	public function load()
	{
		$result = null;

		if($this->_new)
		{
            if($this->isConnected())
            {
		        $row = $this->getDocument()->find($this->getDocument()->getQuery($this->getData(true)), KDatabase::FETCH_ROW);

		        // Set the data if the row was loaded succesfully.
		        if(!$row->isNew())
		        {
			        $this->setData($row->toArray(), false);
			        $this->_modified = array();
			        $this->_new      = false;

			        $this->setStatus(KDatabase::STATUS_LOADED);
			        $result = $this;
		        }
            }
		}

		return $result;
	}

	/**
	 * Reset the row data using the defaults
	 *
	 * @return boolean	If successfull return TRUE, otherwise FALSE
	 */
	public function reset()
	{
		$result = parent::reset();

		if($this->isConnected())
		{
			// TODO: Get defaults from the row's field definition
			/*
	        if($this->_data = $this->getDefaults()) {
		        $result = true;
		    }
		    */

		    $result = true;
		}

		return $result;
	}

	/**
	 * Count the rows in the database based on the data in the row
	 *
	 * @return integer
	 */
	public function count()
	{
		$result = false;

	    if($this->isConnected())
		{
	        //$data   = $this->filter($this->getData(true), true);
		    $result = $this->getDocument()->count($data);
		}

		return $result;
	}
}