<?php

class ComMongoDatabaseAdapterDocument extends KObject
{
	protected $_connection;
	protected $_database;
	protected $_synced;

	public function __construct(KConfig $config)
	{
		parent::__construct($config);

		if (is_null($config->connection))
		{
			// TODO: Move this to a specific Mongo adapter
			$connect = 'mongodb://';
			$connect .= (!empty($config->options->username) && !empty($config->options->password)) ? $config->options->username.':'.$config->options->password.'@' : '';
			$connect .= (!empty($config->options->host)) ? $config->options->host : '';
			$connect .= (!empty($config->options->port)) ? ':'. $config->options->port : '';
			$connect .= (!empty($config->database)) ? '/'. $config->database : '';

			$this->setConnection(new Mongo($connect));

			$this->_database = $this->getConnection()->selectDB($config->database);
		}
		else $this->setConnection($config->connection);

		// Mixin a command chain
        $this->mixin(new KMixinCommand($config->append(array('mixer' => $this))));

        // More sure that data has been inserted/updated
        $this->_synced = $config->synced;
	}

	protected function _initialize(KConfig $config)
    {
    	$config->append(array(
    		'connection'		=> null,
    		'database'			=> 'mcced',
    		'synced'			=> true,
    		'command_chain'		=> $this->getService('koowa:command.chain'),
    		'event_dispatcher'  => $this->getService('koowa:event.dispatcher'),
			'options'	=> array(
    			'host'		=> 'localhost',
    			'username'	=> '',
    			'password'  => '',
    			'port'		=> null,
    			'socket'	=> null
    		)
        ));

        parent::_initialize($config);
    }

    public function setConnection($resource)
	{
	    if(!($resource instanceof Mongo)) {
	        throw new KDatabaseAdapterException('Not a Mongo connection');
	    }

	    $this->_connection = $resource;
		return $this;
	}

	public function getConnection()
	{
		return $this->_connection;
	}

	public function find(ComMongoDatabaseQueryDocument $query, $mode = KDatabase::FETCH_ROWSET)
	{
		$result = array();

		if(!empty($query->from))
		{
			$collection = $this->_database->selectCollection($query->from);

			switch($mode)
			{
				case KDatabase::FETCH_ROW:
					// TODO: Support selecting specific fields
					$result = $collection->findOne($query->build());
				break;

				default:
					$result = $collection->find($query->build());

					if (!empty($query->sort)) {
						$result = $result->sort($query->sort);
					}

					if ($query->limit) {
						$result = $result->limit($query->limit)->skip($query->offset);
					}

					$result = iterator_to_array($result);
				break;
			}
		}

		$query->reset();

		return $result;
	}

	public function insert($collection, $data = array())
	{
		$this->_database->selectCollection($collection)->insert($data, array('fsync' => $this->_synced));

		return $data;
	}

	public function update($collection, $query, $data = array())
	{
		$q = $query->build();

		$collection = $this->_database->selectCollection($collection);

		unset($data['_id']);unset($data['id']);

		$collection->update($q, (array)$data, array('fsync' => $this->_synced));

		// return affected rows
		$result = $collection->find($q)->count();

		$query->reset();

		return $result;
	}

	public function delete($collection, $query, $data = array())
	{
		$condition = $query->build();

		$collection = $this->_database->selectCollection($collection);

		$affected = $collection->find($condition)->count();

		$collection->remove($condition, array('fsync' => $this->_synced));

		$query->reset();

		// return affected rows
		return $affected;
	}

	public function count($query)
	{
		return $this->_database->selectCollection($query->from)
			->find($query->build())
			->count();
	}

	public function __call($method, $args)
    {
        if(method_exists($this->_database, $method))
        {
            return call_user_func_array(array($this->_database, $method), $args);
        }

        return parent::__call($method, $args);
    }
}