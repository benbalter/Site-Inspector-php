<?php

/*
 can be run as:
 $inspector = new SiteInspector;

 $inspector->domain = 'ben.balter.com';
 $data = $inspector->inspect();
 
 or 
 
 $data = $inspector->inspect( 'ben.balter.com' );
 
*/

class SiteInspector {
	
	static $instance;

	
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
					
	public $ua = 'Site Inspector';
	
	public $domain = '';
	public $data = null;

	function __construct() {
		self::$instance = $this;
	}

	function __set( $name, $value ) {
		$this->data[$name] = $value;
	}
	
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

	function check_nonwww( $domain ) {
	
		$dns = dns_get_record($domain, DNS_ANY);
		foreach ( $dns as $record ) {
			 if ( isset( $record['type'] ) && ( $record['type'] == 'A' || $record['type'] == 'CNAME' ) )
				 return 1;
		}
		
		return 0;

	}

	function check_string ( $haystack, $needles ) {
		
		foreach ( $needles as $needle ) {

			if ( stripos( $haystack, $needle ) !== FALSE )
				return 1;
			
		}

		return 0;
	}

	function check_ipv6 ( $dns ) {

		foreach ($dns as $record) {
			if ( isset($record['type']) && $record['type'] == 'AAAA') {
			
				return 1;
			}
		}
		
		return 0;

	}

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
		
		//setup output array
		$output['domain'] = $domain;
		$output['status'] = '1';
		$output['ipv6'] = 0;
		
		//check nonwww
		$output['nonwww'] = $this->check_nonwww( $domain );
		
		//get DNS
		$dns = dns_get_record( $domain ,DNS_ANY, $authns, $addtl);
		
		//IPv6
		$output['ipv6'] = $this->check_ipv6( $dns );
		
		//IP & Host
		$ip =  gethostbynamel( $domain );
		$output['ip'] = $ip[0];
		@ $output['host'] = gethostbyaddr( $output['ip'] );
		
		//check CDN
		$output['cdn'] = $this->check_string( $output['host'], $this->cdn );
		
		//check cloud
		if ( $output['cdn'] == 0 )
			$output['cloud'] = $this->check_string( $output['host'], $this->cloud );
		
		//check google apps 
		$output['gapps'] = $this->check_gapps ( $dns, $addtl );
		
		//grab the page
		$data = $this->remote_get( $this->domain );
		
		//if there was an error, kick
		if ( !$data )
			return false;
		
		$this->data['body'] = $data['body'];
		$this->data['headers'] = $data['headers'];
	
			
			//mark server
			if ( isset( $data['headers']['server'] ) ) {
				$this->data['software'] = $data['headers']['server'];
				$this->data['opensource'] = check_string( $output['software'], $this->oss );
			} 
		
			$this->data['cms'] = check_apps( $body, $this->cms );
			$this->data['analytics'] = check_apps( $body, $this->analytics );
			$this->data['scripts'] = check_apps( $body, $this->scripts );
				
		return $output;
	}
	
	function remote_get( $domain ) {
		
		//prefer WP's HTTP API
		if ( function_exists( 'wp_remote_get') ) {
			
			$data = wp_remote_get( $this->domain , array('user-agent' => $this->ua ) );

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
	
	function maybe_add_http( $input = '' ) {
		
		//allow arg to be optional
		if ( $input == '' ) 
			$domain = $this->domain;
		else
			$domain = $input;
		
		$domain = ( substr( $domain, 0, 7) == 'http://' ) ? $domain : 'http://' . $domain;
		
		//if no domain was passed, asume we should update the class
		if ( $input == '' )
			$this->domain = $domain;
			
		return $domain;
		
	}
	
	function remove_www( $input = '' ) {
		
		//allow domain to be optional
		if ( $input == '' )
			$domain = $this->domain;
		else 
			$domain = $input;
			
		//force http so check will work
		$domain = $this->maybe_add_http( $domain );
	
		//kill the www
		$domain = str_ireplace('http://www.', 'http://', $domain);
		
		//if no domain arg was passed, update the class
		if ( $input == '' )
			$this->domain = $domain;
		
		return $domain;
		
	}
	

	function check_gapps ( $dns, $additional ) {

		foreach ($dns as $k=> $record) {
			
			if ( isset($record['type']) && $record['type'] == 'MX') {

				if ( stripos( $additional[$k]['host'], 'google') !== FALSE)		
					return 1;
			}
		
		}
		
		return 0;
		
	}

}

?>