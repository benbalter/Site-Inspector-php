<?php

class SiteInspector {
	
	public $oss = array( 
					'apache',
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
					
	public $ua = '';

	function __construct() {
	
	}


	function check_apps( $body, $apps ) {
		$output = array();
		
		foreach ($apps as $app) {
			if ( preg_match_all( '/<[^>]+' . $app. '[^>]+>/i', $body, $matches) != 0 )
				$output[] = $app;
		}
		
		return $output;
	}

	function format_records($records) { ?>
		<table>
			<tr>
				<th>Host</th>
				<th>Class</th>
				<th>Type</th>
				<th>TTL</th>
				<th>Additional Info</th>
			</tr>
		<?php
		foreach ($records as $record) { ?>
			<tr>
				<td><?php echo $record['host']; ?></td>
				<td><?php echo $record['class']; ?></td>
				<td><?php echo $record['type']; ?></td>
				<td><?php echo $record['ttl']; ?></td>
				<td>
				<?php 
					unset($record['host'], $record['class'], $record['type'], $record['ttl']);
					foreach ($record as $field=>$value) {
						echo "<strong>$field:</strong> $value<br />";	
					}
				?>
				</td>
			</tr>
		<?php } ?>
		</table>
	<?php } 


	function check_nonwww( $domain ) {
	
		//strip WWW just in case
		$domain = str_ireplace('www.', '', $domain);

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

	function inspect ( $domain ) {
		
		//setup output array
		$output['domain'] = $domain;
		$output['status'] = '1';
		$output['ipv6'] = 0;
		
		//check nonwww
		$output['nonwww'] = check_nonwww( $domain );
		
		//get DNS
		$dns = dns_get_record( $domain ,DNS_ANY, $authns, $addtl);
		
		//IPv6
		$output['ipv6'] = check_ipv6( $dns );
		
		//IP & Host
		$ip =  gethostbynamel( $domain );
		$output['ip'] = $ip[0];
		@ $output['host'] = gethostbyaddr( $output['ip'] );
		
		//check CDN
		$output['cdn'] = check_string( $output['host'], $this->cdn );
		
		//check cloud
		if ( $output['cdn'] == 0 )
			$output['cloud'] = check_string( $output['host'], $this->cloud );
		
		//check google apps 
		$output['gapps'] = check_gapps ( $dns, $addtl );
		
		//Curl the page
		$data = wp_remote_get( 'http://' . $domain, array('user-agent' => $this->ua ) );

		//verify the domain exists
		if ( is_wp_error( $data ) ) {
			$output['status'] = 0;
		} else {
		
			$body = wp_remote_retrieve_body( $data );
			
			//mark server
			if ( isset( $data['headers']['server'] ) ) {
				$output['software'] = $data['headers']['server'];
				$output['opensource'] = check_string( $output['software'], $this->oss );
			} 
		
			$output['cms'] = check_apps( $body, $this->cms );
			$output['analytics'] = check_apps( $body, $this->analytics );
			$output['scripts'] = check_apps( $body, $this->scripts );
			
		}
		
		return $output;
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