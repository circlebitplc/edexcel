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

	<meta name='description' content='Chapter 5 – Networks'>
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
					<h4>Chapter 5</h4>
					<h2>Networks</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions</h3>
</section>

<section>
  <section><h4><b>1) </b>What does a DHCP do?</h4></section>
  <section><p>Answer: B. Assigns IP addresses</p></section>
</section>

<section>
  <section><h4><b>2) </b>IPv6 uses:</h4></section>
  <section><p>Answer: C. 8 groups of 4 hexadecimals</p></section>
</section>

<section>
  <section><h4><b>3) </b>A MAC address is:</h4></section>
  <section><p>Answer: C. Fixed to the device</p></section>
</section>

<section>
  <section><h4><b>4) </b>Which device connects LANs to WANs?</h4></section>
  <section><p>Answer: B. Router</p></section>
</section>

<section>
  <section><h4><b>5) </b>A server that handles user logins is a:</h4></section>
  <section><p>Answer: C. Authentication server</p></section>
</section>

<section>
  <section><h4><b>6) </b>A booster is used to:</h4></section>
  <section><p>Answer: B. Amplify network signals</p></section>
</section>

<section>
  <section><h4><b>7) </b>A peer to peer network:</h4></section>
  <section><p>Answer: B. Shares resources directly between devices</p></section>
</section>

<section>
  <section><h4><b>8) </b>Encryption scrambles data so:</h4></section>
  <section><p>Answer: B. Unauthorized users cannot understand it</p></section>
</section>

<section>
  <section><h4><b>9) </b>WPA is more secure than WEP because:</h4></section>
  <section><p>Answer: B. Every device and packet has a unique key</p></section>
</section>

<section>
  <section><h4><b>10) </b>VPNs allow:</h4></section>
  <section><p>Answer: C. Remote access to LANs</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>What is an IP address?</h4></section>
  <section><p>An IP address is a unique numerical identifier assigned to a device on a network that allows it to be located and communicate with other devices.</p></section>
</section>

<section>
  <section><h4><b>12) </b>Define a MAC address.</h4></section>
  <section><p>A MAC address is a unique hardware address permanently assigned to a network interface card by the manufacturer to identify a device on a network.</p></section>
</section>

<section>
  <section><h4><b>13) </b>State one difference between IPv4 and IPv6.</h4></section>
  <section><p>IPv4 uses 32-bit addresses, while IPv6 uses 128-bit addresses, allowing many more unique addresses.</p></section>
</section>

<section>
  <section><h4><b>14) </b>What is the purpose of a router?</h4></section>
  <section><p>A router directs data packets between different networks and connects a local area network to the internet.</p></section>
</section>

<section>
  <section><h4><b>15) </b>What does a Wireless Access Point do?</h4></section>
  <section><p>A wireless access point allows wireless devices to connect to a wired network using Wi-Fi signals.</p></section>
</section>

<section>
  <section><h4><b>16) </b>Give one benefit of a LAN.</h4></section>
  <section><p>A LAN allows users to share resources such as files and printers efficiently within a small area.</p></section>
</section>

<section>
  <section><h4><b>17) </b>What is authentication?</h4></section>
  <section><p>Authentication is the process of verifying a user’s identity before allowing access to a system or network.</p></section>
</section>

<section>
  <section><h4><b>18) </b>Define encryption.</h4></section>
  <section><p>Encryption is the process of converting data into an unreadable form so that only authorised users with a key can access it.</p></section>
</section>

<section>
  <section><h4><b>19) </b>What is a transaction log used for?</h4></section>
  <section><p>A transaction log records all changes made to a database so data can be recovered after a system failure.</p></section>
</section>

<section>
  <section><h4><b>20) </b>What is a VPN?</h4></section>
  <section><p>A VPN is a secure connection that allows users to access a private network remotely over the internet using encryption.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Difference between static and dynamic IP addressing.</h4></section>
  <section><p>A static IP address is permanently assigned to a device and does not change.</p></section>
  <section><p>A dynamic IP address is assigned automatically by DHCP and may change each time the device connects.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Functions of network components.</h4></section>
  <section><p>A switch connects devices within a LAN and forwards data only to the intended device using MAC addresses.</p></section>
  <section><p>A gateway connects networks that use different protocols and translates data between them.</p></section>
  <section><p>A server provides services such as file storage, authentication, or printing to client devices.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Compare peer-to-peer and client–server networks.</h4></section>
  <section><p>In a peer-to-peer network, devices share resources directly without a central server.</p></section>
  <section><p>In a client–server network, a central server controls access, security, and resource management.</p></section>
  <section><p>Client–server networks are more secure and scalable.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Public key encryption and security.</h4></section>
  <section><p>Public key encryption uses a public key to encrypt data.</p></section>
  <section><p>A private key is used to decrypt the data.</p></section>
  <section><p>It is secure because the private key is never shared.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>25) </b>Three methods used to secure a network.</h4></section>
  <section><p>Firewalls block unauthorised network traffic.</p></section>
  <section><p>Encryption protects data during transmission.</p></section>
  <section><p>Authentication ensures only authorised users can access the network.</p></section>
</section>

<section>
  <section><h4><b>26) </b>Mall Wi-Fi time limit issue.</h4></section>
  <section><p>The Wi-Fi system records the device’s MAC address.</p></section>
  <section><p>Once the time limit expires, the MAC address is blocked.</p></section>
  <section><p>Reconnecting does not work because the MAC address is recognised.</p></section>
</section>

<section>
  <section><h4><b>27) </b>How a VPN helps remote workers.</h4></section>
  <section><p>A VPN encrypts data sent between remote workers and the company network.</p></section>
  <section><p>It creates a secure tunnel over the internet.</p></section>
  <section><p>This prevents hackers from intercepting sensitive data.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Tracking print usage.</h4></section>
  <section><p>A print server should be used.</p></section>
  <section><p>It records which user sends print jobs and controls printer access.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Weak Wi-Fi upstairs.</h4></section>
  <section><p>A Wi-Fi booster or repeater should be installed.</p></section>
  <section><p>It amplifies the wireless signal and improves coverage.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Whitelist vs blacklist filtering.</h4></section>
  <section><p>Blacklist filtering blocks specific harmful websites.</p></section>
  <section><p>Whitelist filtering allows only approved websites and blocks all others.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Device identification on a network.</h4></section>
  <section><p>An IP address identifies a device’s location for communication.</p></section>
  <section><p>A MAC address uniquely identifies the physical hardware.</p></section>
  <section><p>A device name provides a human-readable label.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Network components working together.</h4></section>
  <section><p>Cables transmit data between wired devices.</p></section>
  <section><p>Wireless access points provide Wi-Fi connectivity.</p></section>
  <section><p>Routers connect networks and provide internet access.</p></section>
  <section><p>Switches connect devices within a LAN.</p></section>
  <section><p>Boosters extend signal range.</p></section>
  <section><p>Servers manage files, authentication, and printing.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Symmetric vs public key encryption.</h4></section>
  <section><p>Symmetric encryption uses the same key for encryption and decryption.</p></section>
  <section><p>It is fast but less secure if the key is intercepted.</p></section>
  <section><p>Public key encryption uses two keys.</p></section>
  <section><p>It is more secure but slower.</p></section>
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

