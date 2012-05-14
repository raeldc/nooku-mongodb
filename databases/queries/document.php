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
            $value = $where['value'];

            if ($where['property'] == 'id') {
                $where['property'] = '_id';
            }

            if (is_array($value))
            {
                $items = array();
                foreach ($value as $key => $item)
                {
                    if($where['property'] == '_id')
                    {
                        $items[] = new MongoId($item);
                    }
                    else $value = $item;
                }
                $value = $items;
            }
            elseif($where['property'] == '_id')
            {
                $value = new MongoId($value);
            }

            switch($where['constraint']){
                case '=':
                    $this->query[$where['property']] = $value;
                break;

                default:
                    $constraint = array(
                        'in' => '$in',
                        '<'  => '$lt',
                        '<=' => '$lte',
                        '>'  => '$gt',
                        '>=' => '$gte',
                        '<>' => '$ne',
                        '!=' => '$ne',
                        '='  => '$eq',
                    );

                    $where['constraint'] = strtolower($where['constraint']);
                    $value = $where['constraint'] == 'in' && is_string($value) ? array($value) : $value;

                    $this->query[$where['property']] = array(strtolower($constraint[$where['constraint']]) => $value);
                break;
            }
        }

        return empty($this->query) ? new stdclass : (object)$this->query;
    }

    public function reset()
    {
        $this->from;

        $this->where = array();

        $this->sort = array();

        $this->limit = 0;

        $this->offset = 0;

        $this->query = null;
    }
}