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
    <!-- 3(a)(i) -->
    <section>
        <section>
            <h4><b>3(a)(i)</b> Describe how an analogue sound wave is converted into digital form. (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The analogue sound wave is sampled at regular intervals using a microphone and an analogue-to-digital converter (ADC).</p>
            <p>Each sample’s amplitude is measured and quantized into a binary value according to the bit depth.</p>
            <p>The binary values are then stored digitally as sound data.</p>
        </section>
    </section>

    <!-- 3(a)(ii) -->
    <section>
        <section>
            <h4><b>3(a)(ii)</b> Effects on sound file (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Duration 10 → 20 min: File size increases, accuracy no change</p>
            <p>Sample rate 44kHz → 8kHz: File size decreases, accuracy decreases</p>
            <p>Bit depth 8 → 16 bits: File size increases, accuracy increases</p>
        </section>
    </section>

    <!-- 3(a)(iii) -->
    <section>
        <section>
            <h4><b>3(a)(iii)</b> Identify three pieces of metadata stored with an image. (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Date and time created</p>
            <p>Camera model or device used</p>
            <p>Image resolution or dimensions</p>
        </section>
    </section>

    <!-- 3(b)(i) -->
    <section>
        <section>
            <h4><b>3(b)(i)</b> Give two benefits of compressing data before emailing. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Smaller file sizes reduce upload and download times.</p>
            <p>Compressed files use less storage space and bandwidth.</p>
        </section>
    </section>

    <!-- 3(b)(ii) -->
    <section>
        <section>
            <h4><b>3(b)(ii)</b> Explain why lossy compression may not be appropriate. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Lossy compression permanently removes some data from the file.</p>
            <p>This may reduce image, audio, or document quality, which could affect important project work.</p>
        </section>
    </section>

    <!-- 3(c)(i) -->
    <section>
        <section>
            <h4><b>3(c)(i)</b> State using an example why smart television needs secondary storage. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A smart TV needs secondary storage to permanently store applications, downloaded videos, and user settings even when power is turned off.</p>
            <p>For example, streaming apps such as YouTube or Netflix need storage space for installation and cached data.</p>
        </section>
    </section>

    <!-- 3(c)(ii) -->
    <section>
        <section>
            <h4><b>3(c)(ii)</b> Identify one appropriate type of secondary storage and justify choice. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Storage Type:</b></p>
            <p>SSD or flash storage</p>

            <p><b>Justification:</b></p>
            <p>SSD storage is suitable because it is fast, reliable, and has no moving parts.</p>
            <p>This improves loading times for apps and videos while reducing the risk of physical damage inside the smart TV.</p>
        </section>
    </section>

    <!-- 3(c)(iii) -->
    <section>
        <section>
            <h4><b>3(c)(iii)</b> Explain how data is stored in this secondary storage type. (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>In flash or SSD storage, data is stored electronically using floating-gate transistors inside memory cells.</p>
            <p>Electrical charges represent binary values of 1s and 0s.</p>
            <p>Because there are no moving mechanical parts, data can be accessed quickly and reliably.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_1_4.php">next</a></p>
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

