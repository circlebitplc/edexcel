<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Mock 6'>
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
					<h4>Mock 6</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section> 

    <!-- 2(a) -->
    <section>
        <section>
            <h4><b>2(a)</b> Which device directs data between networks? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Router</p>
        </section>
    </section>

    <!-- 2(b)(i) -->
    <section>
        <section>
            <h4><b>2(b)(i)</b> State two methods used to uniquely identify devices on the network. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• IP address</p>
            <p>• MAC address</p>
        </section>
    </section>

    <!-- 2(b)(ii) -->
    <section>
        <section>
            <h4><b>2(b)(ii)</b> Explain why devices need to be uniquely identified. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Devices need unique addresses so that data packets can be sent to the correct destination device on the network.</p>
            <p>Without unique identification, data could be delivered incorrectly, causing communication errors and network problems.</p>
        </section>
    </section>

    <!-- 2(c) -->
    <section>
        <section>
            <h4><b>2(c)</b> Explain two differences between a PAN and a WAN. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Difference 1:</b></p>
            <p>A PAN (Personal Area Network) covers a very small area around one user, such as Bluetooth devices used by an astronaut.</p>
            <p>A WAN (Wide Area Network) covers a very large geographical area, such as communication between spacecraft and mission control on Earth.</p>

            <p><b>Difference 2:</b></p>
            <p>A PAN normally uses short-range wireless technologies like Bluetooth.</p>
            <p>A WAN uses long-distance communication infrastructure such as satellites, fibre-optic cables, and routers.</p>
        </section>
    </section>

    <!-- 2(d) -->
    <section>
        <section>
            <h4><b>2(d)</b> Identify any two components shown in the network diagram. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Server</p>
            <p>• Wireless access point</p>
            <p>(Other valid answers: router, firewall, switch, workstation)</p>
        </section>
    </section>

    <!-- 2(e) -->
    <section>
        <section>
            <h4><b>2(e)</b> Explain one advantage of satellite communication. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Satellite communication allows data to be transmitted across extremely long distances, making it possible for spacecraft in space to communicate with mission control on Earth in real time.</p>
        </section>
    </section>

    <!-- 2(f) -->
    <section>
        <section>
            <h4><b>2(f)</b> Which layer of the TCP/IP model uses IP addresses? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Internet layer</p>
        </section>
    </section>

    <!-- 2(g) -->
    <section>
        <section>
            <h4><b>2(g)</b> Explain one cause of transmission delay in satellite communication. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Transmission delay occurs because signals must travel extremely long distances between Earth, satellites, and spacecraft.</p>
            <p>This increases the time taken for data packets to reach their destination, causing higher latency.</p>
        </section>
    </section>

    <!-- 2(h) -->
    <section>
        <section>
            <h4><b>2(h)</b> Describe latency. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Latency is the delay between data being transmitted and the response being received across a communication network.</p>
        </section>
    </section>

    <!-- 2(i) -->
    <section>
        <section>
            <h4><b>2(i)</b> Explain one benefit of network security. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Network security protects spacecraft systems and mission data from unauthorized access, malware, and cyberattacks.</p>
            <p>This helps maintain system reliability and protects sensitive information.</p>
        </section>
    </section>

    <!-- 2(j) -->
    <section>
        <section>
            <h4><b>2(j)</b> Explain one reason for backups. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Backups are created so that important mission data can be restored if files are lost due to hardware failure, accidental deletion, corruption, or cyberattacks.</p>
        </section>
    </section>

    <!-- 2(k) -->
    <section>
        <section>
            <h4><b>2(k)</b> Explain what is meant by bandwidth and how it affects data transmission. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Bandwidth is the maximum amount of data that can be transmitted across a network connection in a given time period.</p>
            <p>Higher bandwidth allows larger amounts of spacecraft data to be transmitted faster and more efficiently.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_6_3.php">next</a></p>
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

