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

	<meta name='description' content='Mock'>
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
					<h4>Mock 2</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  
    <!-- 1(a) -->
    <section>
        <section>
            <h4><b>1(a)</b> Give one other reason for connecting computers in a local area network. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To share files, printers, and other hardware resources between computers.</p>
        </section>
    </section>

    <!-- 1(b) -->
    <section>
        <section>
            <h4><b>1(b)</b> State what is meant by the term “Network topology”. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Network topology is the physical or logical layout of devices and connections in a network.</p>
        </section>
    </section>

    <!-- 1(c)(i) -->
    <section>
        <section>
            <h4><b>1(c)(i)</b> State the name of the network topology. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Star topology</p>
        </section>
    </section>

    <!-- 1(c)(ii) -->
    <section>
        <section>
            <h4><b>1(c)(ii)</b> Explain one reason why this topology is most widely used in LAN. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> A star topology is reliable because if one cable or device fails, the rest of the network continues to operate normally.</p>
        </section>
    </section>

    <!-- 1(d) -->
    <section>
        <section>
            <h4><b>1(d)</b> State one advantage of being able to measure the speed of a network. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> It helps network administrators identify performance problems and improve network efficiency.</p>
        </section>
    </section>

    <!-- 1(e) -->
    <section>
        <section>
            <h4><b>1(e)</b> Construct an expression to show how transmission time in seconds is calculated. (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>(10 × 8 × 1000) ÷ 54</p>
        </section>
        <section>
            <p><b>Explanation:</b></p>
            <p>Multiply by 8 to convert gigabytes to gigabits.</p>
            <p>Divide by 54 Mbps to calculate the transmission time in seconds.</p>
        </section>
    </section>

    <!-- 1(f) -->
    <section>
        <section>
            <h4><b>1(f)</b> State the purpose of network protocols. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Network protocols provide standard rules for communication between devices on a network.</p>
        </section>
    </section>

    <!-- 1(g)(i) -->
    <section>
        <section>
            <h4><b>1(g)(i)</b> Complete the TCP/IP table. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Application Layer – Provides services such as email and web browsing</p>
            <p>Transport Layer – Ensures reliable data transmission and error checking</p>
        </section>
    </section>

    <!-- 1(g)(ii) -->
    <section>
        <section>
            <h4><b>1(g)(ii)</b> State the email protocol she should use and justify your choice. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> IMAP</p>
        </section>
        <section>
            <p><b>Explanation:</b></p>
            <p>IMAP stores emails on the mail server and synchronizes messages across multiple devices.</p>
            <p>This allows access to the same emails from phones, tablets, and computers.</p>
        </section>
    </section>

    <!-- 1(h) -->
    <section>
        <section>
            <h4><b>1(h)</b> Describe the series of steps that occur from entering a web address until the webpage is displayed. (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The web browser sends a request to a DNS server to find the IP address of the website.</p>
            <p>The browser then connects to the web server using the IP address and sends an HTTP or HTTPS request.</p>

            <p>The server sends the webpage data back in packets, which the browser interprets and displays.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_2_2.php">next</a></p>
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

