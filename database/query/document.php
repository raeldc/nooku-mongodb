<?php

class ComMongoDatabaseQueryDocument extends KObject
{
    public $from;

    public $where = array();

    public $sort = array();

    public $limit = 0;

    public $offset = 0;

    public $query = null;

    public function where( $property, $constraint = null, $value = null, $condition = 'AND' )
    {
        if(!empty($property)) 
        {
            $where = array();
            $where['property'] = $property;

            if(isset($constraint))
            {
                $constraint = strtoupper($constraint);
                $condition  = strtoupper($condition);
            
                $where['constraint'] = $constraint;
                $where['value']      = $value;
            }
        
            $where['condition']  = count($this->where) ? $condition : '';

            //Make sure we don't store the same where clauses twice
            $signature = md5($property.$constraint.$value);
            if(!isset($this->where[$signature])) {
                $this->where[$signature] = $where;
            }
        }

        return $this;
    }

    public function limit( $limit, $offset = 0 )
    {
        $this->limit  = (int) $limit;
        $this->offset = (int) $offset;
        
        return $this;
    }

    public function sort( $columns, $direction = 'asc' )
    {
        settype($columns, 'array'); //force to an array
        $direction = strtolower($direction);
        foreach($columns as $column)
        {
            $this->sort[$column] = ($direction == 'desc') ? -1 : 1;
        }

        return $this;
    }

    public function from($from)
    {
        $this->from = $from;

        return $this;
    }

    public function build($query = null)
    {
        if (!is_null($query))
            return $this->query = $query;

        $this->query = array();

        // TODO: Try to account for OR not just AND
        foreach ($this->where as $where) 
        {

            switch($where['constraint']){
                case '=':
                    if ($where['property'] == 'id') {
                        $this->query['_id'] = new MongoId($where['value']);
                    }
                    else $this->query[$where['property']] = $where['value'];
                break;

                default:
                    $constraint = array(
                        'in' => '$in',
                        '<' => '$lt',
                        '<=' => '$lte',
                        '>' => '$gt',
                        '>=' => '$gte',
                        '<>' => '$ne',
                        '!=' => '$ne',
                    );

                    $value = (strtolower($where['constraint']) == 'in' && is_string($value)) ? array($value) : $where['value'];

                    $this->query[$where['property']] = array(strtolower($constraint[$where['constraint']]) => $value);
                break;
            }
        }

        return empty($this->query) ? new stdclass : (object)$this->query;
    }
}