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
	public $searches = array( 
	
				'cloud' => array( 
					'amazon'=>'amazon', 'rackspace' => 'rackspace'
				),

				'cdn' => array( 
					'Akamai' => 'akamai', 'Akamai (edgekey.net)' => 'edgekey.net', 'Akamai (akam.net)' => 'akam.net',
				),

				'cms' => array(
					'joomla', 'wordpress', 'drupal', 'xoops', 'mediawiki', 'php-nuke', 'typepad', 'moveable type', 'bbpress', 'blogger', 'sharepoint', 'zencart', 'phpbb', 'tumblr', 'liferay',
				),

				'analytics' => array(	
					'google-analytics', 'quantcast', 'disqus', 'GetSatisfaction', 'AdSense', 'AddThis',
				),

				'scripts' => array( 
					'prototype', 'jquery', 'mootools', 'dojo', 'scriptaculous',
				),
	
				'gapps' => array (
					'Google Docs' => 'ghs.google.com', 'GMail' => 'aspmx.l.google.com', 'GMail' => 'googlemail.com'
				),
	);
	
	//user agent to identify as
	public $ua = 'Site Inspector';
	
	//whether to follow location headers
	public $follow = 5;
		
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
		$this->data[ $name ] = $value;
	}
	
	/**
	 * Returns property from data array
	 * @since 0.1
	 * @param string $name data key
	 * @returns mixed the value requested
	 */
	function __get( $name ) {
	
		if ( array_key_exists($name, $this->data) )
            return $this->data[ $name ];
			
        $trace = debug_backtrace();
        trigger_error(
            'Undefined property via __get(): ' . $name .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE);
        return null;			
	}

	function check_apps( $body, $apps ) {
	
		//TO DO
		return array();
		
		/**
		 * Should Check inside script tags
		 * Should check external scripts
		 * Should check SRC Paths of all tags on page (within same domain)
		 *
		
		
		$output = array();
		
		foreach ($apps as $app) {
			if ( preg_match_all( '/<[^>]+' . $app. '[^>]+>/i', $body, $matches) != 0 )
				$output[] = $app;
		}
		
		return $output;
		*/
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
		$dns = $this->get_dns_record( $domain );
		
		//check for for CNAME or A record on non-www
		foreach ( $dns as $d ) {
		
			foreach ( $d as $record ) {
				 if ( isset( $record['type'] ) && ( $record['type'] == 'A' || $record['type'] == 'CNAME' ) )
					 return true;
			}
		
		}
		
		//if there's no non-www, subsequent actions should be taken on www. instead of the TLD.
		$this->domain = $this->maybe_add_www ( $domain );
		
		return false;

	}
	
	/**
	 * Loops through an array of needles to see if any are in the haystack
	 * @param array $needles array of needle strings
	 * @param array $haystack the haystack
	 * @returns string|bool needle if found, otherwise false
	 * @since 0.1
	 */
	function find_needles_in_haystack( $haystack, $key, $needle ) {	

		$needles = $this->searches[$needle];
					
		foreach ( $needles as $label => $n ) {

			if ( stripos( $haystack, $n ) !== FALSE ) {

				$this->data[$needle] = $label;	
				return;	
			}			
		}
			
		return false;
		
	}

	
	/**
	 * Checks for an AAAA record on a domain
	 * @since 0.1
	 * @param array $dns the DNS Records
	 * @returns bool true if ipv6, otherwise false
	 */
	function check_ipv6 ( $dns = '' ) {
		
		if ( $dns == '' ) 
			$dns = $this->get_dns_record();
	
		foreach ( $dns as $domain ) {
		
			foreach ($domain as $record) {
				if ( isset($record['type']) && $record['type'] == 'AAAA') {
					return true;
				}
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

		$domain =  $this->remove_http( $this->get_domain( $domain ) );
		
		if ( !isset( $this->data['dns'][ $domain ] ) )
			$this->data['dns'][ $domain ] = dns_get_record( $domain, DNS_ALL );

		return $this->dns[ $domain ];
	
	}
	
	/**
	 * Main function of the class; propegates data array
	 * @since 0.1
	 * @param string $domain domain to inspect
	 * @returns array data array
	 */
	function inspect ( $domain = '' ) {
	
		//cleanup public vars
		$this->body = '';
		$this->headers = '';
		$this->data = array();

		//set the public if an arg is passed
		if ( $domain != '' )
			$this->domain = $domain;

		//if we don't have a domain, kick
		if ( $this->domain == '') 
			return false;
			
		
		//cleanup domain
		$this->domain = strtolower( $this->domain );
		$this->domain = trim( $this->domain );
		$this->maybe_add_http( );
		$this->remove_www( );

		//check nonwww
		$this->nonwww = $this->check_nonwww( );
		
		//get DNS
		$this->get_dns_record( $this->domain );
		
		//IP & Host
		$this->ip = gethostbyname( $this->domain );
		foreach ( gethostbynamel( $this->remove_http( $this->domain ) ) as $ip ) 
			$this->data['hosts'][$ip] = gethostbyaddr( $ip );
		
		//grab the page
		$data = $this->remote_get( $this->domain );

		//if there was an error, kick
		if ( !$data ) {
			$this->status = 'unreachable';
			return false;
		} else {
			$this->status = 'live';
		}
		
		$this->body = $data['body'];
		$this->md5 = md5( $this->body );
		$this->headers = $data['headers'];

		if ( isset( $data['headers']['server'] ) ) {
			$this->server_software = $data['headers']['server'];
		} 
		
		//merge DNS and hosts from reverse DNS lookup
		$haystack = array_merge( $this->dns, $this->hosts );
		
		//IPv6
		$this->ipv6 = $this->check_ipv6( $this->dns );
		
		//check CDN
		array_walk_recursive( $haystack, array( &$this, 'find_needles_in_haystack'), 'cdn');
				
		//check cloud
		array_walk_recursive( $haystack, array( &$this, 'find_needles_in_haystack'), 'cloud');
		
		//check google apps 
		array_walk_recursive( $haystack, array( &$this, 'find_needles_in_haystack'), 'gapps');
		
		$this->cms = $this->check_apps( $body, $this->cms );
		$this->analytics = $this->check_apps( $body, $this->analytics );
		$this->scripts = $this->check_apps( $body, $this->scripts );
				
		asort( $this->data );
		
		return $this->data;
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
		
		$this->get_dns_record( $this->remove_trailing_slash( $domain ) );
						
		$args = array( 'redirection' => 0, 'user-agent' => $this->ua );
			
		$data = wp_remote_get( $domain , $args);

		//if there was an error, try to grab the headers to potentially follow a location header
		if ( is_wp_error( $data ) ) {
			$data = array( 'headers' => wp_remote_retrieve_headers( $domain ) );
			if ( is_wp_error( $data ) )
				return array();
		}
		
		$data = $this->maybe_follow_location_header ( $data );

		return $data;	
	}
	
	function maybe_follow_location_header ( $data ) {
		
		//check flag
		if ( !$this->follow )
			return $data;

		//if there's a location header, follow
		if ( !isset ( $data['headers']['location'] ) ) 
			return $data;
		
		//store the redirect 
		$this->data['redirect'][] = array( 'code' => wp_remote_retrieve_response_code( $data ), 'destination' => $data['headers']['location'] );
		
		if ( sizeof( $this->data['redirect'] ) < $this->follow )
			$data = $this->remote_get( $data['headers']['location'] );
	
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
	
	function remove_http ( $input ) {
	
		$domain = $this->get_domain( $input );
			
		//kill the http
		$domain = str_ireplace('http://', '', $domain);
		
		//if no domain arg was passed, update the class
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
	function maybe_add_www ( $input = '' ) {

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
	
	function remove_trailing_slash( $domain ) {
		
		if ( substr( $domain, -1, 1) == '/' )
			return substr( $domain, 0, -1);
			
		return $domain;
			
	}

}

?>