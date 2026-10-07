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

	<meta name='description' content='Mock 1'>
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
					<h4>Mock 1</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  

    <!-- 1(a)(i) -->
    <section>
        <section>
            <h4><b>1(a)(i)</b> Complete the diagram of the 4-layer TCP/IP model. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Application</p>
            <p>Transport</p>
            <p>Internet</p>
            <p>Link</p>
        </section>
    </section>

    <!-- 1(a)(ii) -->
    <section>
        <section>
            <h4><b>1(a)(ii)</b> State the network protocol used to request a webpage. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> HTTP</p>
        </section>
    </section>

    <!-- 1(a)(iii) -->
    <section>
        <section>
            <h4><b>1(a)(iii)</b> Identify the reason computers are connected in a network. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To share peripherals</p>
        </section>
    </section>

    <!-- 1(a)(iv) -->
    <section>
        <section>
            <h4><b>1(a)(iv)</b> Explain one benefit to a user of using IMAP to access emails. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>IMAP stores emails on the mail server instead of downloading and deleting them from the server.</p>
            <p>This allows users to access and synchronize the same emails from multiple devices such as phones, tablets, and computers.</p>
        </section>
    </section>

    <!-- 1(a)(v) -->
    <section>
        <section>
            <h4><b>1(a)(v)</b> Define the term ‘bandwidth’. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Bandwidth is the maximum amount of data that can be transmitted across a network connection per second.</p>
        </section>
    </section>

    <!-- 1(b)(i) -->
    <section>
        <section>
            <h4><b>1(b)(i)</b> State two benefits and two drawbacks of star topology. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Benefit 1:</b></p>
            <p>If one cable or device fails, the rest of the network continues working normally.</p>

            <p><b>Benefit 2:</b></p>
            <p>Faults are easier to identify and fix because each device has a separate connection.</p>

            <p><b>Drawback 1:</b></p>
            <p>The network depends heavily on the central switch or hub.</p>

            <p><b>Drawback 2:</b></p>
            <p>More cabling is required, increasing installation costs.</p>
        </section>
    </section>

    <!-- 1(b)(ii) -->
    <section>
        <section>
            <h4><b>1(b)(ii)</b> Explain one other method of physical security used to protect servers. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A locked server room with keycard or biometric access can be used to protect the servers.</p>
            <p>This prevents unauthorized people from physically accessing or damaging the equipment.</p>
        </section>
    </section>

    <!-- 1(b)(iii) -->
    <section>
        <section>
            <h4><b>1(b)(iii)</b> Explain why Ethernet is a standard. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Ethernet is a standard because it defines agreed rules and specifications for wired network communication.</p>
            <p>This allows hardware and devices made by different manufacturers to communicate and work together correctly.</p>
        </section>
    </section>

    <!-- 1(c) -->
    <section>
        <section>
            <h4><b>1(c)</b> Describe how a router enables data to arrive at its destination. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A router examines the destination IP address in each data packet.</p>
            <p>It then uses routing tables to determine the best path and forwards the packet towards its destination network.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_1_2.php">next</a></p>
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

