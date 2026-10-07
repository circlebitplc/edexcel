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
					<h4>TOPIC 2.3</h4>
					<h2>TRANSMISSION MEDIA</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
  <!-- ====================================================== -->
<!-- TOPIC 2.3 : TRANSMISSION MEDIA -->
<!-- ADD INSIDE <div class='slides'> -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Transmission Media <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
<font color=#00FBFF>Transmission Media</font>
is the method used to transfer data
from one device to another.
</p>

<br>

<p>
There are two main types:
</p>

<p>
Wired Communication
<br><br>
Wireless Communication
</p>

</section>

</section> 

<!-- ====================================================== -->
<!-- WIRED COMMUNICATION -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Wired Communication <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
<font color=#00FBFF>Wired Communication</font>
uses physical cables
to transfer data between devices.
</p>

<br>

<p>
Examples:
</p>

<p>
Twisted Pair Cable
<br>
Coaxial Cable
<br>
Fibre Optic Cable
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- TWISTED PAIR -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Twisted Pair Cable <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
A <font color=#00FBFF>Twisted Pair Cable</font>
contains pairs of copper wires
twisted together.
</p>

<br>

<p>
It is commonly used in:
</p>

<p>
LAN networks
<br>
Telephone systems
</p>

</section>

<section>

<h2>Features</h2>

<p>
Made using copper wires
<br><br>
Pairs of wires are twisted
<br><br>
Reduces interference
<br><br>
Cheap and flexible
</p>

</section>

<section>

<h2>Advantages</h2>

<p>
Low cost
<br><br>
Easy installation
<br><br>
Flexible cable
<br><br>
Widely available
</p>

</section>

<section>

<h2>Disadvantages</h2>

<p>
Lower speed compared to fibre optic
<br><br>
Signal interference possible
<br><br>
Limited transmission distance
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- COAXIAL -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Coaxial Cable <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
A <font color=#00FBFF>Coaxial Cable</font>
contains a central copper conductor
surrounded by insulation and shielding.
</p>

</section>

<section>

<h2>Features</h2>

<p>
Thicker than twisted pair cable
<br><br>
Better shielding
<br><br>
Less signal interference
<br><br>
Can carry signals over longer distances
</p>

</section>

<section>

<h2>Uses</h2>

<p>
Cable television
<br><br>
Internet connections
<br><br>
CCTV systems
</p>

</section>

<section>

<h2>Advantages</h2>

<p>
Better protection against interference
<br><br>
Higher bandwidth
<br><br>
Reliable communication
</p>

</section>

<section>

<h2>Disadvantages</h2>

<p>
More expensive
<br><br>
Less flexible
<br><br>
Harder to install
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- FIBRE OPTIC -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Fibre Optic Cable <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
A <font color=#00FBFF>Fibre Optic Cable</font>
uses light signals
to transfer data through thin glass fibres.
</p>

</section>

<section>

<h2>Features</h2>

<p>
Uses light instead of electricity
<br><br>
Very high bandwidth
<br><br>
Long-distance communication
<br><br>
Very thin glass fibres
</p>

</section>

<section>

<h2>Fast Data Transfer</h2>

<p>
Fibre optic cables provide:
</p>

<br>

<p>
Very high speed
<br><br>
Large data capacity
<br><br>
Efficient communication
</p>

</section>

<section>

<h2>Security Benefits</h2>

<p>
Fibre optic cables are:
</p>

<br>

<p>
Harder to tap
<br><br>
Less affected by interference
<br><br>
More secure for communication
</p>

</section>

<section>

<h2>Advantages</h2>

<p>
Very high speed
<br><br>
Long transmission distance
<br><br>
No electromagnetic interference
<br><br>
More secure
</p>

</section>

<section>

<h2>Disadvantages</h2>

<p>
Expensive installation
<br><br>
Fragile cables
<br><br>
Difficult maintenance
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- WIRELESS COMMUNICATION -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Wireless Communication <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
<font color=#00FBFF>Wireless Communication</font>
transfers data
without using physical cables.
</p>

<br>

<p>
It uses:
</p>

<p>
Radio waves
<br>
Infrared waves
<br>
Microwaves
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- WIFI -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Wi-Fi <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
<font color=#00FBFF>Wi-Fi</font>
is a wireless technology
used to connect devices to networks
and the Internet.
</p>

</section>

<section>

<h2>Features</h2>

<p>
Wireless connectivity
<br><br>
Uses radio signals
<br><br>
Supports multiple devices
<br><br>
Provides Internet access
</p>

</section>

<section>

<h2>Advantages</h2>

<p>
No cables required
<br><br>
Easy mobility
<br><br>
Simple installation
</p>

</section>

<section>

<h2>Disadvantages</h2>

<p>
Signal interference
<br><br>
Limited range
<br><br>
Security risks
<br><br>
Lower speed than wired networks
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- BLUETOOTH -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Bluetooth <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
<font color=#00FBFF>Bluetooth</font>
is a wireless technology
used for short-range communication
between devices.
</p>

</section>

<section>

<h2>Short-range Communication</h2>

<p>
Bluetooth works over short distances,
usually less than 10 meters.
</p>

</section>

<section>

<h2>Uses</h2>

<p>
Wireless headphones
<br><br>
Wireless keyboards
<br><br>
Mobile file transfer
<br><br>
Smart devices
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- INFRARED -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Infrared <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
<font color=#00FBFF>Infrared</font>
uses infrared light
to transfer data between devices.
</p>

</section>

<section>

<h2>Line-of-sight Communication</h2>

<p>
Infrared requires:
</p>

<br>

<p>
Direct line of sight
<br><br>
No physical obstacles
<br><br>
Short distance communication
</p>

</section>

<section>

<h2>Uses</h2>

<p>
TV remote controls
<br><br>
Air conditioner remotes
<br><br>
Short-range device communication
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- SATELLITE -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Satellite Communication <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
<font color=#00FBFF>Satellite Communication</font>
uses satellites in space
to transmit data over long distances.
</p>

</section>

<section>

<h2>Global Communication</h2>

<p>
Satellite communication allows:
</p>

<br>

<p>
Worldwide broadcasting
<br><br>
Global Internet access
<br><br>
Communication in remote areas
</p>

</section>

<section>

<h2>Advantages</h2>

<p>
Very large coverage area
<br><br>
Useful for remote locations
<br><br>
Supports global communication
</p>

</section>

<section>

<h2>Disadvantages</h2>

<p>
High cost
<br><br>
Signal delay possible
<br><br>
Affected by weather conditions
</p>

<br>

<svg width="500" height="220">

<circle cx="250" cy="60" r="30" fill="#00FBFF"/>

<ellipse cx="250" cy="180" rx="140" ry="40" fill="#d8ff00"/>

<line x1="250" y1="90" x2="170" y2="160" stroke="white" stroke-width="4"/>
<line x1="250" y1="90" x2="330" y2="160" stroke="white" stroke-width="4"/>

<text x="215" y="65" fill="black" font-size="18">SAT</text>
 
</svg>
 
</section>
 
</section>

<!-- ====================================================== -->
<!-- SUMMARY -->
<!-- ====================================================== -->

<section>

<h2>Summary</h2>

<p>
Twisted pair cables are cheap and flexible.
<br>
Coaxial cables provide better shielding.
<br>
Fibre optic cables provide very high speed.
<br>
Wi-Fi provides wireless Internet access.
<br>
Bluetooth supports short-range communication.
<br>
Infrared requires line-of-sight.
<br>
Satellites support global communication.
</p>

<br>
 
</section>
<section>
<a href="<?= $dirBase ?>/network_4.php">next</a>			
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

