<?php
include('class-site-inspector.php'); 
$inspector = new SiteInspector;
?>
<!doctype html>
<html lang="en" class="no-js">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
	<title>Site Inspector</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="css/style.css?v=2">
</head>
<body>
	<div id="container">
		<header>
			<form id="domain">
				<label for="domain">Domain:</label> 
				http://<input type="text" name="domain" />
				<input type="submit" value="Lookup" />
			</form>
			<?php if ( !empty($_GET['domain'] ) ) { ?>

			<h1>Details for <?php echo $_GET['domain']; ?></h1>


						
		</header>

		<div id="main" role="main">
		
		
	<h2>Software</h2>	
		
		<?php $data = $inspector->inspect ( $_GET['domain'] ); print_r($data); ?>
		<ul>
			<li><div class="label">Status:</div> <?php echo ( $data['status'] ) ? 'Online' : 'Unnavailable'; ?></li>
			<li><div class="label">IPv6 Support:</div> <?php echo ( $data['ipv6'] ) ? 'Yes' : 'No'; ?></li>
			<li><div class="label">Non-WWW Support:</div>  <?php echo ( $data['nonwww'] ) ? 'Yes' : 'No'; ?></li>
			<li><div class="label">CDN:</div> <?php echo ( $data['cdn'] ) ? 'Yes' : 'No'; ?></li>
			<li><div class="label">Cloud:</div> <?php echo ( $data['cloud'] ) ? 'Yes' : 'No'; ?></li>
			<li><div class="label">Google Apps:</div> <?php echo ( $data['gapps'] ) ? 'Yes' : 'No'; ?></li>
			<li><div class="label">Server Software:</div> <?php echo $data['software']; ?></li>
			<li><div class="label">CMS:</div> <?php echo $data['cms']; ?></li>
		</ul>
	<h2>DNS Record</h2>
	
	<h3>Basic Record</h3>
	<?php //$records = dns_get_record($_GET['domain'],DNS_ANY, $authns, $addtl); format_records($records); ?>

	
	<h3>Name Servers</h3>
	<?php //format_records($authns); ?>
	<h3>Additional Records</h3>
	<?php //format_records($addtl); ?>
	<?php 	
	$hosts = gethostbynamel($_GET['domain']);
	if ($hosts) { ?>
		<h2>Reverse Lookup</h2>
		<table>
			<tr>
				<th>IP</th>
				<th>Hostname</th>
			</tr>
		<?php 
		
		foreach ($hosts as $host) { ?>
			<tr>
				<td><a href="http://www.bing.com/search?q=ip%3A<?php echo trim($host); ?>"><?php echo $host; ?></a></td>
				<td><?php echo gethostbyaddr($host); ?></td>
			</tr>
		<?php } ?>
		</table>
	<?php } ?>
<?php } else { ?>
	<h1>Site Inspector</h1>
</header>

<div id="main" role="main">
<em>Enter a domain to begin.</em>
<?php } ?>
		
		</div>
		
	</div>

		<footer>

		</footer>
	</div>

	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.5.1/jquery.min.js"></script>
	<script>!window.jQuery && document.write(unescape('%3Cscript src="js/libs/jquery-1.5.1.min.js"%3E%3C/script%3E'))</script>
	<script src="js/plugins.js"></script>
	<script src="js/script.js"></script>
</body>
</html>