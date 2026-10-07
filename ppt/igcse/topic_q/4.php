<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Chapter 4 – Digital Communications'>
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
					<h4>Chapter 4</h4>
					<h2>Digital Communications</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions</h3>
</section>

<section>
  <section><h4><b>1) </b>What does bandwidth measure?</h4></section>
  <section><p>Answer: B. Amount of data transferred per second</p></section>
</section>

<section>
  <section><h4><b>2) </b>Latency is:</h4></section>
  <section><p>Answer: B. The delay before data transfer</p></section>
</section>

<section>
  <section><h4><b>3) </b>What happens when the buffer becomes empty while streaming?</h4></section>
  <section><p>Answer: B. The video pauses</p></section>
</section>

<section>
  <section><h4><b>4) </b>A LAN covers:</h4></section>
  <section><p>Answer: B. A small geographical area</p></section>
</section>

<section>
  <section><h4><b>5) </b>A PAN is mainly used for:</h4></section>
  <section><p>Answer: B. Connecting personal devices nearby</p></section>
</section>

<section>
  <section><h4><b>6) </b>Tethering means:</h4></section>
  <section><p>Answer: B. Sharing mobile broadband with another device</p></section>
</section>

<section>
  <section><h4><b>7) </b>Which method uses radio waves?</h4></section>
  <section><p>Answer: C. Satellite communication</p></section>
</section>

<section>
  <section><h4><b>8) </b>Which is a disadvantage of satellite communication?</h4></section>
  <section><p>Answer: C. Weather interference</p></section>
</section>

<section>
  <section><h4><b>9) </b>Ethernet is used for:</h4></section>
  <section><p>Answer: C. Network communication</p></section>
</section>

<section>
  <section><h4><b>10) </b>Which is a wireless communication method?</h4></section>
  <section><p>Answer: C. Wi-Fi</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define bandwidth.</h4></section>
  <section><p>Bandwidth is the maximum amount of data that can be transmitted over a network connection per second, usually measured in Mbps or Gbps.</p></section>
</section>

<section>
  <section><h4><b>12) </b>Define latency.</h4></section>
  <section><p>Latency is the delay between sending data and it being received over a network.</p></section>
</section>

<section>
  <section><h4><b>13) </b>What is a buffer used for?</h4></section>
  <section><p>A buffer temporarily stores data before it is used, helping to prevent interruptions during streaming or data transfer.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Explain what streaming means.</h4></section>
  <section><p>Streaming is the process of transmitting media data continuously so it can be played while it is being downloaded.</p></section>
</section>

<section>
  <section><h4><b>15) </b>Name two factors that affect data transfer.</h4></section>
  <section><p>Bandwidth</p></section>
  <section><p>Latency</p></section>
</section>

<section>
  <section><h4><b>16) </b>What is a LAN?</h4></section>
  <section><p>A LAN (Local Area Network) is a network that connects devices within a small geographical area such as a school or office.</p></section>
</section>

<section>
  <section><h4><b>17) </b>What is the difference between PAN and WPAN?</h4></section>
  <section><p>A PAN connects personal devices using wired or wireless connections.</p></section>
  <section><p>A WPAN specifically uses wireless technologies such as Bluetooth.</p></section>
</section>

<section>
  <section><h4><b>18) </b>Explain tethering.</h4></section>
  <section><p>Tethering is the process of sharing a mobile phone’s internet connection with another device such as a laptop or tablet.</p></section>
</section>

<section>
  <section><h4><b>19) </b>Give one advantage of satellite communication.</h4></section>
  <section><p>Satellite communication can provide network access in remote or rural areas where cables are unavailable.</p></section>
</section>

<section>
  <section><h4><b>20) </b>Give one disadvantage of satellite communication.</h4></section>
  <section><p>Satellite communication can be affected by weather conditions such as heavy rain.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Difference between wired and wireless communication.</h4></section>
  <section><p>Wired communication uses physical cables such as Ethernet and is usually faster and more stable.</p></section>
  <section><p>Wireless communication uses radio waves such as Wi-Fi or Bluetooth, allowing mobility but with possible interference.</p></section>
</section>

<section>
  <section><h4><b>22) </b>How satellite communication works.</h4></section>
  <section><p>Data is transmitted from Earth to a satellite using radio waves.</p></section>
  <section><p>The satellite receives the signal and relays it back to another location on Earth.</p></section>
  <section><p>This allows communication over long distances.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Compare LANs and WANs.</h4></section>
  <section><p>LANs cover small areas and offer high speeds, commonly used in homes or schools.</p></section>
  <section><p>WANs cover large geographical areas and are slower, used to connect networks across cities or countries.</p></section>
</section>

<section>
  <section><h4><b>24) </b>How buffering helps when streaming videos.</h4></section>
  <section><p>Buffering stores part of the video in advance.</p></section>
  <section><p>If the internet connection slows down, playback continues from the buffer instead of stopping.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Three wired communication methods.</h4></section>
  <section><p>Ethernet is used for wired network connections.</p></section>
  <section><p>USB is used to transfer data between devices.</p></section>
  <section><p>HDMI is used to transmit audio and video signals to displays.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Streaming pauses during playback.</h4></section>
  <section><p>The buffer is not filling quickly enough due to low bandwidth or high latency.</p></section>
  <section><p>When the buffer becomes empty, the video pauses.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Best network type for a school.</h4></section>
  <section><p>The school should use wired connections.</p></section>
  <section><p>Wired networks provide higher speeds, lower latency, and greater reliability.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Effect of tethering on bandwidth.</h4></section>
  <section><p>The phone’s available bandwidth is shared between devices.</p></section>
  <section><p>This reduces bandwidth per device and can slow internet speeds.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Network for international offices.</h4></section>
  <section><p>A WAN is suitable.</p></section>
  <section><p>It connects networks over large geographical distances.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Satellite TV failure during heavy rain.</h4></section>
  <section><p>Heavy rain interferes with satellite radio signals.</p></section>
  <section><p>This weakens or blocks the signal, causing loss of communication.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Factors affecting digital communication quality.</h4></section>
  <section><p>Bandwidth determines how much data can be transferred per second.</p></section>
  <section><p>Latency affects transmission delay.</p></section>
  <section><p>Interference disrupts signals.</p></section>
  <section><p>Physical obstacles weaken wireless signals.</p></section>
  <section><p>Long distances reduce signal strength.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Comparison of communication methods.</h4></section>
  <section><p>Satellite communication covers wide areas but has high latency and weather interference.</p></section>
  <section><p>Broadcast communication sends data to many users but lacks privacy.</p></section>
  <section><p>Wired communication is fast and reliable but lacks mobility.</p></section>
  <section><p>Wireless communication allows mobility but is prone to interference.</p></section>
</section>

<section>
  <section><h4><b>33) </b>How devices form a PAN.</h4></section>
  <section><p>Devices connect using Bluetooth or Wi-Fi to form a PAN.</p></section>
  <section><p>Data flows wirelessly between nearby devices.</p></section>
  <section><p>The phone often acts as the central controlling device.</p></section>
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

