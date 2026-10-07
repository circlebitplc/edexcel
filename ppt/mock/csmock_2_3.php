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
    <!-- 3(a)(i) -->
    <section>
        <section>
            <h4><b>3(a)(i)</b> Name items labelled A, B, C and D. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A – Clock</p>
            <p>B – Address bus</p>
            <p>C – Data bus</p>
            <p>D – ALU</p>
        </section>
    </section>

    <!-- 3(a)(ii) -->
    <section>
        <section>
            <h4><b>3(a)(ii)</b> Describe one function of the cache. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Cache stores frequently used instructions and data so the CPU can access them more quickly than RAM.</p>
            <p>This improves processing speed and overall system performance.</p>
        </section>
    </section>

    <!-- 3(b) -->
    <section>
        <section>
            <h4><b>3(b)</b> Identify the most suitable secondary storage medium and justify your answer. (6)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>The most suitable secondary storage medium is cloud storage combined with SSD storage on portable devices.</p>

            <p>Cloud storage is suitable because researchers work in different locations and need to share files collaboratively over the Internet.</p>
            <p>It allows images, videos, audio recordings, and documents to be accessed remotely from multiple devices.</p>

            <p>Cloud storage also provides backup protection if devices are damaged or lost during fieldwork.</p>

            <p>SSDs are suitable for portable devices because they are fast, lightweight, durable, and contain no moving parts.</p>
            <p>This makes them more reliable when travelling and working outdoors.</p>

            <p>The large storage capacity supports multimedia files such as images and videos collected during research.</p>
        </section>
    </section>

    <!-- 3(c) -->
    <section>
        <section>
            <h4><b>3(c)</b> Explain one difference between open source and proprietary software. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Open-source software allows users to view, modify, and distribute the source code freely.</p>
            <p>Proprietary software restricts access to the source code and usually requires users to purchase a license.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_2_4.php">next</a></p>
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

