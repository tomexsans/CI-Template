<?php
/*
 * Template.php
 * @author: Rhustum Evaristo
 * ----------------------------
 * dependencies 
 * - anonymous functions
 * - array dereferencing
 * Uses the DIRECTORY/CONTROLLER/METHOD to group your views
 * 
 * SAMPLE:
 *
 * With Directory
 * URL 1: example.com/dir/class/method
 * Template::load() will find the pages on views/pages/dir/class/method.php
 *
 * With no directory
 * URL 2: example.com/class/method
 * Template::load() will find the pages on views/pages/class/method.php
 *
 ****************************************************************************************************
 * ************************************************************************************************ *
 * * 1) Add This file on the libraries folder 													 * *
 * * 2) Load the file using $this->load->libraries('template') or add on the autoload.php config  * *
 * * 3) Setup the `template_path` and the `pages_path` (must be inside the views folder)          * *
 * * 4) You are good to go!!																		 * *
 * ************************************************************************************************ *
 ****************************************************************************************************
 *
 *	//load our main template
 *	Template::set('template','temp_main');
 *
 *	//minify data upon output
 *	//removes whitespaces and tab indents
 *	//default TRUE
 *	Template::set('minify',true);
 *
 *	//compress data upon output
 *	//gzip deflate
 *	//default TRUE
 *	Template::set('compress',true);
 *		
 *	// load CSS
 *	// add ^ on the beginning so that Template will ignore and not add a base URL
 *	Template::set('css',[
 *		'test/test/test',
 *		'^http://www.google.com', 					#add ^ on the first part for external links
 *		'^http://www.testing.com?getid=12?4434',	#add ^ on the first part for external links
 *		'^cxds.com',								#add ^ on the first part for external links
 *		'^path/test/123',							#add ^ on the first part for external links
 *	]);
 *
 *
 *	// load javascript links default javascript location at footer
 *	// add ^ on the beginning so that Template will ignore and not add a base URL
 *	Template::set('javascript',[
 *		'test/test/test',
 *		'^http://www.google.com', 					#add ^ on the first part for external links
 *		'^http://www.testing.com?getid=12?4434',	#add ^ on the first part for external links
 *		'^cxds.com',								#add ^ on the first part for external links
 *		'^path/test/123',							#add ^ on the first part for external links
 *	]);
 *		
 *	//javascript for the head
 *	Template::set('javascript',['test/test/test'],'head');
 *
 *	//partials
 *	Template::set('partials',[
 *		'spam_page' => 'foo/bar/spam',
 *		'eggs_page' => 'foo/bar/eggs',
 *	]);
 *
 *	//data for output to views
 *	//automatically sends the data to the partials also
 *	Template::set('data',[
 *		'data1' => 'value data 1',
 *		'data2' => 'value data 2',
 *		'data3' => 'value data 3',
 *		'data4' => ['eggs','orange','apples'],
 *	]);
 *
 *	//auto load the page
 *	//will look for the VIEWS/DIR/CLASS/METHOD.php or VIEWS/CLASS/METHOD.php
 *	// aUTO dETECT		
 *	Template::load();	
 *	//mANUAL LOAD A SPECIFIC FILE	
 *	Template::load('pages/for/not/tony');
 *
 * Variables to use on the Template Page:
 * - Load Javascript on the header
 * = $_TEMPLATE_JS_HEAD_ 
 * 
 * - Anonymous functions to access the partials data
 * = $_TEMPLATE_PARTIALS_('nonexistent',['name'=>'Jane Doe']);
 *
 * - Loads the Body of the page
 * = $_TEMPLATE_CONTENT_
 *	
 * - Loads the Footer javascript files	
 * = $_TEMPLATE_JS_FOOT_
 *
 * - Loads the CSS files	
 * = $_TEMPLATE_CSS_
 *
 * //Redirection with flashdata
 * Template::redirect('path/to/redirect','Message to be saved','key');						
 *
 * Automatically available in views data as $_FLASHDATA_key
 *
 * Template::redirect('path/to/redirect',array('test','tost'),'keyofFlash');
 * Automatically available in views data as array $_FLASHDATA_keyofFlash 
 */

class Template 
{	
	
	static  $TEMPLATE;
	private $_CI;
	private $template = false,
			$partials = array(),
			$data = array(),
			$_js_foot = '',
			$_js_head = '',
			$_css= '' ,
			$compress_output = false,
			$minify_output	 = false;

	private $template_path 		= 'templates';	//must be inside the views_path directory	
	private $pages_path 		= 'pages';		//must be inside the views_path directory

	public function __construct(){
		$this->_CI =& get_instance();
		if(!function_exists('site_url')){
			$this->_CI->load->helper('url');
		}

		SELF::$TEMPLATE = $this;
	}

	public static function __callstatic($name,$args)
	{
		if(method_exists(SELF::$TEMPLATE, '_'.$name))
		{
			switch ($name) 
			{
				case 'warning':
					return SELF::$TEMPLATE->{'_'.$name}($args);
				break;				
				default:
					SELF::$TEMPLATE->{'_'.$name}($args);
				break;
			}
		}
	}	

	private function _set($args)
	{
		if(!isset($args[0]))
		{
			// silently fail here
			// or what must we do?
		}else
		{
			$set_type = strtolower($args[0]);
			switch ($set_type) 
			{
				case 'template':
					$this->template = $args[1];
				break;
				case 'partials':
					$this->partials = $args[1];
				break;
				case 'javascript':
					$data = isset($args[1]) ? $args[1] : [];
					$location = isset($args[2]) ? $args[2] : null;
					$this->_javascript([$data,$location]);
				break;
				case 'css':
					$data = isset($args[1]) ? $args[1] : [];
					$this->_css([$data]);
				break;
				case 'data':
					if(count($this->data) >= 1)
					{
						$this->data = array_merge($this->data,$args[1]);						
					}else{
						$this->data = $args[1];	
					}
				break;
				case 'minify':
					$this->minify_output = isset($args[1]) && is_bool($args[1]) ? $args[1] : $this->minify_output;
				break;
				case 'compress':
					$this->compress_output = isset($args[1]) && is_bool($args[1]) ? $args[1] : $this->compress_output;
				break;
				default:
					//no options for this
				break;
			}
		}
	}

	/*
	 * @param1 string = head || foot
	 * @param2 array
	*/


	private function _javascript($args){
		$data = isset($args[0]) ? $args[0] : false;
		$location = isset($args[1]) ? $args[1] : 'foot';
		$use_var = in_array(strtolower($location),['head','top','above','header']) ? '_js_head' : '_js_foot';
		$template = '<script src="{{src}}" type="text/javascript"></script>';
		foreach($data as $k => $v){			
			$this->$use_var .= str_replace('{{src}}', strpos($v, '^') === false ? base_url($v) : substr($v, strpos($v, '^')+1) , $template);
		}
	}

	private function _css($args){
		$data = isset($args[0]) ? $args[0] : false;
		$template = '<link href="{{src}}" rel="stylesheet">';
		if($data){
			foreach($data as $k => $v){			
				$this->_css .= str_replace('{{src}}', strpos($v, '^') === false ? base_url($v) : substr($v, strpos($v, '^')+1) , $template);
			}
		}
	}

	private function _json($args){

		$arg = is_array($args) && isset($args[0]) ? $args[0] : $args;

		$CI = $this->_CI;
		
		$header = is_array($args) && isset($args[1]) ? $args[1] : false;

		if($header !== false){
			$CI->output->set_status_header($header);			
		}

		$CI->output
           ->set_content_type('application/json')
           ->set_output(json_encode($arg,true))
           ->_display();
		exit;
	}

	/*
	 * Load page view
	 * @param array  (data to be passed on the view)
	 * @param string (optional,if view is on a different path)

		  Template::load({
		 	'data1' => 'data',
		 	'data2' => 'value'
		  },'custom/path/to/view');
	*
	*/

	private function _flash($args){		
		// $this->_CI->load->library('session');
		// $varIable = isset($args[0]) ? $args[0] : 'system_message';
		// if($this->_CI->session->flashdata !== null){
		// 			var_dump($this->_CI->session->flashdata);exit;
		// }

		// return $this->_CI->session->flashdata($varIable);		
	}


	private function _redirect($args){
		$url = isset($args[0]) ? $args[0] : false;
		$msg = isset($args[1]) ? $args[1] : false;
		$var = isset($args[2]) ? $args[2] : 'system_message';

		$dataToSet[$var]=$msg;
		$this->_CI->session->set_flashdata($dataToSet);

		if($url === false)
		{
			//do nothing
		}else{
			redirect($url);
		}
	}	

	private function _load($args = array())
	{
		$CI = $this->_CI;

		if($CI->load->is_loaded('session'))
		{
			if($CI->session->get_flash_keys())
			{		
				$_flash_Data = array();		
				foreach ($CI->session->get_flash_keys() as $key => $flashDataKey) {
					$_flash_Data['_FLASHDATA_'.$flashDataKey] = $CI->session->flashdata($flashDataKey);
				}
			}else{
				$_flash_Data = array();
			}
		}else{
			$_flash_Data = array();
		}

		$this->data = array_merge($this->data,$_flash_Data);

		
		$_MTD = $CI->router->method;
		$_CLS = $CI->router->class;
		$_DIR = $CI->router->directory;

		if($_DIR == null)
		{
			$path = $this->pages_path.DIRECTORY_SEPARATOR.$_CLS.DIRECTORY_SEPARATOR.$_MTD;
		}else
		{
			$path = $this->pages_path.DIRECTORY_SEPARATOR.$_DIR.DIRECTORY_SEPARATOR.$_CLS.DIRECTORY_SEPARATOR.$_MTD;
		}

		if(is_array($args) && count($args) >= 1)
		{
			$load_view = isset($args[0]) && null !== $args[0] ? $args[0] : $path;
		}else
		{
			$load_view = $path;	
		}

		if($this->template === false)
		{
			//no template
			//load selected files
			$_output_HTML = $CI->load->view($load_view,$this->data,true);
		}else
		{
			if(count($this->partials) >= 1)
			{				
				$view_data = array_merge($this->data);
				$partial_data = $this->partials;

				$build['_TEMPLATE_PARTIALS_'] = function($partial_name = null ,array $more_data = array()) use ($view_data,$partial_data,$CI){
					if( isset($partial_data[$partial_name]) ){
						if(is_array($more_data) && count($more_data) >=1){
							$view_data = array_merge($view_data,$more_data);
						}
						return $CI->load->view($partial_data[$partial_name],$view_data,true);
					}						
				};				

				$this->data = array_merge($this->data,$build);				
			}else
			{
				$build['_TEMPLATE_PARTIALS_'] = function(){};
				$this->data = array_merge($this->data,$build);
			}

			
			$content_body['_TEMPLATE_CONTENT_'] = $CI->load->view($load_view,$this->data,true);
			$content_body['_TEMPLATE_JS_FOOT_'] = $this->_js_foot;
			$content_body['_TEMPLATE_JS_HEAD_'] = $this->_js_head;
			$content_body['_TEMPLATE_CSS_']		= $this->_css;
			$content_body = array_merge($content_body,$this->data);
			
			$_output_HTML = $CI->load->view($this->template_path.DIRECTORY_SEPARATOR.$this->template, $content_body, true);
		}

		$this->compress_display($_output_HTML);
	}

	private function compress_display($buffer)
	{
		if($this->minify_output === true)
		{
			ini_set("pcre.recursion_limit", "16777");

			$re = '%# Collapse whitespace everywhere but in blacklisted elements.
		        (?>             # Match all whitespans other than single space.
		          [^\S ]\s*     # Either one [\t\r\n\f\v] and zero or more ws,
		        | \s{2,}        # or two or more consecutive-any-whitespace.
		        ) # Note: The remaining regex consumes no text at all...
		        (?=             # Ensure we are not in a blacklist tag.
		          [^<]*+        # Either zero or more non-"<" {normal*}
		          (?:           # Begin {(special normal*)*} construct
		            <           # or a < starting a non-blacklist tag.
		            (?!/?(?:textarea|pre|script)\b)
		            [^<]*+      # more non-"<" {normal*}
		          )*+           # Finish "unrolling-the-loop"
		          (?:           # Begin alternation group.
		            <           # Either a blacklist start tag.
		            (?>textarea|pre|script)\b
		          | \z          # or end of file.
		          )             # End alternation group.
		        )  # If we made it here, we are not in a blacklist tag.
		        %Six';

		    $_OUTPUT_VIEW = preg_replace($re, " ", $buffer);

		    // We are going to check if processing has worked
			if ($_OUTPUT_VIEW === null)
			{
				$_OUTPUT_VIEW = $buffer;
			}
		}else{
			$_OUTPUT_VIEW = $buffer;
		}

		if($this->compress_output === true)
		{
			if($_SERVER['HTTP_ACCEPT_ENCODING'] !== '')
			{
				$server_accept_encoding = explode(',',str_replace(' ', '', $_SERVER['HTTP_ACCEPT_ENCODING']));
				
				$parsed_server_accept_encoding = array_map(function($x){
					return strtoupper($x);
				}, $server_accept_encoding);

				if(count($parsed_server_accept_encoding) >= 1)
				{
					if(in_array('GZIP',$parsed_server_accept_encoding))
					{
						ob_start("ob_gzhandler");
					}else if(in_array('DEFLATE', $parsed_server_accept_encoding))
					{
						if(function_exists('gzdeflate'))
						{
							$_OUTPUT_VIEW = gzdeflate($_OUTPUT_VIEW,ZLIB_ENCODING_DEFLATE);
						}else if(function_exists('gzcompress'))
						{
							$_OUTPUT_VIEW = gzcompress($_OUTPUT_VIEW,ZLIB_ENCODING_DEFLATE);
						}else
						{
							$_OUTPUT_VIEW = $_OUTPUT_VIEW;
						}
					}
				}
			}			
		}
		
		/*
			FIRE the output!!
		*/
		$CI = $this->_CI;
		$CI->output
           ->set_content_type('text/html')
           ->set_output($_OUTPUT_VIEW)
           ->_display();
		exit; //exit ?
	}
 }
