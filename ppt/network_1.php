<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='CONNECTIVITY (NETWORKS)'>
	<meta name='author' content='Enidu Batuwanthudawe'>

	<meta name='apple-mobile-web-app-capable' content='yes' />
	<meta name='apple-mobile-web-app-status-bar-style' content='black-translucent' />

	<meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no'>

	<link rel='stylesheet' href='<?= $pptBase ?>/css/reveal.min.css'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/theme/default.css' id='theme'>
	<link rel='stylesheet' href='<?= $pptBase ?>/css/custom.css'>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

	<!-- For syntax highlighting -->
	<link rel='stylesheet' href='<?= $pptBase ?>/lib/css/zenburn.css'>

	<!-- If the query includes 'print-pdf', use the PDF print sheet -->
	<?php if (isset($_GET['print-pdf'])): ?>
	<link rel="stylesheet" href="<?= $pptBase ?>/css/print/pdf.css">
	<?php endif; ?>

		<!--[if lt IE 9]>
		<script src='<?= $pptBase ?>/lib/js/html5shiv.js'></script>
		<![endif]-->
		<script src='<?= $pptBase ?>/js/moment.min.js'></script>
	</head>

	<body>

		<div class='reveal'>
			<div class='slides'>
				<section>
					<h4>TOPIC 2</h4>
					<h2> CONNECTIVITY (NETWORKS)</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<!-- ====================================================== -->
<!-- TOPIC 2 : CONNECTIVITY (NETWORKS) -->
<!-- ADD THIS INSIDE <div class='slides'> -->
<!-- ====================================================== -->

<section>
 
	<h2> Connectivity (Networks)</h2>
	<p>
		 by <a href='http://www.hodamapanthiya.com/enidu/' target="_blank">Enidu Batuwanthudawe </a>
		 <font size="3" color="white">
		 	C&nbsp;&nbsp;i&nbsp;&nbsp;v&nbsp;&nbsp;i&nbsp;&nbsp;l&nbsp;&nbsp;&nbsp;&nbsp;
		 	E&nbsp;&nbsp;n&nbsp;&nbsp;g&nbsp;&nbsp;.&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|
		 	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
		 	B&nbsp;&nbsp;.&nbsp;&nbsp;S&nbsp;&nbsp;c&nbsp;&nbsp;.&nbsp;&nbsp;&nbsp;&nbsp;
		 	i&nbsp;&nbsp;n&nbsp;&nbsp;&nbsp;&nbsp;I&nbsp;&nbsp;T
		 </font>
	</p>

	<br>

	<svg width="450" height="220">

	<circle cx="80" cy="100" r="35" fill="#00FBFF"/>
	<circle cx="220" cy="50" r="35" fill="#FF0004"/>
	<circle cx="360" cy="100" r="35" fill="#d8ff00"/>
	<circle cx="220" cy="170" r="35" fill="#ffffff"/>

	<line x1="115" y1="100" x2="185" y2="50" stroke="white" stroke-width="5"/>
	<line x1="255" y1="50" x2="325" y2="100" stroke="white" stroke-width="5"/>
	<line x1="115" y1="100" x2="185" y2="170" stroke="white" stroke-width="5"/>
	<line x1="255" y1="170" x2="325" y2="100" stroke="white" stroke-width="5"/>

	</svg>

</section>
<section>
	<section>
	<h2>Network</h2>
	</section>
	<section>
	<p>Two or more digital devices connected together using wired or wireless connections to share resources is called a network.</p>
	</section>
	<section>

<p>
A computer network is a collection of interconnected devices
that can communicate and share resources with each other.
</p>

<br>

<p>
Networks allow:
</p>

<p>
<font color=#00FBFF>
File sharing
<br>
Internet access
<br>
Printer sharing
<br>
Communication
</font>
</p>

</section>
	</section>
<!-- ====================================================== -->
<!-- TYPES OF NETWORKS -->
<!-- ====================================================== -->
<section>
	<section><h2>Types of Networks <font color=#FF0004>?</font></h2></section>
	<section>
	<p>PAN</p>
	<p>LAN</p>
	<p>WAN</p>
	</section>
</section>



</section>
<!-- ====================================================== -->
<!-- PAN -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Personal Area Network (PAN) <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
A <font color=#00FBFF>Personal Area Network (PAN)</font>
is a network that connects digital devices
within a very small geographical area around one person.
</p>

<br>

<p>
Examples:
</p>

<p>
Mobile Phone<br>
Laptop<br>
Smart Watch<br>
Bluetooth Earbuds
</p>

</section>

<section>

<h2>Characteristics of PAN</h2>

<p>
<font color=#00FBFF>Very small geographical area</font>
</p>

<p>
PAN normally covers a few meters around a person.
</p>

<br>

<p>
<font color=#00FBFF>Wireless communication</font>
</p>

<p>
Usually uses Bluetooth, Wi-Fi, or USB connections.
</p>

<br>

<p>
<font color=#00FBFF>Personal ownership</font>
</p>

<p>
Normally managed and used by one person.
</p>

</section>

<section>

<h2>Advantages of PAN</h2>

<p>
Easy device connection
<br><br>
Portable and convenient
<br><br>
Low cost
<br><br>
Wireless communication
</p>

</section>

<section>

<h2>Disadvantages of PAN</h2>

<p>
Very limited range
<br><br>
Lower speed compared to LAN
<br><br>
Security risks possible
<br><br>
Limited number of connected devices
</p>

</section>

<section>

<h2>Examples of PAN use</h2>

<p>
Connecting a phone to Bluetooth earbuds
<br><br>
Smart watch connected to a mobile phone
<br><br>
Laptop connected to a wireless mouse
<br><br>
Phone hotspot connection
</p>

<br>

<svg width="420" height="200">

<circle cx="205" cy="100" r="35" fill="#00FBFF"/>

<rect x="40" y="40" width="60" height="40" fill="#FF0004"/>
<rect x="320" y="40" width="60" height="40" fill="#d8ff00"/>
<rect x="170" y="150" width="70" height="35" fill="white"/>

<line x1="100" y1="60" x2="205" y2="100" stroke="white" stroke-width="4"/>
<line x1="320" y1="60" x2="205" y2="100" stroke="white" stroke-width="4"/>
<line x1="205" y1="135" x2="205" y2="150" stroke="white" stroke-width="4"/>

</svg>

</section>

</section>
<!-- ====================================================== -->
<!-- LAN -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Local Area Network (LAN) <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
A <font color=#00FBFF>Local Area Network (LAN)</font>
is a network that connects computers and devices
within a small geographical area.
</p>

<br>

<p>
Examples:
</p>

<p>
School<br>
Office<br>
Home<br>
Computer Laboratory
</p>

</section>

<section>

<h2>Characteristics of LAN</h2>

<p>
<font color=#00FBFF>Small geographical area</font>
</p>

<p>
LAN normally covers one building or campus.
</p>

<br>

<p>
<font color=#00FBFF>High Speed</font>
</p>

<p>
Data transfer speed is very high.
</p>

<br>

<p>
<font color=#00FBFF>Private ownership</font>
</p>

<p>
Usually managed by one organization.
</p>

</section>

<section>

<h2>Advantages of LAN</h2>

<p>
Fast communication
<br><br>
Easy resource sharing
<br><br>
Low communication cost
<br><br>
Centralized data management
</p>

</section>

<section>

<h2>Disadvantages of LAN</h2>

<p>
Limited coverage area
<br><br>
Requires maintenance
<br><br>
Security risks possible
<br><br>
Initial setup cost
</p>

</section>

<section>

<h2>Examples of LAN use</h2>

<p>
School computer labs
<br><br>
Office buildings
<br><br>
Home Wi-Fi networks
<br><br>
Internet cafés
</p>

<br>

<svg width="420" height="200">

<rect x="30" y="60" width="70" height="50" fill="#00FBFF"/>
<rect x="170" y="60" width="70" height="50" fill="#FF0004"/>
<rect x="310" y="60" width="70" height="50" fill="#d8ff00"/>

<circle cx="205" cy="160" r="30" fill="white"/>

<line x1="65" y1="110" x2="205" y2="160" stroke="white" stroke-width="4"/>
<line x1="205" y1="110" x2="205" y2="160" stroke="white" stroke-width="4"/>
<line x1="345" y1="110" x2="205" y2="160" stroke="white" stroke-width="4"/>

</svg>

</section>

</section>

<!-- ====================================================== -->
<!-- WAN -->
<!-- ====================================================== -->

<section>
<section>
	<h2>Wide Area Network (WAN) <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
A <font color=#00FBFF>Wide Area Network (WAN)</font>
is a network that connects computers over
large geographical areas.
</p>

<br>

<p>
Examples:
</p>

<p>
Countries
<br>
Cities
<br>
Continents
</p>

</section>

<section>

<h2>Characteristics of WAN</h2>

<p>
Large geographical coverage
<br><br>
Lower speed than LAN
<br><br>
Uses public communication links
<br><br>
Managed by multiple organizations
</p>

</section>

<section>

<h2>Advantages of WAN</h2>

<p>
Worldwide communication
<br><br>
Remote access
<br><br>
Supports online services
<br><br>
Centralized business systems
</p>

</section>

<section>

<h2>Disadvantages of WAN</h2>

<p>
High setup cost
<br><br>
Security concerns
<br><br>
Complex maintenance
<br><br>
Lower speed than LAN
</p>

</section>

<section>

<h2>Examples of WAN use</h2>

<p>
The Internet
<br><br>
Banking systems
<br><br>
Airline reservation systems
<br><br>
Government communication systems
</p>

<br>

<svg width="500" height="220">

<circle cx="80" cy="110" r="35" fill="#00FBFF"/>
<circle cx="250" cy="50" r="35" fill="#FF0004"/>
<circle cx="420" cy="110" r="35" fill="#d8ff00"/>

<line x1="115" y1="110" x2="215" y2="50" stroke="white" stroke-width="5"/>
<line x1="285" y1="50" x2="385" y2="110" stroke="white" stroke-width="5"/>

<text x="45" y="180" fill="white" font-size="20">City A</text>
<text x="215" y="20" fill="white" font-size="20">City B</text>
<text x="385" y="180" fill="white" font-size="20">City C</text>

</svg>

</section>

</section>

<!-- ====================================================== -->
<!-- COMPARISON -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Comparison Between LAN and WAN</h2>
</section>

<section>

<h2>Geographical Coverage</h2>

<p>
<font color=#00FBFF>LAN</font>
covers small areas such as buildings.
</p>

<br>

<p>
<font color=#d8ff00>WAN</font>
covers large areas such as countries.
</p>

</section>

<section>

<h2>Ownership</h2>

<p>
<font color=#00FBFF>LAN</font>
is privately owned.
</p>

<br>

<p>
<font color=#d8ff00>WAN</font>
is shared between organizations.
</p>

</section>

<section>

<h2>Speed</h2>

<p>
<font color=#00FBFF>LAN</font>
has very high speed.
</p>

<br>

<p>
<font color=#d8ff00>WAN</font>
has lower speed because of long-distance communication.
</p>

</section>

<section>

<h2>Cost</h2>

<p>
<font color=#00FBFF>LAN</font>
has lower setup cost.
</p>

<br>

<p>
<font color=#d8ff00>WAN</font>
has higher setup and maintenance cost.
</p>

</section>

<section>

<h2>Security</h2>

<p>
<font color=#00FBFF>LAN</font>
is easier to secure.
</p>

<br>

<p>
<font color=#d8ff00>WAN</font>
has greater security risks.
</p>

</section>

<section>

<h2>Reliability</h2>

<p>
<font color=#00FBFF>LAN</font>
is more reliable because it is locally managed.
</p>

<br>

<p>
<font color=#d8ff00>WAN</font>
depends on communication providers.
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- SUMMARY -->
<!-- ====================================================== -->

<section>

<h2>Summary</h2>

<p>
LAN is used for small areas.
<br><br>
WAN is used for large areas.
<br><br>
LAN is faster and cheaper.
<br><br>
WAN supports global communication.
</p>

<br>

<h2>
<a href="<?= $dirBase ?>/network_2.php">Next</a>
</h2>

</section>
 
  


			</div>
		</div>

		<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
		<script src="<?= $pptBase ?>/js/reveal.min.js"></script>

		<script>

			// Full list of configuration options available here:
			// https://github.com/hakimel/reveal.js#configuration
			Reveal.initialize({
				controls: true,
				progress: true,
				history: true,
				center: true,

				theme: Reveal.getQueryHash().theme, // available themes are in /css/theme
				transition: Reveal.getQueryHash().transition || 'default', // default/cube/page/concave/zoom/linear/fade/none

				// Parallax scrolling
				// parallaxBackgroundImage: 'https://s3.amazonaws.com/hakim-static/reveal-js/reveal-parallax-1.jpg',
				// parallaxBackgroundSize: '2100px 900px',

				// Optional libraries used to extend on reveal.js
				dependencies: [
					{ src: '<?= $pptBase ?>/lib/js/classList.js', condition: function() { return !document.body.classList; } },
					{ src: '<?= $pptBase ?>/plugin/markdown/marked.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/markdown/markdown.js', condition: function() { return !!document.querySelector( '[data-markdown]' ); } },
					{ src: '<?= $pptBase ?>/plugin/highlight/highlight.js', async: true, callback: function() { hljs.initHighlightingOnLoad(); } },
					{ src: '<?= $pptBase ?>/plugin/zoom-js/zoom.js', async: true, condition: function() { return !!document.body.classList; } },
					
				]
			});

		</script>

	</body>
</html>

