<?php
abstract class ComMongoModelDocument extends KModelAbstract
{
    /**
     * Document object or identifier (type://app/COMPONENT.database.document.DOCUMENTNAME)
     *
     * @var string|object
     */
    protected $_document = false;

    /**
     * Constructor
     *
     * @param   object  An optional KConfig object with configuration options
     */
    public function __construct(KConfig $config)
    {
        parent::__construct($config);

        $this->_document = $config->document;

        // Set the static states
        $this->_state
            ->insert('limit'    , 'int', 10)
            ->insert('offset'   , 'int')
            ->insert('sort'     , 'cmd')
            ->insert('direction', 'word', 'asc')
            ->insert('search'   , 'string')
            // callback state for JSONP, needs to be filtered as cmd to prevent XSS
            ->insert('callback' , 'cmd')
            // TODO: Automatically populate the unique states from the row definition.
            ->insert('id', 'string', null, true);
    }

    /**
     * Initializes the config for the object
     *
     * Called from {@link __construct()} as a first step of object instantiation.
     *
     * @param   object  An optional KConfig object with configuration options
     * @return  void
     */
    protected function _initialize(KConfig $config)
    {
        $config->append(array(
            'document' => $this->getIdentifier()->name,
        ));

        parent::_initialize($config);
    }

    /**
     * Set the model state properties
     *
     * This function overloads the KDatabaseDocumentAbstract::set() function and only acts on state properties.
     *
     * @param   string|array|object The name of the property, an associative array or an object
     * @param   mixed               The value of the property
     * @return  KModelDocument
     */
    public function set( $property, $value = null )
    {
        parent::set($property, $value);

        // If limit has been changed, adjust offset accordingly
        if($limit = $this->_state->limit) {
             $this->_state->offset = $limit != 0 ? (floor($this->_state->offset / $limit) * $limit) : 0;
        }

        return $this;
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
     * Test the connected status of the row.
     *
     * @return  boolean Returns TRUE if we have a reference to a live KDatabaseDocumentAbstract object.
     */
    public function isConnected()
    {
        return (bool) $this->getDocument();
    }

    /**
     * Method to get a item object which represents a document row
     *
     * If the model state is unique a row is fetched from the database based on the state.
     * If not, an empty row is be returned instead.
     *
     * @return KDatabaseRow
     */
    public function getItem()
    {
        if (!isset($this->_item))
        {
            if($this->isConnected())
            {
                $query = null;

                if($this->_state->isUnique())
                {
                    $query = $this->getDocument()->getQuery();
                    $this->_buildQueryWhere($query);
                }

                $this->_item = $this->getDocument()->find($query, KDatabase::FETCH_ROW);
            }
        }

        return $this->_item;
    }

    /**
     * Get a list of items which represnts a  document rowset
     *
     * @return KDatabaseRowset
     */
    public function getList()
    {
        // Get the data if it doesn't already exist
        if (!isset($this->_list))
        {
            if($this->isConnected())
            {
                $query  = null;

                if(!$this->_state->isEmpty())
                {
                    $query = $this->getDocument()->getQuery();

                    $this->_buildQueryWhere($query);
                    $this->_buildQueryLimit($query);
                    $this->_buildQueryOrder($query);
                }

                $this->_list = $this->getDocument()->find($query, KDatabase::FETCH_ROWSET);
            }
        }
        return $this->_list;
    }

    /**
     * Get the total amount of items
     *
     * @return  int
     */
    public function getTotal()
    {
        // Get the data if it doesn't already exist
        if (!isset($this->_total))
        {
            if($this->isConnected())
            {
                //Excplicitly get a count query, build functions can then test if the
                //query is a count query and decided how to build the query.
                $query = $this->getDocument()->getQuery();

                $this->_buildQueryWhere($query);

                $total = $this->getDocument()->count($query);
                $this->_total = $total;
            }
        }

        return $this->_total;
    }

    /**
     * Builds a WHERE clause for the query
     */
    protected function _buildQueryWhere(ComMongoDatabaseQueryDocument $query)
    {
        //Get only the unique states
        $states = $this->_state->getData(true);

        if(!empty($states))
        {
            $states = $this->getDocument()->mapColumns($states);
            foreach($states as $key => $value)
            {
                if(is_array($value)) {
                    $query->where($key, 'IN', $value);
                }
                else $query->where($key, '=', $value);
            }
        }
    }

    /**
     * Builds a Limit clause for the query
     */
    protected function _buildQueryLimit(ComMongoDatabaseQueryDocument $query)
    {
        if (!is_null($this->_state->limit)) {
            $query->limit($this->_state->limit, $this->_state->offset);
        }
    }

    /**
     * Builds a Order clause for the query
     */
    protected function _buildQueryOrder(ComMongoDatabaseQueryDocument $query)
    {
        if (!is_null($this->_state->sort)) {
            $query->sort($this->_state->sort, $this->_state->direction);
        }
    }
}