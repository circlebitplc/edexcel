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

	<meta name='description' content='Mock 5'>
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
					<h4>Mock 5</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 
      <!-- 2(a) -->
    <section>
        <section>
            <h4><b>2(a)</b> Device responsible for routing data packets (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Router</p>
        </section>
    </section>

    <!-- 2(b)(i) -->
    <section>
        <section>
            <h4><b>2(b)(i)</b> Two methods to identify devices (2)</h4>
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
            <h4><b>2(b)(ii)</b> Reasons for identification (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To ensure data is sent to the correct device and to manage network access securely.</p>
        </section>
        <section>
            <p><b>Explanation:</b> Prevents errors and improves security.</p>
        </section>
    </section>

    <!-- 2(b)(iii) -->
    <section>
        <section>
            <h4><b>2(b)(iii)</b> Differences between wired and wireless (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Wired uses cables and is faster and more secure.</p>
            <p>Wireless uses radio signals and allows mobility.</p>
        </section>
    </section>

    <!-- 2(b)(iv) -->
    <section>
        <section>
            <h4><b>2(b)(iv)</b> Advantage of fibre optic (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Fibre optic cables transmit data using light, allowing faster speeds and less signal loss over long distances.</p>
        </section>
    </section>

    <!-- 2(v) -->
    <section>
        <section>
            <h4><b>2(v)</b> Name topologies (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A – Star</p>
            <p>B – Bus</p>
            <p>C – Ring</p>
            <p>D – Mesh</p>
        </section>
    </section>

    <!-- 2(vi) -->
    <section>
        <section>
            <h4><b>2(vi)</b> Component with MAC address (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Network interface card</p>
        </section>
    </section>

    <!-- 2(c) -->
    <section>
        <section>
            <h4><b>2(c)</b> Factor reducing network performance (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Network congestion reduces available bandwidth, causing slower data transfer.</p>
        </section>
    </section>

    <!-- 2(d) -->
    <section>
        <section>
            <h4><b>2(d)</b> Packet loss (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Packet loss occurs when data packets fail to reach their destination due to errors or congestion, resulting in incomplete data.</p>
        </section>
    </section>

    <!-- 2(e)(i) -->
    <section>
        <section>
            <h4><b>2(e)(i)</b> Why packets are reassembled (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Data is split into packets that may arrive out of order, so they must be reassembled correctly at the destination.</p>
        </section>
    </section>

    <!-- 2(e)(ii) -->
    <section>
        <section>
            <h4><b>2(e)(ii)</b> Why organisations use firewalls (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Firewalls block unauthorised access and protect systems from cyber attacks by monitoring network traffic.</p>
        </section>
    </section>

    <!-- 2(f) -->
    <section>
        <section>
            <h4><b>2(f)</b> Define data transmission speed (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> The rate at which data is transferred over a network, measured in bits per second (bps).</p>
        </section>
    </section>
			<section>
            <p><a href="<?= $dirBase ?>/mock_5_3.php">next</a></p> 
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

