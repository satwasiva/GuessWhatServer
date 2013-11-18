<?php
define("AMFPHP_BASE", realpath(dirname(dirname(dirname(__FILE__)))) . "/");
require_once(AMFPHP_BASE . "shared/app/BasicGateway.php");
require_once(AMFPHP_BASE . "shared/util/MessageBody.php");
require_once(AMFPHP_BASE . "shared/util/functions.php");
require_once(AMFPHP_BASE . "json/app/Actions.php");



class Gateway extends BasicGateway
{

	public function createBody()
	{
		$GLOBALS['amfphp']['encoding'] = 'json';
		$body = new MessageBody();

		if (!isset($_GET['svc'])) {
			echo("The JSON gateway is installed correctly. Call like this: Json_gateway?svc=MyClazz.myMethod");
			exit();
		}
		$service = $_GET['svc'];
		$rawArgs = array($service);

		if (!isset($GLOBALS['HTTP_RAW_POST_DATA'])){
	        $GLOBALS['HTTP_RAW_POST_DATA'] = file_get_contents('php://input');
		}
		
		if(isset($GLOBALS['HTTP_RAW_POST_DATA']))
		{
			$rawArgs[] = $GLOBALS['HTTP_RAW_POST_DATA'];
		}

		$body->setValue($rawArgs);
		return $body;
	}

	/**
	 * Sets the base path for loading service methods.
	 *
	 * Call this method to define the directory to look for service classes in.
	 * Relative or full paths are acceptable
	 *
	 * @param string $path The path the the service class directory
	 */
	function setClassPath($value) {
		$path = realpath($value . '/') . '/';
		$GLOBALS['amfphp']['classPath'] = $path;
	}

    function setClassPaths($valueArr) {
        foreach($valueArr as $value) {
            $path = realpath($value . '/') . '/';
            $GLOBALS['amfphp']['classPathArray'][] = $path;
        }
    }

	/**
	 * Create the chain of actions
	 */
	function registerActionChain()
	{
		$this->actions['deserialization'] = 'deserializationAction';
		$this->actions['classLoader'] = 'classLoaderAction';
		$this->actions['security'] = 'securityAction';
		$this->actions['exec'] = 'executionAction';
		$this->actions['serialization'] = 'serializationAction';
	}

    /**
   	 * The service method runs the gateway application.  It turns the gateway 'on'.  You
   	 * have to call the service method as the last line of the gateway script after all of the
   	 * gateway configuration properties have been set.
   	 *
   	 */
   	public function service() {

   		if (!isset($GLOBALS['HTTP_RAW_POST_DATA'])){
   		    $GLOBALS['HTTP_RAW_POST_DATA'] = file_get_contents('php://input');
   		}

        if (empty($_GET['svc'])) {
            echo("The JSON gateway is installed correctly. Call like this: Json_gateway?svc=MyClazz.myMethod");
            exit();
        }
        ob_start();
        $body = $this->createBody();

        $performance = array();
        foreach($this->actions as $key => $action)
        {
            $start = microtime(true);
            $result = $action($body); //   invoke the first filter in the chain
            $end = microtime(true);
            $performance[$action] = ' took ' . ($end - $start) * 1000 . ' ms ';
            if($result === false)
            {
                //Go straight to serialization actions
                $serAction = 'serializationAction';
                $serAction($body);
                break;
            }
        }
        //debug(__FILE__, 'JSON Gateway performance: ' . print_r($performance, true));

        $results =  $body->getResults();
        $output_contents = ob_get_contents();
        if(strlen($output_contents) > 1) {
            debug(__FILE__, 'captured extra output contents on JSON Gateway: ' . $output_contents);
        }
        ob_end_clean();

        header('Content-type: application/json; charset=utf-8;'); // define the proper header

        //Finally to Gary who appears to have find a solution which works even more reliably
        $dateStr = date("D, j M Y ") . date("H:i:s", strtotime("-2 days"));
        header("Expires: $dateStr GMT");
        header("Pragma: no-store");
        header("Cache-Control: no-store");

        echo $results;
   	}
}
?>