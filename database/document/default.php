<?php

class ComMongoDatabaseDocumentDefault extends ComMongoDatabaseDocumentAbstract implements KObjectInstantiatable
{
	/**
     * Associative array of table instances
     * 
     * @var array
     */
    private static $_instances = array();
    
	/**
     * Force creation of a singleton
     *
     * @return ComMongoDatabaseDocumentDefault
     */
    public static function getInstance($config = array(), KFactoryInterface $factory = null)
    {
       // Check if an instance with this identifier already exists or not
        if (!$factory->exists($config->identifier))
        {
            //Create the singleton
            $classname = $config->identifier->classname;
            $instance  = new $classname($config);
            $factory->set($config->identifier, $instance);
        }
        
        return $factory->get($config->identifier);
    }
}