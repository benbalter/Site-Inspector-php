 can be run as:
 $inspector = new SiteInspector;

 $inspector->domain = 'ben.balter.com';
 $data = $inspector->inspect();
 
 or 
 
 $data = $inspector->inspect( 'ben.balter.com' );
 
 returns data as array
 
 individual datapoints can be accessed as property calls e.g. $inpsector->headers;