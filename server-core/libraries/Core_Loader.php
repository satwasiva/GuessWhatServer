<?php

if (CI_VERSION !== '1.7.2') {
    require_once(BASEPATH . 'core/Loader.php');
}

class Core_Loader extends CI_Loader
{
    // The empty string at the end is used for loading the BASEPATH

    protected $_all_helper_paths = array(APPPATH, COREPATH, BASEPATH);

    protected $_all_library_paths = array(APPPATH, COREPATH, BASEPATH);

    protected $_all_model_paths = array(APPPATH, BASEPATH, COREPATH);

    var $_ci_models			= array();

    private static $_ci_instances = array();
    private $_ci_model_instances = array();
    
    function Core_Loader(){
        if (is_array(config_item('additional_search_directories'))) {
            $this->_all_helper_paths = array_merge($this->_all_helper_paths, config_item('additional_search_directories'));
            $this->_all_model_paths = array_merge($this->_all_model_paths, config_item('additional_search_directories'));
            $this->_all_library_paths = $this->_all_library_paths;
        }

        parent::__construct();
    }

	/**
	 * Database Loader
	 *
	 * @access	public
	 * @param	string	the DB credentials
	 * @param	bool	whether to return the DB object
	 * @param	bool	whether to enable active record (this allows us to override the config setting)
	 * @return	object
	 */	
	function database($params = '', $return = FALSE, $active_record = FALSE)
	{
		// Grab the super object
		$CI =& get_instance();
		
		// Do we even need to load the database class?
		if (class_exists('CI_DB') AND $return == FALSE AND $active_record == FALSE AND isset($CI->db) AND is_object($CI->db))
		{
			return FALSE;
		}	
	
		require_once(BASEPATH.'database/DB'.EXT);

		if ($return === TRUE)
		{
			return DB($params, $active_record);
		}
		
		// Initialize the db variable.  Needed to prevent   
		// reference errors with some configurations
		$CI->db = '';
		
		// Load the DB class
		$CI->db =& DB($params, $active_record);	
		
		// Assign the DB object to any existing models
		$this->_ci_assign_to_models();
	}

    function helper($helpers = array())
    {
        if ( ! is_array($helpers))
        {
            $helpers = array($helpers);
        }

        foreach ($helpers as $helper)
        {
            $helper = strtolower(str_replace(EXT, '', str_replace('_helper', '', $helper)).'_helper');

            if (isset($this->_ci_helpers[$helper]))
            {
                continue;
            }

            // Is this a helper extension request?
            $ext_helper = APPPATH.'helpers/'.config_item('subclass_prefix').$helper.EXT;
            if (file_exists($ext_helper))
            {
                $base_helper = BASEPATH.'helpers/'.$helper.EXT;

                if ( ! file_exists($base_helper))
                {
                    show_error('Unable to load the requested file: helpers/'.$helper.EXT);
                }

                include_once($ext_helper);
                include_once($base_helper);
            }
            else
            {
                $found = FALSE;
                foreach($this->_all_helper_paths as $path)
                {
                    if (file_exists($path.'helpers/'.$helper.EXT))
                    {
                        $found = TRUE;
                        include_once($path.'helpers/'.$helper.EXT);
                        break;
                    }
                }

                if($found === FALSE)
                {
                    show_error('Unable to load the requested file: helpers/'.$helper.EXT);
                }
            }

            $this->_ci_helpers[$helper] = TRUE;
        }
    }

	/**
	 * Model Loader
	 *
	 * This function lets users load and instantiate models.
	 *
	 * @access	public
	 * @param	string	the name of the class
	 * @param	string	name for the model
	 * @param	bool	database connection
	 * @return	void
	 */	
	function model($model, $name = '', $db_conn = FALSE)
	{
	    // We need to override this method from the default loader because we want to be able to support the
	    //  instantiation of multiple controllers and subsequent model loading between multiple controllers for
	    //  proper amf batching support.  Because CodeIgniter uses a superclass paradigm for the base controller class,
	    //  we need to check if a model class has been loaded in any instance of a controller class.
	    //  Look at the code for CI_Base and Loader for more details.

	    if (is_array($model))
		{
			foreach($model as $babe)
			{
				$this->model($babe);	
			}
			return;
		}

		if ($model == '')
		{
			return;
		}
	
		// Is the model in a sub-folder? If so, parse out the filename and path.
		if (strpos($model, '/') === FALSE)
		{
			$path = '';
		}
		else
		{
			$x = explode('/', $model);
			$model = end($x);			
			unset($x[count($x)-1]);
			$path = implode('/', $x).'/';
		}
	
		if ($name == '')
		{
			$name = $model;
		}
		
		if (in_array($name, $this->_ci_models, TRUE))
		{
		    $CI =& get_instance();
//		    log_message('error', "MODEL ALREADY LOADED: " . $name . "  class = " . get_class($CI));
		    if (! isset($CI->$name)) {
//		        log_message('error', "SETTING MODEL INSTANCE TO CONTROLLER: " . $name . "  class = " . get_class($CI));
		        $CI->$name = & $this->_ci_model_instances[$name];
		    }
		    return;
		}
		
		$CI =& get_instance();
		if (isset($CI->$name))
		{
			show_error('The model name you are loading is the name of a resource that is already being used: '.$name);
		}
	
		$model = strtolower($model);

        $model_exists = false;

        foreach($this->_all_model_paths as $mod_path)
        {
            if (file_exists($mod_path . 'models/'.$path.$model.EXT))
            {
                $model_exists = true;
                break;
            }
        }

        if ( ! $model_exists)
		{
			show_error('Unable to locate the model you have specified: '.$model);
		}

		if ($db_conn !== FALSE AND ! class_exists('CI_DB'))
		{
			if ($db_conn === TRUE)
				$db_conn = '';
		
			$CI->load->database($db_conn, FALSE, TRUE);
		}

        if (CI_VERSION === '2.1.0' || CI_VERSION === '2.1.1') {
            if ( ! class_exists('CI_Model'))
            {
                load_class('Model', 'core');
            }
        } else {
            if ( ! class_exists('Model'))
            {
                load_class('Model', FALSE);
            }
        }

        $found = FALSE;
        foreach($this->_all_model_paths as $mod_path)
        {
            $file = $mod_path.'models/'.$path.$model.EXT;
            if (file_exists($file))
            {
                $found = TRUE;
                include_once($file);
                break;
            }
        }

        if($found === FALSE)
        {
            show_error('Unable to load the requested file: models/'.$model.EXT);
        }

		$model = ucfirst($model);
				
//	    log_message('info', "LOADING MODEL: " . $name . "  class = " . get_class($CI));
		$CI->$name = new $model();
        $CI->$name->_assign_libraries();

		$all_ci = get_all_loaded_instances();
		foreach ($all_ci as $old_ci) {
		    if (! isset($old_ci->$name)) {
//		        log_message('error', "BACKLOADING MODEL INSTANCE TO CONTROLLER: " . $name . "  class = " . get_class($old_ci));
		        $old_ci->$name = & $CI->$name;
		    }
		}

		$this->_ci_model_instances[$name] = & $CI->$name;

		$this->_ci_models[] = $name;	
	}

    // --------------------------------------------------------------------

    /**
     * Class Loader
     *
     * This function lets users load and instantiate classes.
     * It is designed to be called from a user's app controllers.
     *
     * @access	public
     * @param	string	the name of the class
     * @param	mixed	the optional parameters
     * @param	string	an optional object name
     * @return	void
     */
    function library($library = '', $params = NULL, $object_name = NULL)
    {
        if ($library == '')
        {
            return FALSE;
        }

        if ( ! is_null($params) AND ! is_array($params))
        {
            $params = NULL;
        }

        if (is_array($library))
        {
            foreach ($library as $class)
            {
                $this->_ci_load_class($class, $params, $object_name);
            }
        }
        else
        {
            $this->_ci_load_class($library, $params, $object_name);
        }
    }

    // --------------------------------------------------------------------

    /**
     * Load class
     *
     * This function loads the requested class.
     *
     * @access	private
     * @param 	string	the item that is being loaded
     * @param	mixed	any additional parameters
     * @param	string	an optional object name
     * @return 	void
     */
    function _ci_load_class($class, $params = NULL, $object_name = NULL)
    {
        // Get the class name, and while we're at it trim any slashes.
        // The directory path can be included as part of the class name,
        // but we don't want a leading slash
        $class = str_replace(EXT, '', trim($class, '/'));

        // Was the path included with the class name?
        // We look for a slash to determine this
        $subdir = '';
        if (strpos($class, '/') !== FALSE)
        {
            // explode the path so we can separate the filename from the path
            $x = explode('/', $class);

            // Reset the $class variable now that we know the actual filename
            $class = end($x);

            // Kill the filename from the array
            unset($x[count($x)-1]);

            // Glue the path back together, sans filename
            $subdir = implode($x, '/').'/';
        }

        // We'll test for both lowercase and capitalized versions of the file name
        foreach (array(ucfirst($class), strtolower($class)) as $class)
        {
            $subclass = APPPATH.'libraries/'.$subdir.config_item('subclass_prefix').$class.EXT;

            // Is this a class extension request?
            if (file_exists($subclass))
            {
                $baseclass = BASEPATH.'libraries/'.ucfirst($class).EXT;

                if ( ! file_exists($baseclass))
                {
                    log_message('error', "Unable to load the requested class: ".$class);
                    show_error("Unable to load the requested class: ".$class);
                }

                // Safety:  Was the class already loaded by a previous call?
                if (in_array($subclass, $this->_ci_loaded_files))
                {
                    // Before we deem this to be a duplicate request, let's see
                    // if a custom object name is being supplied.  If so, we'll
                    // return a new instance of the object
                    if ( ! is_null($object_name))
                    {
                        $CI =& get_instance();
                        if ( ! isset($CI->$object_name))
                        {
                            return $this->_ci_init_class($class, config_item('subclass_prefix'), $params, $object_name);
                        }
                    }

                    $is_duplicate = TRUE;
                    log_message('debug', $class." class already loaded. Second attempt ignored.");
                    return;
                }

                include_once($baseclass);
                include_once($subclass);
                $this->_ci_loaded_files[] = $subclass;

                return $this->_ci_init_class($class, config_item('subclass_prefix'), $params, $object_name);
            }

            $is_duplicate = FALSE;
            // If we didn't find it in application, let's check all the other directories
            foreach( $this->_all_library_paths as $path)
            {
                $filepath = $path.'libraries/'.$subdir.$class.EXT;

                // Does the file exist?  No?  Bummer...
                if ( ! file_exists($filepath))
                {
                    continue;
                }

                // Safety:  Was the class already loaded by a previous call?
                if (in_array($filepath, $this->_ci_loaded_files))
                {
                    // Before we deem this to be a duplicate request, let's see
                    // if a custom object name is being supplied.  If so, we'll
                    // return a new instance of the object
                    if ( ! is_null($object_name))
                    {
                        $CI =& get_instance();
                        if ( ! isset($CI->$object_name))
                        {
                            return $this->_ci_init_class($class, '', $params, $object_name);
                        }
                    }

                    $is_duplicate = TRUE;
                    log_message('debug', $class." class already loaded. Second attempt ignored.");
                    return;
                }

                include_once($filepath);
                $this->_ci_loaded_files[] = $filepath;
                return $this->_ci_init_class($class, '', $params, $object_name);
            } // END FOREACH
        }

        // One last attempt.  Maybe the library is in a subdirectory, but it wasn't specified?
        if ($subdir == '')
        {
            $path = strtolower($class).'/'.$class;
            return $this->_ci_load_class($path, $params);
        }

        // If we got this far we were unable to find the requested class.
        // We do not issue errors if the load call failed due to a duplicate request
        if ($is_duplicate == FALSE)
        {
            log_message('error', "Unable to load the requested class: ".$class);
            show_error("Unable to load the requested class: ".$class);
        }
    }

    /**
   	 * Assign to Models
   	 *
   	 * Makes sure that anything loaded by the loader class (libraries, plugins, etc.)
   	 * will be available to models, if any exist.
   	 *
   	 * @access	private
   	 * @param	object
   	 * @return	array
   	 */
    function _ci_assign_to_models()
    {
        if (count($this->_ci_models) == 0)
        {
            return;
        }

        $CI =& get_instance();
        foreach ($this->_ci_models as $model)
        {
            $CI->$model->_assign_libraries();
        }
    }
}
