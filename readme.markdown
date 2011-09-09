PHP Class to provide information about a given domain.
 
Information Gathered
====================
* Server status (response code, if it is reachable, etc.)
* Non-WWW support (is www. required to access the site)
* IPv6 Support (is it reachable via next generation technology)
* CDN Provider (do they use a content distribution network, if so what)
* CMS (do they use a content management system, if so what)
* Cloud Provider (are they hosted in the cloud, if so by whom)
* Analytics Source (do they track visitors, if so how)
* Script Library (do they use common javascript libraries)
* HTTPs Support (is the site browsable via the secure HTTPS protocol)
 
Usage
=====
 
can be run as:
$inspector = new SiteInspector;

$inspector->domain = 'ben.balter.com';
$data = $inspector->inspect();
 
or 
 
$data = $inspector->inspect( 'ben.balter.com' );
 
returns data as array
 
individual datapoints can be accessed as property calls e.g. $inspector->headers;

Files
======
* **class-site-inspector.php** - the site inspector class
* **index.php** - is a simple front end for the class
 
Requirements
===========

Relies on WordPress for transportation layer and caching, but can easily be ported.