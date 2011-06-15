<?php
/**
 * Site Inspector Class
 *
 * @author Benjamin J. Blater
 * @version 0.1
 * @pacakge siteinspector
 * @license GPL2
 */

 class SiteInspector {
	
	static $instance;
	
	//defaults to look for; can be overriden by user
	public $oss = array( 
					'apache', 'nginx'
					);

	public $cloud = array( 
					'amazon',
					);

	public $cdn = array( 
					'akamai',
					);

	public $cms = array(
					'joomla', 'wordpress', 'drupal', 'xoops', 'mediawiki', 'php-nuke', 'typepad', 'moveable type', 'bbpress', 'blogger', 'sharepoint', 'zencart', 'phpbb', 'tumblr', 'liferay',
					);

	public $analytics = array(	
					'google-analytics', 'quantcast', 'disqus', 'GetSatisfaction', 'AdSense', 'AddThis',
					);

	public $scripts = array( 
					'prototype', 'jquery', 'mootools', 'dojo', 'scriptaculous',
					);
	
	//user agent to identify as
	public $ua = 'Site Inspector';
	
	public $domain = '';
	public $data = null;

	/**
	 * Initiates the class
	 * @since 0.1
	 */
	function __construct() {
		self::$instance = $this;
	}

	/**
	 * Allows user to overload data array
	 * @since 0.1
	 * @param string $name data key
	 * @param mixed $value data value 
	 */
	function __set( $name, $value ) {
		$this->data[$name] = $value;
	}
	
	/**
	 * Returns property from data array
	 * @since 0.1
	 * @param string $name data key
	 * @returns mixed the value requested
	 */
	function __get( $name ) {
		if (array_key_exists($name, $this->data))
            return $this->data[$name];
			
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;			
	}

	function check_apps( $body, $apps ) {
		$output = array();
		
		foreach ($apps as $app) {
			if ( preg_match_all( '/<[^>]+' . $app. '[^>]+>/i', $body, $matches) != 0 )
				$output[] = $app;
		}
		
		return $output;
	}

	/**
	 * Checks a domain to see if there's a CNAME or A record on the non-www domain
	 * 
	 * Updates $this->domain to www. if there's no non-www support
	 * @since 0.1
	 * @param string $domain the domain
	 * @return bool true if non-www works, otherwise false
	 */
	function check_nonwww( $domain  = '' ) {
	
		$domain = $this->get_domain( $domain );
	
		//grab the DNS
		$dns = dns_get_record($domain, DNS_ANY);
		
		//check for for CNAME or A record on non-www
		foreach ( $dns as $record ) {
			 if ( isset( $record['type'] ) && ( $record['type'] == 'A' || $record['type'] == 'CNAME' ) )
				 return true;
		}
		
		//if there's no non-www, subsequent actions should be taken on www. instead of the TLD.
		$this->domain = $this->add_www ( $domain );
		
		return false;

	}
	
	/**
	 * Loops through an array of needles to see if any are in the haystack
	 * @param array $needles array of needle strings
	 * @param string $haystack the haystack
	 * @returns string|bool needle if found, otherwise false
	 * @since 0.1
	 */
	function find_needles_in_haystack( $needles, $haystack ) {
		
		foreach ( $needles as $needle ) {

			if ( stripos( $haystack, $needle ) !== FALSE )
				return $needle;
			
		}

		return false;
	}

	function check_ipv6 ( $dns ) {

		foreach ($dns as $record) {
			if ( isset($record['type']) && $record['type'] == 'AAAA') {
			
				return 1;
			}
		}
		
		return 0;

	}
	
	/**
	 * Checks DNS records for reference to google apps
	 * @since 0.1
	 * @param array $dns DNS returned from dns_get_record
	 */
	function check_gapps ( $dns = '', $additional = '') {
		
		if ( $dns == '' ) 
			$dns = get_dns_record();
		
		if ( $additional == '' ) {
			get_dns_record();
			$additional = $this->data['dns']['addtl'];
		}
		
		foreach ($dns as $k=> $record) {
			
			if ( isset($record['type']) && $record['type'] == 'MX') {

				if ( stripos( $additional[$k]['host'], 'google') !== FALSE)		
					return true;
			}
		
		}
		
		return false;
		
	}

	/**
	 * Helper function to allow domain arguments to be optional
	 *
	 * If domain is passed as an arg, will return that, otherwise will check $this->domain for the domain
	 * @since 0.1
	 * @param string $domain the domain
	 * @returns string the true domain
	 */
	function get_domain( $domain ) {
		
		if ( $domain != '' )
			return $domain;
		
		if ( $this->domain == '' )
			die('No Domain Supplied.');

		return $this->domain;
		
	}
	
	/**
	 * Retrieves DNS record and caches to $this->data
	 * @param string $domain the domain
	 * @returns array dns data
	 * @since 0.1
	 */
	function get_dns_record( $domain  = '' ) {
	
		$domain = $this->get_domain( $domain );
	
		if ( !isset ( $this->data['dns'] ) )
			$this->data['dns'] = dns_get_record( 	$domain, 
													DNS_ALL, 
													$this->data['dns']['ns'], 
													$this->data['dns']['addtl']
												);
		
		return $this->data['dns'];
	
	}
	
	/**
	 * Main function of the class; propegates data array
	 * @since 0.1
	 * @param string $domain domain to inspect
	 * @returns array data array
	 */
	function inspect ( $domain = '' ) {
	
		//set the public if an arg is passed
		if ( $domain != '' )
			$this->$domain = $domain;
	
		//if we don't have a domain, kick
		if ( $this->domain == '') 
			return false;
			
		//cleanup domain
		$this->maybe_add_http( );
		$this->remove_www( );
		
		//cleanup public vars
		$this->body = '';
		$this->headers = '';
		$this->data = array();
		
		//check nonwww
		$this->data['nonwww'] = $this->check_nonwww( );
		
		//get DNS
		$dns = dns_get_record( $domain ,DNS_ANY, $authns, $addtl);
		
		//IPv6
		$this->data['ipv6'] = $this->check_ipv6( $dns );
		
		//IP & Host
		$ip =  gethostbynamel( $domain );
		$this->data['ip'] = $ip[0];
		@ $this->data['host'] = gethostbyaddr( $this->data['ip'] );
		
		//check CDN
		$this->data['cdn'] = $this->find_needles_in_haystack( $this->cdn,  $this->data['host']);
		
		//check cloud
		if ( $this->data['cdn'] == 0 )
			$this->data['cloud'] = $this->find_needles_in_haystack( $this->cloud, $this->data['host'] );
		
		//check google apps 
		$this->data['gapps'] = $this->check_gapps ( $this->dns, $this->data['dns']['addtl'] );
		
		//grab the page
		$data = $this->remote_get( $this->domain );
		
		//if there was an error, kick
		if ( !$data ) {
			$this->data['status'] = 'unreachable';
			return false;
		}
		
		$this->data['body'] = $data['body'];
		$this->data['headers'] = $data['headers'];
				
			if ( isset( $data['headers']['server'] ) ) {
				$this->data['server_software'] = $data['headers']['server'];
			} 
		
			$this->data['cms'] = check_apps( $body, $this->cms );
			$this->data['analytics'] = check_apps( $body, $this->analytics );
			$this->data['scripts'] = check_apps( $body, $this->scripts );
				
		return $output;
	}
	
	/**
	 * Smart remote get function
	 *  
	 * Prefers wp_remote_get, but falls back to file_get_contents
	 * @param $domain string site to retrieve
	 * @returns array assoc. array of page data
	 * @since 0.1
	 */
	function remote_get( $domain = '' ) {
		
		$domain = $this->get_domain( $domain );
		
		//prefer WP's HTTP API
		if ( function_exists( 'wp_remote_get') ) {
			
			$data = wp_remote_get( $domain , array('user-agent' => $this->ua ) );

			//verify the domain exists
			if ( is_wp_error( $data ) )
				return false;
		
			return $data;
		
		}
		
		//non WP fallback
		
		//grab body
		$data['body'] = file_get_contents( $this->domain );
		
		//if fopen failed for some reason, kick
		if ( $data['body'] == false )
			return false;
	
		//grab the headers
		$data['headers'] = get_headers ( $this->domain );
	
		return $data;
	
	}
	
	/**
	 * Conditionally prepends http:// to a string
	 * @since 0.1
	 * @param string $input domain to modify
	 * @returns string modified domain
	 */
	function maybe_add_http( $input = '' ) {
		
		$domain = $this->get_domain( $input );
		
		$domain = ( substr( $domain, 0, 7) == 'http://' ) ? $domain : 'http://' . $domain;
		
		//if no domain was passed, asume we should update the class
		if ( $input == '' )
			$this->domain = $domain;
			
		return $domain;
		
	}
	
	/**
	 * Removes www from domains
	 * @since 0.1
	 * @param string $input domain
	 * @returns string domain with www removed
	 */
	function remove_www( $input = '' ) {
		
		$domain = $this->get_domain( $input );
			
		//force http so check will work
		$domain = $this->maybe_add_http( $domain );
	
		//kill the www
		$domain = str_ireplace('http://www.', 'http://', $domain);
		
		//if no domain arg was passed, update the class
		if ( $input == '' )
			$this->domain = $domain;
		
		return $domain;
		
	}
	
	/**
	 * Conditionally adds www to a domain
	 * @since 0.1
	 * @param string $input the domain
	 * @returns string the domain with www.
	 */
	function add_www ( $input = '' ) {

		$domain = $this->get_domain( $input );

		//force http so check will work
		$domain = $this->maybe_add_http( $domain );
		
		//check if it's already there
		if ( strpos( $domain, 'http://www.' ) !== FALSE )
			return $domain;
		
		//add the www
		$domain = str_ireplace('http://', 'http://www.', $domain);
		
		//if no domain arg was passed, update the class
		if ( $input == '' )
			$this->domain = $domain;
		
		return $domain;
	}

}

?>