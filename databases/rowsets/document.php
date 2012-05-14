<?php


class ComMongoDatabaseRowsetDocument extends KDatabaseRowsetAbstract
{
    /**
     * Document object or identifier (com://APP/COMPONENT.document.NAME)
     *
     * @var string|object
     */
    protected $_document = false;

    /**
     * Constructor
     *
     * @param   object  An optional KConfig object with configuration options.
     */
    public function __construct(KConfig $config = null)
    {
        parent::__construct($config);

        $this->_document = $config->document;

        // Reset the rowset
        $this->reset();

        // Insert the data, if exists
        if(!empty($config->data)) {
            $this->addData($config->data->toArray(), $config->new);
        }
    }

    /**
     * Initializes the options for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   object  An optional KConfig object with configuration options.
     * @return void
     */
    protected function _initialize(KConfig $config)
    {
        $config->append(array(
            'document' => $this->getIdentifier()->name
        ));

        parent::_initialize($config);
    }

    /**
     * Returns a SDatabaseRow
     *
     * This functions accepts either a know position or associative array of key/value pairs
     *
     * @param   string|array    The position or the key or an associatie array of column data
     *                          to match
     * @return SDatabaseRow(set)Abstract Returns a row or rowset if successfull. Otherwise NULL.
     */
    public function find($needle)
    {
        $result = null;

        if(!is_scalar($needle))
        {
            $result = clone $this;

            foreach ($this as $i => $row)
            {
                foreach($needle as $key => $value)
                {
                    if(!in_array($row->{$key}, (array) $value)) {
                        $result->extract($row);
                    }
                }
            }
        }
        else
        {
            if(isset($this->_object_set[$needle])) {
                $result = $this->_object_set[$needle];
            }
        }

        return $result;
    }

    /**
     * Insert a row into the rowset
     *
     * The row will be stored by it's identity_column if set or otherwise by
     * it's object handle.
     *
     * @param  object   A KDatabaseRow object to be inserted
     * @return boolean  TRUE on success FALSE on failure
     */
    public function insert(KDatabaseRowInterface $row)
    {
        if(isset($this->_identity_column)) {
            $handle = $row->{$this->_identity_column};
        } else {
            $handle = $row->getHandle();
        }

        if($handle) {
            $this->_object_set->offsetSet((string)$handle, $row);
        }

        return true;
    }

    /**
     * Method to get a document object
     *
     * Function catches SDatabaseDocumentExceptions that are thrown for documents that
     * don't exist. If no document object can be created the function will return FALSE.
     *
     * @return SDatabaseDocumentAbstract
     */
     public function getDocument()
    {
        if($this->_document !== false)
        {
            if(!($this->_document instanceof SDatabaseDocumentAbstract))
            {
                //Make sure we have a document identifier
                if(!($this->_document instanceof KServiceIdentifier)) {
                    $this->setDocument($this->_document);
                }

                try {
                    $this->_document = $this->getService($this->_document);
                } catch (SDatabaseDocumentException $e) {
                    $this->_document = false;
                }
            }
        }

        return $this->_document;
    }

    /**
     * Method to set a document object attached to the rowset
     *
     * @param   mixed   An object that implements KObjectServiceable, KServiceIdentifier object
     *                  or valid identifier string
     * @throws  SDatabaseRowsetException    If the identifier is not a document identifier
     * @return  KDatabaseRowsetAbstract
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
                throw new SDatabaseRowsetException('Identifier: '.$identifier.' is not a document identifier');
            }

            $document = $identifier;
        }

        $this->_document = $document;

        return $this;
    }

    /**
     * Test the connected status of the row.
     *
     * @return  boolean Returns TRUE if we have a reference to a live SDatabaseDocumentAbstract object.
     */
    public function isConnected()
    {
        return (bool) $this->getDocument();
    }

    /**
     * Get an empty row
     *
     * @param   array An optional associative array of configuration settings.
     * @return  object  A SDatabaseRow object.
     */
    public function getRow(array $options = array())
    {
        $result = null;

        if($this->isConnected()) {
            $result = $this->getDocument()->getRow($options);
        }

        return $result;
    }

    /**
     * Forward the call to each row
     *
     * This functions overloads KDatabaseRowsetAbstract::__call and implements
     * a just in time mixin strategy. Available document behaviors are only mixed
     * when needed.
     *
     * @param  string   The function name
     * @param  array    The function arguments
     * @throws BadMethodCallException   If method could not be found
     * @return mixed The result of the function
     */
    public function __call($method, array $arguments)
    {
        // If the method hasn't been mixed yet, load all the behaviors.
        if($this->isConnected() && !isset($this->_mixed_methods[$method]))
        {
            foreach($this->getDocument()->getBehaviors() as $behavior) {
                $this->mixin($behavior);
            }
        }

        return parent::__call($method, $arguments);
    }
}