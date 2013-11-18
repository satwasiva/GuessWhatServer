<?php

const DEBUG_JSON = false;

function deserializationAction(&$body)
{
	$args = $body->getValue();
	$target = $args[0];

	//$baseClassPath = $GLOBALS['amfphp']['classPath'];
    $baseClassPathArray = $GLOBALS['amfphp']['classPathArray'];

    $lpos = strrpos($target, '.');

    $methodname = substr($target, $lpos + 1);
    $trunced = substr($target, 0, $lpos);
    $lpos = strrpos($trunced, ".");

    foreach($baseClassPathArray as $baseClassPath)
    {
        if ($lpos === false) {
            $classpath = $baseClassPath . $trunced . ".php";
        } else {
            $classpath = $baseClassPath . str_replace(".", "/", $trunced) . ".php"; // removed to strip the basecp out of the equation here
        }

        $body->classPaths[] = $classpath;
    }

    if ($lpos === false) {
        $classname = $trunced;
        $uriclasspath = $trunced . ".php";
    } else {
        $classname = substr($trunced, $lpos + 1);
        $uriclasspath = str_replace(".", "/", $trunced) . ".php"; // removed to strip the basecp out of the equation here
    }

	$body->methodName = $methodname;
	$body->className = $classname;
	$body->uriClassPath = $uriclasspath;

	//Now deserialize the arguments
	array_shift($args);

	$actualArgs= json_decode(urldecode($args[0]), true);

    $actualArgs = convert_to_mapped_objects($actualArgs);
    if (DEBUG_JSON) debug(__FILE__, "JSON_DEBUG:  Final object: " . print_r($actualArgs, true));
	$body->setValue($actualArgs);
}


function isAssoc($arr)
{
    return array_keys($arr) !== range(0, count($arr) - 1);
}

function convert_to_mapped_objects($data) {
    if (DEBUG_JSON) debug(__FILE__, "JSON_DEBUG: convert_to_mapped_objects: " . json_encode($data));
    if (is_array($data)) {
        $conv_array = array();
        if (isAssoc($data)) {
            foreach ($data as $k => $v) {
                $conv_array[$k] = convert_to_mapped_objects($v);
            }
            if (key_exists('_explicitType', $data)) {
                $conv_array = convert_assoc_to_mapped_obj($conv_array);
            }
        } else {
            foreach ($data as $v) {
                $conv_array[] = convert_to_mapped_objects($v);
            }
        }
        return $conv_array;
    } else if (is_object($data)) {
        $object = convert_to_mapped_obj($data);
        foreach($object as $k => $v) {
            $object->$k = convert_to_mapped_objects($v);
        }
        return $object;
    } else {
        return $data;
    }
}
function convert_assoc_to_mapped_obj($assoc) {
    if (key_exists('_explicitType', $assoc)) {
        $mappedClass = $assoc['_explicitType'];

        $include = '';
        $dirs = array(APPPATH, COREPATH);
        foreach ($dirs as $dir) {
            $include = $dir . 'models/' . str_replace(".", "/", $mappedClass) . '.php';
            if (file_exists($include)){
                break;
            }
        }

        if (!file_exists($include)) {
            debug(__FILE__, "File not found in JSON gateway: " . $include);
        } else {
            require_once($include);
        }
        $lastPlace = strrpos('.' . $mappedClass, '.');
        $classname = substr($mappedClass, $lastPlace);
        if(class_exists($classname))
        {
            $clazz = new $classname;
        } else {
            $clazz = new stdClass();
        }
        // Map all fields of stdObj into new class obj
        foreach ($assoc as $key => $val) {
            // RNG - TODO:  We should probably limit it to only setting non-static fields!!
            if (DEBUG_JSON) debug(__FILE__, "Assoc Setting field " . $key . " on " . $classname);
            $clazz->$key = $val;
        }
        return $clazz;
    }

    return $assoc;
}

function convert_to_mapped_obj($stdObj) {
    if (get_class($stdObj) == 'stdClass' && isset($stdObj->_explicitType)) {
        $mappedClass = $stdObj->_explicitType;
        $include = '';
        $dirs = array(APPPATH, COREPATH);
        foreach ($dirs as $dir) {
            $include = $dir . 'models/' . str_replace(".", "/", $mappedClass) . '.php';
            if (file_exists($include)){
                break;
            }
        }
        
        if (!file_exists($include)) {
            debug(__FILE__, "File not found in JSON gateway: " . $include);
            return $stdObj;
        }
        require_once($include);
        $lastPlace = strrpos('.' . $mappedClass, '.');
        $classname = substr($mappedClass, $lastPlace);
        if(class_exists($classname))
        {
            $clazz = new $classname;
            // Map all fields of stdObj into new class obj
            foreach ($stdObj as $key => $val) {
                $clazz->$key = $val;
            }
            return $clazz;
        } else {
            return $stdObj;
        }
    }
    return $stdObj;
}


function executionAction(& $body)
{
	$classConstruct = &$body->getClassConstruct();
	$methodName = $body->methodName;

	$args = $body->getValue();
	
	if ($args == null) {
		
		$content_length = 0;
		$raw_post_data_length = 0;
		
		if (isset($_SERVER['CONTENT_LENGTH'])) {
			$content_length = (int) $_SERVER['CONTENT_LENGTH'];
			$raw_post_data_length = strlen($GLOBALS['HTTP_RAW_POST_DATA']);
		}

		if ($raw_post_data_length < $content_length) {
			error(__FILE__, "INCOMPLETE CONTENT LENGTH." .
					", CLASS NAME:" . $body->className .
					", METHOD NAME:" . $methodName);
			
			$result = array(
					'status'=> "INCOMPLETE_DATA",
					'responses' => array(),
			);
			$body->setResults($result);
			
			return;
		}
	} 
	
	$output = Executive::doMethodCall($body, $classConstruct, $methodName, $args);
		
	if($output !== "__amfphp_error")
	{
		$body->setResults($output);
	}

	if (DEBUG_JSON) debug(__FILE__, print_r($output, true));
}


function serializationAction(& $body)
{
	//Take the raw response
	$rawResponse = & $body->getResults();

	adapterMap($rawResponse);

	//Now serialize it
	$encodedResponse = json_encode($rawResponse);

	if(count(NetDebug::getTraceStack()) > 0)
	{
		$trace = "/*" . implode("\n", NetDebug::getTraceStack()) . "*/";
		$encodedResponse = $trace . "\n" . $encodedResponse;
	}

	$body->setResults($encodedResponse);
}

if(!function_exists("json_encode"))
{
	include_once(AMFPHP_BASE . "shared/util/JSON.php");

	function json_encode($val)
	{
		$json = new Services_JSON();
		return $json->encode($val);
	}

	function json_decode($val, $asAssoc = FALSE)
	{
		if($asAssoc)
		{
			$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		}
		else
		{
			$json = new Services_JSON();
		}
		return $json->decode($val);
	}
}

?>