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
					<h4>TOPIC 2.2</h4>
					<h2>NETWORK HARDWARE</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <!-- ====================================================== -->
<!-- TOPIC 2.2 : NETWORK HARDWARE -->
<!-- ADD INSIDE <div class='slides'> -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Network Hardware <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
<font color=#00FBFF>Network Hardware</font>
refers to the physical devices used
to connect computers and other devices together
in a network.
</p>

<br>

<p>
Examples:
</p>

<p>
NIC<br>
Switch<br>
Router<br>
Modem<br>
WAP<br>
Servers
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- NIC -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Network Interface Card (NIC) <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Network Interface Card (NIC)</font>
is a hardware component that allows a computer
to connect to a network.
</p>

<br>

<p>
Every device connected to a network
must have a NIC.
</p>

</section>

<section>

<h1>Purpose of NIC</h1>

<p>
A NIC is used to:
</p>

<br>

<p>
Connect devices to networks
<br><br>
Send data
<br><br>
Receive data
<br><br>
Provide a unique MAC address
</p>

</section>

<section>

<h1>Wired NIC</h1>

<p>
A <font color=#00FBFF>Wired NIC</font>
uses cables such as Ethernet cables
to connect devices to a network.
</p>

<br>

<p>
Advantages:
</p>

<p>
Stable connection
<br>
Faster speed
<br>
More secure
</p>

</section>

<section>

<h1>Wireless NIC</h1>

<p>
A <font color=#00FBFF>Wireless NIC</font>
connects devices to a network
using Wi-Fi signals.
</p>

<br>

<p>
Advantages:
</p>

<p>
No cables required
<br>
Portable
<br>
Easy connectivity
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- SWITCH -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Switch <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Switch</font>
is a networking device used to connect
multiple devices together in a LAN.
</p>

</section>

<section>

<h1>Purpose of a Switch</h1>

<p>
A switch:
</p>

<br>

<p>
Connects computers together
<br><br>
Transfers data efficiently
<br><br>
Reduces unnecessary traffic
<br><br>
Uses MAC addresses
</p>

</section>

<section>

<h1>Data Transfer Between Devices</h1>

<p>
A switch receives data from one device
and sends it directly to the correct destination device.
</p>

<br>

<p>
This improves:
</p>

<p>
Network speed
<br>
Efficiency
<br>
Performance
</p>

</section>
<section>

<svg width="450" height="220">

<rect x="160" y="90" width="120" height="60" fill="#00FBFF"/>

<text x="190" y="125" fill="black" font-size="20">SWITCH</text>

<circle cx="60" cy="60" r="30" fill="#FF0004"/>
<circle cx="60" cy="180" r="30" fill="#d8ff00"/>
<circle cx="380" cy="60" r="30" fill="#ffffff"/>
<circle cx="380" cy="180" r="30" fill="#ff9900"/>

<line x1="90" y1="60" x2="160" y2="105" stroke="white" stroke-width="4"/>
<line x1="90" y1="180" x2="160" y2="135" stroke="white" stroke-width="4"/>
<line x1="280" y1="105" x2="350" y2="60" stroke="white" stroke-width="4"/>
<line x1="280" y1="135" x2="350" y2="180" stroke="white" stroke-width="4"/>

</svg>
</section>
</section>

</section>

<!-- ====================================================== -->
<!-- ROUTER -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Router <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Router</font>
is a networking device that connects
different networks together.
</p>

</section>

<section>

<h1>Purpose of Router</h1>

<p>
A router:
</p>

<br>

<p>
Routes data packets
<br><br>
Connects LANs together
<br><br>
Provides Internet access
<br><br>
Chooses the best path for data
</p>

</section>

<section>

<h1>Connecting Networks</h1>

<p>
Routers are used to connect:
</p>

<br>

<p>
Home networks
<br>
School networks
<br>
Office networks
<br>
Wide Area Networks
</p>

</section>

<section>

<h1>Internet Connectivity</h1>

<p>
Routers allow devices in a network
to access the Internet.
</p>

<br>

<p>
They forward data
between local networks and the Internet.
</p>

<br>

<svg width="500" height="220">

<circle cx="90" cy="110" r="35" fill="#00FBFF"/>
<rect x="190" y="80" width="120" height="60" fill="#FF0004"/>
<circle cx="420" cy="110" r="45" fill="#d8ff00"/>

<text x="225" y="117" fill="white" font-size="20">ROUTER</text>
<text x="390" y="118" fill="black" font-size="18">Internet</text>

<line x1="125" y1="110" x2="190" y2="110" stroke="white" stroke-width="5"/>
<line x1="310" y1="110" x2="375" y2="110" stroke="white" stroke-width="5"/>

</svg>

</section>

</section>

<!-- ====================================================== -->
<!-- MODEM -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Modem <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Modem</font>
is a device that converts
digital signals into analogue signals
and analogue signals into digital signals.
</p>

</section>

<section>

<h1>Purpose of Modem</h1>

<p>
A modem is used to:
</p>

<br>

<p>
Connect to Internet Service Providers (ISP)
<br><br>
Transmit data through telephone or cable lines
<br><br>
Convert signals for communication
</p>

</section>

<section>

<h1>Digital ↔ Analogue Conversion</h1>

<p>
Computers use digital signals,
but communication lines may use analogue signals.
</p>

<br>

<p>
The modem performs:
</p>

<p>
Modulation
<br><br>
Demodulation
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- WAP -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Wireless Access Point (WAP) <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Wireless Access Point (WAP)</font>
allows wireless devices
to connect to a wired network using Wi-Fi.
</p>

</section>

<section>

<h1>Wi-Fi Connectivity</h1>

<p>
WAP provides:
</p>

<br>

<p>
Wireless Internet access
<br><br>
Mobility for users
<br><br>
Easy wireless networking
</p>

</section>

<section>

<h1>Wireless Communication</h1>

<p>
Devices communicate with the WAP
using radio waves instead of cables.
</p>

<br>

<p>
Examples:
</p>

<p>
Laptops
<br>
Smartphones
<br>
Tablets
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- SERVERS -->
<!-- ====================================================== -->

<section>

<section>
	<h1>Servers <font color=#FF0004>?</font></h1>
</section>

<section>

<p>
A <font color=#00FBFF>Server</font>
is a powerful computer
that provides services and resources
to other computers on a network.
</p>

</section>

<section>

<h1>File Server</h1>

<p>
A <font color=#00FBFF>File Server</font>
stores and manages files for users on a network.
</p>

<br>

<p>
Advantages:
</p>

<p>
Centralized storage
<br>
Easy file sharing
<br>
Backup management
</p>

</section>

<section>

<h1>Print Server</h1>

<p>
A <font color=#00FBFF>Print Server</font>
manages printers connected to a network.
</p>

<br>

<p>
It allows multiple users
to share the same printer.
</p>

</section>

<section>

<h1>Web Server</h1>

<p>
A <font color=#00FBFF>Web Server</font>
stores and delivers web pages
to users through the Internet.
</p>

<br>

<p>
Examples:
</p>

<p>
Websites
<br>
Online applications
<br>
E-learning systems
</p>

</section>

<section>

<h1>Mail Server</h1>

<p>
A <font color=#00FBFF>Mail Server</font>
handles sending,
receiving,
and storing emails.
</p>

<br>

<p>
Functions:
</p>

<p>
Email storage
<br>
Email delivery
<br>
User authentication
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- SUMMARY -->
<!-- ====================================================== -->

<section>

<h1>Summary</h1>

<p>
NIC connects devices to networks.
<br> 
Switches transfer data inside LANs.
<br> 
Routers connect networks together.
<br> 
Modems convert digital and analogue signals.
<br> 
WAP provides wireless connectivity.
<br> 
Servers provide network services.
</p>
 
</section>
 <section>
<a href="<?= $dirBase ?>/network_3.php">next</a>
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

