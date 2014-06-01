<?php
class template {
	private $baseUrl   		= '',
			$defaultPath    = '',
			$fullUrl   		= '',
			$params    		= '',
			$doReplace 		= true,
			$search    		= [],
			$replace   		= [],
			$output    		= false,
			$headerSet 		= false
	;

	/**
	 * [__construct]
	 * @param string $url         Domain name that we wanna duplicate
	 * @param string $defaultPath Default domainpath path that we wanna use
	 * @param mixed $params       Params that we wanna use on the request
	 */
	public function __construct($url=false, $defaultPath=false, $params=false) {

		//Check if we got valid an url
		if ($url && !is_string($url)) {
			throw new InvalidArgumentException('Your given URL is invalid');
		}

		//Check if we got valid an defaultPath
		if ($defaultPath && !is_string($defaultPath)) {
			throw new InvalidArgumentException('Your given defaultPath is invalid');
		}

		//Check if we got valid params
		if ($params && !is_string($params) && !is_array($params)) {
			throw new InvalidArgumentException('Your given params is invalid');
		}

		//Check if we got an array with params
		if (!empty($params) && is_array($params)) {
			$params = http_build_query($params, '', '&amp;');
		}

		//Save defaultPath
		$this->defaultPath = ($defaultPath ? $defaultPath : '');

		//Build url path
		$path = (isset($_GET['url']) && !empty($_GET['url']) ? $_GET['url'] : $this->defaultPath );

		//Build base URL
		$this->baseUrl = 'http://'.$url.'/';

		//Save URL with path
		$this->fullUrl = $this->baseUrl.$path;

		//Check if this path has an extention
		if ( ($extention = substr(strrchr($path, '.'), 1)) ) {
			$this->doReplace = false;
		} else {
			if (!empty($params)) {
				$this->fullUrl  .= '?'.$params.'&';

				//Save params
				$this->params = $params;
			}
		}
		
		$this->fullUrl = str_replace(' ', '+', $this->fullUrl);
		$ch  = curl_init();

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)'));
		curl_setopt($ch, CURLOPT_URL, $this->fullUrl); 

		//Check if we need to do an POST request
		if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			if (isset($_POST)) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
			}
		}
		//Set proxy Cookie if we got it
		if (isset($_COOKIE['proxyCookie'])) {
			// cookies to be sent
			curl_setopt($ch, CURLOPT_COOKIE, join(';', (array)json_decode($_COOKIE['proxyCookie'],1)));
		}

		$response = curl_exec($ch);

		//Check if we got response
		if ($response !== false) {

			// Get headers and page content
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$headers     = substr($response, 0, $header_size);
			$body        = substr($response, $header_size);				
			$headers     = http_parse_headers($headers);

			//Check if we need to set cookies
			if (isset($headers['Set-Cookie'])) {
				setcookie("proxyCookie", json_encode($headers['Set-Cookie']), time()+3600);
			}

			//heck if we need to set content type
			if (isset($headers['Content-Type'])) {
				header('content-type: '.trim($headers['Content-Type']));
			}

			//Check if we need to set expire date
			if (isset($headers['Expires'])) {
				header('Expires: '.trim($headers['Expires']));
			}

			//Check if we need to set date
			if (isset($headers['Date'])) {
				header('Date: '.trim($headers['Date']));
			}

			//Save output
			$this->output = $body;
		} else {
			curl_close($ch);
			throw new DomainException('Couldnt receive url content: '.$url);				
		}

		curl_close($ch);
	}

	/**
	 * defaultPath
	 * @return string Return defaultPath
	 */
	public function defaultPath()	{
		return (!empty($this->defaultPath) ? $this->defaultPath : '');
	}

	/**
	 * getParams
	 * @return string Return params
	 */
	public function getParams()	{
		return (!empty($this->params) ? $this->params : '');
	}

	/**
	 * getPath
	 * @return string Return path
	 */
	public function getPath()	{
		return (!empty($this->getPath) ? $this->getPath : '');
	}

	/**
	 * findAndReplace find and replace content in output
	 * @param  array $search  The value being searched for, otherwise known as the needle. An array may be used to designate multiple needles. 
	 * @param  array $replace The replacement value that replaces found search values. An array may be used to designate multiple replacements. 
	 * @return object this array so we can chain
	 */
	public function findAndReplace(Array $search=[] , Array $replace=[]) {

		//Check if we got something to search and replace
		if (empty($search) || empty($replace)) {
			throw new InvalidArgumentException('Check your search and replace array');
		}

		//Count searches and replaces
		$totalSearch  = count($search);
		$totalReplace = count($replace);

		//Check if we got more replacements than searches
		if ($totalReplace > $totalSearch) {
			throw new InvalidArgumentException('There are more replaces then searches');
		}

		//Merge the searches with the array that we already got
		$this->search  = array_merge($this->search, $search);

		//When we we got less replaces then searches make them the same amount
		if ($totalSearch !== $totalReplace) {
			$replace = array_pad($replace, $totalSearch, ($totalReplace === 1 ? reset($replace) : ''));
		}

		//Merge the replaces with the array that we already got
		$this->replace = array_merge($this->replace, $replace);

		return $this;
	}

	/**
	 * Parse our received page content
	 * @return string Return the page content with our replacements
	 */
	private function parse() {

		$output = &$this->output;

		//Check if we may replace the page content
		if ($this->doReplace && !empty($this->search)) {
			$output = str_ireplace($this->search, $this->replace, $output);
		}

		return $output;
	}

	/**
	 * __tostring Decide how it will react when it is treated like a string
	 * @return string Return the page content with our replacements
	 */
	public function __tostring()
	{
		return $this->parse();
	}
}

/**
 * Create http_parse_headers function if not exists
 */
if (!function_exists('http_parse_headers'))
{
	/**
	 * http_parse_headers Parse HTTP headers
	 * @param  string $raw_headers string containing HTTP headers 
	 * @return array Return HTTP headers with header name as key
	 */
    function http_parse_headers($raw_headers)
    {
        $headers = array();
        $key = ''; // [+]

        foreach(explode("\n", $raw_headers) as $i => $h)
        {
            $h = explode(':', $h, 2);

            if (isset($h[1]))
            {
                if (!isset($headers[$h[0]]))
                    $headers[$h[0]] = trim($h[1]);
                elseif (is_array($headers[$h[0]]))
                {
                    // $tmp = array_merge($headers[$h[0]], array(trim($h[1]))); // [-]
                    // $headers[$h[0]] = $tmp; // [-]
                    $headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1]))); // [+]
                }
                else
                {
                    // $tmp = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [-]
                    // $headers[$h[0]] = $tmp; // [-]
                    $headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1]))); // [+]
                }

                $key = $h[0]; // [+]
            }
            else // [+]
            { // [+]
                if (substr($h[0], 0, 1) == "\t") // [+]
                    $headers[$key] .= "\r\n\t".trim($h[0]); // [+]
                elseif (!$key) // [+]
                    $headers[0] = trim($h[0]);trim($h[0]); // [+]
            } // [+]
        }

        return $headers;
    }
}