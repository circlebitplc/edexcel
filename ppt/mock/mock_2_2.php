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

	<meta name='description' content='Mock 2'>
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

    <!-- 2(a) -->
    <section>
        <section>
            <h4><b>2(a)</b> Explain the difference between OCR and OMR. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>OCR (Optical Character Recognition) reads printed or handwritten characters and converts them into editable digital text.</p>
            <p>OMR (Optical Mark Recognition) detects marks or shaded areas on forms, such as multiple-choice answer sheets.</p>
        </section>
    </section>

    <!-- 2(b)(i) -->
    <section>
        <section>
            <h4><b>2(b)(i)</b> State two other ways a device can be identified on a network. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>MAC address</p>
            <p>Device name / hostname</p>
        </section>
    </section>

    <!-- 2(b)(ii) -->
    <section>
        <section>
            <h4><b>2(b)(ii)</b> State two reasons for identifying devices. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>To ensure data is sent to the correct device on the network.</p>
            <p>To improve network security and allow monitoring of connected devices.</p>
        </section>
    </section>

    <!-- 2(b)(iii) -->
    <section>
        <section>
            <h4><b>2(b)(iii)</b> State two differences between LAN and WAN. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A LAN covers a small geographical area, while a WAN covers a very large geographical area.</p>
            <p>LANs are usually privately owned, while WANs often use public communication infrastructure such as the Internet.</p>
        </section>
    </section>

    <!-- 2(b)(iv) -->
    <section>
        <section>
            <h4><b>2(b)(iv)</b> Explain one advantage of using a network printer. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A network printer can be shared by multiple users connected to the network.</p>
            <p>This reduces hardware costs and makes printing more convenient for hotel staff.</p>
        </section>
    </section>

    <!-- 2(b)(v) -->
    <section>
        <section>
            <h4><b>2(b)(v)</b> Which connects using gateway? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> WAN</p>
        </section>
    </section>

    <!-- 2(c) -->
    <section>
        <section>
            <h4><b>2(c)</b> Describe one way to reduce buffering. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Using a faster Internet connection or increasing available bandwidth can reduce buffering.</p>
            <p>Lowering video quality can also reduce the amount of data that needs to be streamed.</p>
        </section>
    </section>

    <!-- 2(d) -->
    <section>
        <section>
            <h4><b>2(d)</b> Describe two factors that can cause high latency in a WAN. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Long physical distances between devices increase the time taken for data to travel.</p>
            <p>Heavy network traffic or congestion can delay transmission of data packets.</p>
        </section>
    </section>

    <!-- 2(e) -->
    <section>
        <section>
            <h4><b>2(e)</b> Explain one reason to use network OS. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A network operating system allows centralized management of users, files, printers, and security settings across the network.</p>
            <p>This makes administration and access control easier.</p>
        </section>
    </section>

    <!-- 2(f) -->
    <section>
        <section>
            <h4><b>2(f)</b> State one advantage of wireless instead of wired network. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Wireless networks allow users to move freely without needing physical cables.</p>
        </section>
    </section>

    <!-- 2(g) -->
    <section>
        <section>
            <h4><b>2(g)</b> Explain one reason why a star topology is suitable. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>In a star topology, each device is connected separately to a central switch or hub.</p>
            <p>If one cable or device fails, the rest of the network continues to operate normally.</p>
        </section>
    </section>

    <!-- 2(h) -->
    <section>
        <section>
            <h4><b>2(h)</b> Explain one benefit and one risk of cloud storage. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A benefit of cloud storage is that files can be accessed remotely from different devices and locations using the Internet.</p>
            <p>A risk is that unauthorized users or hackers could gain access to sensitive data if security is weak.</p>
        </section>
 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_2_3.php">next</a></p>
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

