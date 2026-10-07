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
					<h4>TOPIC 2.4</h4>
					<h2>NETWORK TOPOLOGIES </h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <!-- ====================================================== -->
<!-- TOPIC 2.4 : NETWORK TOPOLOGIES -->
<!-- FULL VERSION -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Network Topologies <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
A <font color=#00FBFF>Network Topology</font>
is the arrangement of devices,
connections,
and communication paths
in a computer network.
</p>
 
<p>
Common Network Topologies:
</p>

<p>
Star Topology
<br><br>
Bus Topology
<br><br>
Ring Topology
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- STAR TOPOLOGY -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Star Topology <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
In a <font color=#00FBFF>Star Topology</font>,
all devices are connected
to a central device
such as a switch or hub.
</p>

<br>

<svg width="700" height="420">

<!-- center switch -->
<rect x="300" y="170" width="100" height="70"
fill="#0c223f"
stroke="white"
stroke-width="4"/>

<text x="323" y="212" fill="white" font-size="22">SWITCH</text>

<!-- pc 1 -->
<rect x="60" y="30" width="70" height="50" fill="none" stroke="white" stroke-width="3"/>
<rect x="85" y="80" width="20" height="15" fill="white"/>
<line x1="95" y1="95" x2="95" y2="150" stroke="white" stroke-width="3"/>
<line x1="95" y1="150" x2="300" y2="185" stroke="white" stroke-width="3"/>

<!-- pc 2 -->
<rect x="300" y="20" width="70" height="50" fill="none" stroke="white" stroke-width="3"/>
<rect x="325" y="70" width="20" height="15" fill="white"/>
<line x1="335" y1="85" x2="335" y2="170" stroke="white" stroke-width="3"/>

<!-- pc 3 -->
<rect x="540" y="30" width="70" height="50" fill="none" stroke="white" stroke-width="3"/>
<rect x="565" y="80" width="20" height="15" fill="white"/>
<line x1="575" y1="95" x2="575" y2="150" stroke="white" stroke-width="3"/>
<line x1="575" y1="150" x2="400" y2="185" stroke="white" stroke-width="3"/>

<!-- pc 4 -->
<rect x="80" y="320" width="70" height="50" fill="none" stroke="white" stroke-width="3"/>
<rect x="105" y="370" width="20" height="15" fill="white"/>
<line x1="115" y1="320" x2="300" y2="225" stroke="white" stroke-width="3"/>

<!-- pc 5 -->
<rect x="520" y="320" width="70" height="50" fill="none" stroke="white" stroke-width="3"/>
<rect x="545" y="370" width="20" height="15" fill="white"/>
<line x1="520" y1="320" x2="400" y2="225" stroke="white" stroke-width="3"/>

</svg>

</section>

<section>

<h2>Structure</h2>

<p>
All computers and devices
are connected
to one central device.
</p>

<br>

<p>
The central device controls
communication between devices.
</p>

</section>

<section>

<h2>Advantages of Star Topology</h2>
</section><section>
<p>
<font color=#00FBFF>Easy Installation</font>
</p> 
<p>
Devices can be added or removed easily.
</p>
</section><section> 
<p>
<font color=#00FBFF>Easy Fault Detection</font>
</p> 
<p>
Network problems can be identified quickly.
</p>
</section><section>
<p>
<font color=#00FBFF>Failure Isolation</font>
</p> 
<p>
Failure of one cable
does not affect the whole network.
</p>
	</section><section>
<p>
<font color=#00FBFF>High Performance</font>
</p>

<p>
Switches manage traffic efficiently.
</p>
</section><section>
</section>

<section>

<h2>Disadvantages of Star Topology</h2>
</section><section>
<p>
<font color=#FF0004>Higher Cost</font>
</p>
<p>
Requires more cables
and networking devices.
</p>
</section><section>
	<p>
<font color=#FF0004>Central Device Dependency</font>
</p>

<p>
If the switch fails,
the entire network stops.
</p></section><section>
<p>
<font color=#FF0004>More Hardware Needed</font>
</p>

<p>
Switches or hubs increase installation cost.
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- BUS TOPOLOGY -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Bus Topology <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
In a <font color=#00FBFF>Bus Topology</font>,
all devices are connected
to one main cable
called the backbone cable.
</p>

<br>

<svg width="720" height="360">

<!-- backbone -->
<line x1="20" y1="180" x2="700" y2="180"
stroke="#1f355c"
stroke-width="16"/>

<!-- pc1 -->
<rect x="90" y="20" width="55" height="45"
fill="none"
stroke="white"
stroke-width="3"/>

<line x1="118" y1="65" x2="118" y2="180"
stroke="white"
stroke-width="3"/>

<!-- pc2 -->
<rect x="290" y="20" width="55" height="45"
fill="none"
stroke="white"
stroke-width="3"/>

<line x1="318" y1="65" x2="318" y2="180"
stroke="white"
stroke-width="3"/>

<!-- pc3 -->
<rect x="490" y="20" width="55" height="45"
fill="none"
stroke="white"
stroke-width="3"/>

<line x1="518" y1="65" x2="518" y2="180"
stroke="white"
stroke-width="3"/>

<!-- pc4 -->
<rect x="140" y="250" width="55" height="45"
fill="none"
stroke="white"
stroke-width="3"/>

<line x1="168" y1="180" x2="168" y2="250"
stroke="white"
stroke-width="3"/>

<!-- pc5 -->
<rect x="340" y="250" width="55" height="45"
fill="none"
stroke="white"
stroke-width="3"/>

<line x1="368" y1="180" x2="368" y2="250"
stroke="white"
stroke-width="3"/>

<!-- pc6 -->
<rect x="540" y="250" width="55" height="45"
fill="none"
stroke="white"
stroke-width="3"/>

<line x1="568" y1="180" x2="568" y2="250"
stroke="white"
stroke-width="3"/>

</svg>

</section>

<section>

<h2>Structure</h2>

<p>
All devices share
one communication cable.
</p>

<br>

<p>
Data travels through
the backbone cable.
</p>

</section>

<section>

<h2>Advantages of Bus Topology</h2>
</section><section>
<p>
<font color=#00FBFF>Low Cost</font>
</p>

<p>
Requires less cable
than other topologies.
</p>
</section><section>
<p>
<font color=#00FBFF>Easy Installation</font>
</p>

<p>
Simple structure
makes setup easy.
</p>
</section><section>
<p>
<font color=#00FBFF>Suitable for Small Networks</font>
</p>

<p>
Works well for temporary
or small office networks.
</p>

</section>

<section>

<h2>Disadvantages of Bus Topology</h2>
</section><section>
<p>
<font color=#FF0004>Backbone Failure</font>
</p>

<p>
Failure of the main cable
stops the entire network.
</p>
</section><section>
<p>
<font color=#FF0004>Difficult Troubleshooting</font>
</p>

<p>
Finding faults
can be difficult.
</p>
</section><section>
<p>
<font color=#FF0004>Performance Issues</font>
</p>

<p>
Network speed decreases
when many devices are connected.
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- RING TOPOLOGY -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Ring Topology <font color=#FF0004>?</font></h2>
</section>

<section>

<p>
In a <font color=#00FBFF>Ring Topology</font>,
each device connects
to two other devices,
forming a circular structure.
</p>

<br>

<svg width="650" height="420">

<!-- ring -->
<circle cx="320" cy="200" r="140"
fill="none"
stroke="white"
stroke-width="5"/>

<!-- pcs -->

<rect x="285" y="20" width="70" height="50"
fill="none"
stroke="white"
stroke-width="3"/>

<rect x="520" y="140" width="70" height="50"
fill="none"
stroke="white"
stroke-width="3"/>

<rect x="430" y="320" width="70" height="50"
fill="none"
stroke="white"
stroke-width="3"/>

<rect x="140" y="320" width="70" height="50"
fill="none"
stroke="white"
stroke-width="3"/>

<rect x="50" y="140" width="70" height="50"
fill="none"
stroke="white"
stroke-width="3"/>

</svg>

</section>

<section>

<h2>Structure</h2>

<p>
Each device connects
to the next device
forming a ring.
</p>

<br>

<p>
Data travels around the ring
in one direction.
</p>

</section>

<section>

<h2>Advantages of Ring Topology</h2>
</section><section>
<p>
<font color=#00FBFF>Reduced Data Collisions</font>
</p>

<p>
Data flows in one direction.
</p>
</section><section>
<p>
<font color=#00FBFF>Equal Access</font>
</p>

<p>
Every device gets equal opportunity
to send data.
</p>
</section><section>
<p>
<font color=#00FBFF>Good Under Heavy Traffic</font>
</p>

<p>
Performs efficiently
when network traffic is high.
</p>

</section>

<section>

<h2>Disadvantages of Ring Topology</h2>
</section><section>
<p>
<font color=#FF0004>Device Failure Problem</font>
</p>

<p>
Failure of one device
can affect the whole network.
</p>
</section><section>
<p>
<font color=#FF0004>Difficult Maintenance</font>
</p>

<p>
Troubleshooting can be complicated.
</p>
</section><section>
<p>
<font color=#FF0004>Network Disruption</font>
</p>

<p>
Adding or removing devices
may interrupt the network.
</p>

</section>

</section>

<!-- ====================================================== -->
<!-- COMPARISON -->
<!-- ====================================================== -->

<section>

<section>
	<h2>Comparison of Topologies</h2>
</section>

<section>

<table style="width:100%; font-size:26px;">

<tr>
<th>Topology</th>
<th>Main Feature</th>
<th>Main Problem</th>
</tr>

<tr>
<td>Star</td>
<td>Central Device</td>
<td>Switch failure affects network</td>
</tr>

<tr>
<td>Bus</td>
<td>Single Backbone Cable</td>
<td>Backbone failure affects network</td>
</tr>

<tr>
<td>Ring</td>
<td>Circular Connection</td>
<td>One device failure affects network</td>
</tr>

</table>

</section>

</section>

<!-- ====================================================== -->
<!-- SUMMARY -->
<!-- ====================================================== -->

<section>

<h2>Summary</h2>

<p>
Star topology uses a central device.
<br><br>
Bus topology uses a backbone cable.
<br><br>
Ring topology forms a circle.
<br><br>
Each topology has advantages
and disadvantages.
</p> 
</section>
<section>
			<a href="<?= $dirBase ?>/network_5.php">next</a>
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

