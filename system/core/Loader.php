<?php
/* 
| --------------------------------------------------------------
| Plexis
| --------------------------------------------------------------
| Author:       Steven Wilson 
| Author:       Tony (Syke)
| Copyright:    Copyright (c) 2011-2012, Plexis
| License:      GNU GPL v3
| ---------------------------------------------------------------
| Class: Loader()
| ---------------------------------------------------------------
|
| This class is used to load classes and librarys into the calling
| class / method.
|
*/
namespace Core;

class Loader
{

/*
| ---------------------------------------------------------------
| Constructor
| ---------------------------------------------------------------
|
*/
    public function __construct()
    {
        // Add trace for debugging
        \Debug::trace('Loader class initialized', __FILE__, __LINE__);
    }

/*
| ---------------------------------------------------------------
| Method: library()
| ---------------------------------------------------------------
|
| This method is used to call in a class from either the APP
| library, or the system library folders.
|
| @Param: (String) $name - The name of the class, with or without namespacing
| @Param: (Bool | String) $instance - Do we instance the class (true|false)? 
| May also specify the instance name (IE: class Test instance as TeStInG)
| @Param: (Bool) $surpress - set to TRUE to bypass the error screen
|   if the class fails to initiate, and return false instead
| @Return: (Object) Returns the library class
|
*/
    public function library($name, $instance = TRUE, $surpress = FALSE)
    {
        // Load the Class
        $Obj = load_class($name, 'Library', $surpress);
        
        // Do we instance this class?
        if($instance != false)
        {
            $FB = get_instance();
            if(is_object($FB))
            {
                $instance = (!is_string($instance)) ? $name : $instance;
                if(!isset($FB->$instance)) $FB->$instance = $Obj;
            }
        }
        
        return $Obj;
    }

/*
| ---------------------------------------------------------------
| Method: model()
| ---------------------------------------------------------------
|
| This method is used to call in a model
|
| @Param: (String) $name - The name of the model. You may also go path/to/$name
| @Param: (Mixed) $instance_as - How you want to access it as in the 
|   controller (IE: $instance_as = test; In controller: $this->test)
| @Return: (Object) Returns the model
|
*/
    public function model($name, $instance = true, $silence = false)
    {
        // Fix names
        $class = ucfirst($name);
        $name = strtolower($name);
        
        // Check the registry
        $Obj = \Registry::load($class);
        if($Obj !== NULL) return $Obj;
        
        // Add debug tracer
        \Debug::trace("Loading model \"{$name}\"...", __FILE__, __LINE__);
        
		$model_path = "";
        // Include the model page
        if($GLOBALS['is_module'] == TRUE)
			$model_path = path( ROOT, "third_party", "modules", $GLOBALS["controller"], "models", $name . ".php" );
        else
			$model_path = path( SYSTEM_PATH, "models", $name . ".php" );
			
		require_once( $model_path );
        
        // Load the class
        try{
            $Obj = new $class();
            \Debug::trace("Successfully loaded model \"{$name}\"", __FILE__, __LINE__);
        }
        catch(\Exception $e) {
            $Obj = FALSE;
            \Debug::trace("Model \"{$name}\" failed to initialize. Message given: ". $e->getMessage(), __FILE__, __LINE__);
        }
        
        // Instnace the Model in the controller
        if($instance != false)
        {
            $FB = get_instance();
            if(is_object($FB))
            {
                $instance = (!is_string($instance)) ? $class : $instance;
                if(!isset($FB->$instance)) $FB->$instance = $Obj;
            }
        }
        
        // Store the model
        \Registry::store($class, $Obj);

        return $Obj;
    }

/*
| ---------------------------------------------------------------
| Method: view()
| ---------------------------------------------------------------
|
| This method is used to load the view file and display it
|
| @Param: (String) $name - The name of the requested view file
| @Param: (Array) $data - an array of variables to be extracted
| @Param: (Bool) $skip - Skip the template system and use parent?
|
*/
    public function view($name, $data = array(), $skip = FALSE)
    {
        // If we are requesting to use the default render system
        if($skip == TRUE)
        {
            parent::view($name, $data);
        }
        else
        {
            // We are just going to let the template engine handle this
            $template = $this->library('Template');
            $template->render($name, $data);
        }
    }
    
/*
| ---------------------------------------------------------------
| Method: database()
| ---------------------------------------------------------------
|
| This method is used to setup a database connection
|
| @Param: (String) $args - The indentifier of the DB connection in 
|   the DB config file.
| @Param: (Mixed) $instance - If you want to instance the connection
|   in the controller, set to TRUE, or the instance variable desired
| @Param: (Bool) $surpress - set to TRUE to bypass the error screen
|   if the connection failes, and just return false
| @Return: (Object) Returns the database object / connection
|
*/
    public function database($args, $instance = TRUE, $surpress = FALSE)
    {
        // Load our connection settings. We can allow custom connection arguments
        if(!is_array($args))
        {
            // Check our registry to see if we already loaded this connection
            $Obj = \Registry::load("DBC_".$args);
            if($Obj !== NULL)
            {
                // Skip to the instancing part unless we set instance to FALSE
                if($instance != FALSE) goto Instance;
                return $Obj;
            }
            
            // Add Trace
            \Debug::trace("Loading database connection \"{$args}\"...", __FILE__, __LINE__);
        
            // Get the DB connection information
            $info = load_class('Config')->get($args, 'DB');
            if($info === NULL)
            {
                // Add Trace
                \Debug::trace("Failed to load database connection preset \"{$args}\" because it doesnt exist in the config", __FILE__, __LINE__);
                show_error('db_key_not_found', array($args), E_ERROR);
            }
        }
        else
        {
            // Assign our $info variable, and set our connection name to $instance (unless it equals true or 1)
            $info = $args;
            if(is_bool($instance) || is_numeric($instance))
            {
                $instance = FALSE;
                $args = 'custom_database';
            }
            else
            {
                $args = $instance;
            }
            
            // Add Trace
            \Debug::trace("Loading custom database connection...", __FILE__, __LINE__);
        }
        
        // Check for a DB class in the Application, and system core folder
        $info['driver'] = strtolower($info['driver']);
        if(file_exists(ROOT . DS . 'system'. DS .'database' . DS . 'Driver.php'))
        {
            require_once(ROOT . DS . 'system'. DS .'database' . DS . 'Driver.php');
        }
        
        // Not in the registry, so istablish a new connection
        $dispatch = "Database\\Driver";
        try{
            $Obj = new $dispatch( $info );
            \Debug::trace("Successfully connected to database", __FILE__, __LINE__);
        }
        catch(\Exception $e) {
            $Obj = false;
            
            // Add Trace
            \Debug::trace("Failed to load database connection. Message given: ". $e->getMessage(), __FILE__, __LINE__);
        }
        
        // Error?
        if($surpress == FALSE && $Obj == FALSE)
        {
            show_error('db_connect_error', array( $info['database'], $info['host'], $info['port'] ), E_ERROR);
        }
        
        // Store the connection in the registry
        \Registry::store("DBC_".$args, $Obj);		
        
        // Here is our instance goto
        Instance:
        {
            // If user wants to instance this, then we do that
            if($instance != FALSE && !is_numeric($args))
            {
                if($instance === TRUE) $instance = $args;

                // Easy way to instance the connection is like this
                $FB = get_instance();
                if(is_object($FB))
                {
                    if(!isset($FB->$instance)) $FB->$instance = $Obj;
                }
            }
        }
        
        // Return the object!
        return $Obj;
    }
    
/*
| ---------------------------------------------------------------
| Method: helper()
| ---------------------------------------------------------------
|
| This method is used to call in a helper file from either the 
| application/helpers, or the core/helpers folders.
|
| @Param: (String) $name - The name of the helper file
| @Return: (None)
|
*/
    public function helper($name)
    {
        // Static array of helpers
        static $loaded = array();
        
        // Lowercase the name because it isnt a class file!
        $name = strtolower($name);
        
        // Add debug tracer
        \Debug::trace('Loading helper "'. $name .'"...', __FILE__, __LINE__);
        
        // Make sure this helper isnt already loaded
        if(in_array($name, $loaded))
        {
            // Add debug tracer
            \Debug::trace('Helper "'. $name .'" already loaded, returning false.', __FILE__, __LINE__);
            return;
        }
        else
        {
            $loaded[] = $name;
        }
        
        // Check the core/helpers folder
        if(file_exists( SYSTEM_PATH . DS .'helpers'. DS . $name .'.php')) 
        {
            require_once(SYSTEM_PATH . DS .'helpers'. DS . $name .'.php');
        }
    }
    
/*
| ---------------------------------------------------------------
| Method: plugin()
| ---------------------------------------------------------------
|
| This method is used to load a plugin
|
| @Param: (String) $name - The name of the plugin
|
*/
    public function plugin($name)
    {
        // Create our classname
        $name = ucfirst($name);
        $store_name = 'Plugins_'. $name;
        
        // Check if the plugin is already loaded
        $Obj = \Registry::load($store_name);
        if( $Obj === null )
        {
            // Add debug tracer
            \Debug::trace('Loading plugin "'. $name .'"...', __FILE__, __LINE__);
        
            // We have to manually load the plugin
            $file = path( ROOT, 'third_party', 'plugins', $name .'.php');
            if(!file_exists($file))
            {
                show_error('plugin_not_found', array($name), E_ERROR);
                return false;
            }
            
            // Include the file just once!
            include_once( $file );
            
            // Init the plugin
            try {
                $className = "\Plugins\\". $name;
                $Obj = new $className();
                
                // Add debug tracer
                \Debug::trace('Plugin "'. $name .'" was loaded successfully', __FILE__, __LINE__);
            } 
            catch(\Exception $e) {
                $Obj = false;
                $message = $e->getMessage();
                
                // Add debug tracer
                \Debug::trace('Plugin "$name" failed to initialize. Message given was: '. $message, __FILE__, __LINE__);
                show_error('plugin_failed_init', array($name, $message), E_WARNING);
            }
            
            // Store the object
            \Registry::store($store_name, $Obj);
        }
        
        // Make sure the object IS an object
        return (is_object($Obj)) ? $Obj : false;
    }

/*
| ---------------------------------------------------------------
| Method: wowlib()
| ---------------------------------------------------------------
|
| This method is used to load a WoW library
|
| @Param: (Int) $id - The realm ID as stored in the `scms_realms` table
| @Param: (String) $instance_as - The name the instance variable
|   Ex: $this->$instance_as->method()
|
*/
    public function wowlib($id = 0, $instance_as = FALSE)
    {
        // Get our realm id if none is provieded
        if($id === 0) $id = config('default_realm_id');
        
        // Make sure we havent loaded the lib already
        $Obj = \Registry::load('Wowlib_r'.$id);
        if($Obj !== NULL) return $Obj;
        
        // Make sure the wowlib is initialized
        if(!class_exists('Wowlib', false)) $this->_initWowlib();
        
        // Load our driver name
        $DB = $this->database('DB', FALSE);
        $realm = $DB->query("SELECT `id`, `name`, `driver`, `char_db`, `world_db` FROM `pcms_realms` WHERE `id`=".$id)->fetchRow();
        
        // Make sure we didnt get a false DB return
        if($realm === FALSE)
        {
            $language = load_language_file('messages');
            $message = $language['wowlib_realm_doesnt_exist'];
            show_error($message, array($id), E_ERROR);
        }
        
        // Add debug tracer
        \Debug::trace('Loading Wowlib driver "'. $realm['driver'] .'"', __FILE__, __LINE__);
        
        // Unserialize our database information
        $char = unserialize($realm['char_db']);
        $world = unserialize($realm['world_db']);
        
        // Init the driver
        $class = \Wowlib::load($realm['driver'], $char, $world);
        
        // Store the class statically and return the class
        \Registry::store('Wowlib_r'.$id, $class);
        
        // Check to see if the user wants to instance
        if($instance_as !== FALSE) get_instance()->$instance_as = $class;
        return $class;
    }
    
/*
| ---------------------------------------------------------------
| Method: realm()
| ---------------------------------------------------------------
|
| This method is used to load a WoW Emulator, and connect to 
|   the realm
|
|   @Param: $instance - Instance the realm?
|
*/
    public function realm($instance = TRUE)
    {  
        // Get our emulator from the Config File
        $emulator = ucfirst( config('emulator') );
        $class_name = "Emulator_".$emulator;
        
        // Make sure we havent loaded the lib already
        $realm = \Registry::load($class_name);
        if($realm !== NULL) goto Instance;

        // Init the class if it doesnt exist
        if(!class_exists('Wowlib', false)) $this->_initWowlib();
        
        // Add debug tracer
        \Debug::trace('Loading Realm from Wowlib', __FILE__, __LINE__);
        
        // Fetch the emulator class
        try {
            $realm = \Wowlib::getRealm(0, 'RDB');
        }
        catch(\Exception $e) {
            \Debug::silent_mode(false);
            $message = 'Wowlib Error: '. $e->getMessage();
            
            // Add debug tracer
            \Debug::trace($message, __FILE__, __LINE__);
            show_error($message, false, E_ERROR);
        }
        
        // Store the class statically and return the class
        \Registry::store($class_name, $realm);
        
        // Instance
        Instance:
        {
            if($instance == TRUE)
            {
                $FB = get_instance();
                if(is_object($FB)) $FB->realm = $realm;
            }
        }
        
        // We need to make sure the realm loaded ok, or thrown an error
        if(!is_object($realm))
        {
            // Add debug tracer
            $message = 'Wowlib failed to fetch realm. Assuming the realm database is offline.';
            \Debug::trace($message, __FILE__, __LINE__);
            show_error($message, false, E_ERROR);
        }
        else
        {
            \Debug::trace('Successfully fetched the realm connection from the Wowlib', __FILE__, __LINE__);
        }
        
        return $realm;
    }
    
    protected function _initWowlib()
    {
        // Include the wowlib file
        require path( ROOT, 'third_party', 'wowlib', 'Wowlib.php' );
        
        // Add Trace
        \Debug::trace('Initializing Wowlib...', __FILE__, __LINE__);
        
        // Try to init the wowlib
        try {
            \Wowlib::Init( config('emulator') );
            \Debug::trace('Wowlib Initialized successfully', __FILE__, __LINE__);
        }
        catch(Exception $e) {
            \Debug::silent_mode(false);
            \Debug::trace('Wowlib failed to initialize. Message thrown was: '. $e->getMessage(), __FILE__, __LINE__);
            show_error('Wowlib Error: '. $e->getMessage(), false, E_ERROR);
        }
    }
}
// EOF