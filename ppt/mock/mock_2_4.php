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
    <!-- 4(a) -->
    <section>
        <section>
            <h4><b>4(a)</b> Explain one way the Internet has improved how Saku works. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The Internet allows Saku to communicate instantly with colleagues and upload content remotely from different locations.</p>
            <p>This improves efficiency and supports flexible working.</p>
        </section>
    </section>

    <!-- 4(b)(i) -->
    <section>
        <section>
            <h4><b>4(b)(i)</b> Explain one benefit of cloud storage when working remotely. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Cloud storage allows files to be accessed, edited, and shared from any location with Internet access.</p>
            <p>This makes collaboration and remote working easier.</p>
        </section>
    </section>

    <!-- 4(b)(ii) -->
    <section>
        <section>
            <h4><b>4(b)(ii)</b> State one device used to access cloud storage. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Laptop</p>
        </section>
    </section>

    <!-- 4(c) -->
    <section>
        <section>
            <h4><b>4(c)</b> Describe what is meant by filtering software. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Filtering software monitors and controls access to websites or online content.</p>
            <p>It blocks harmful, inappropriate, or unauthorized material from being accessed.</p>
        </section>
    </section>

    <!-- 4(d) -->
    <section>
        <section>
            <h4><b>4(d)</b> State two other features of a strong password. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Long length (for example 8–12+ characters)</p>
            <p>Combination of uppercase and lowercase letters with numbers</p>
        </section>
    </section>

    <!-- 4(e) -->
    <section>
        <section>
            <h4><b>4(e)</b> Describe the risk of ransomware. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Ransomware encrypts files or locks systems so users cannot access their data.</p>
            <p>Attackers then demand payment to restore access.</p>
        </section>
    </section>

    <!-- 4(f) -->
    <section>
        <section>
            <h4><b>4(f)</b> Which is least environmentally friendly? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Replacing devices frequently</p>
        </section>
    </section>

    <!-- 4(g) -->
    <section>
        <section>
            <h4><b>4(g)</b> Discuss the suitability of different broadband options in rural areas. (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Different broadband options have advantages and disadvantages for users living in rural areas where Internet access may be limited.</p>
 </section>
    <section> 
            <p>Fibre broadband provides very high speeds and reliable performance, making it suitable for streaming, video calls, and cloud services. However, fibre infrastructure may not be available in remote rural areas because installation costs are expensive.</p>
 </section>
    <section> 
            <p>ADSL broadband uses existing telephone lines and is widely available. It is usually cheaper than fibre broadband. However, speeds are slower and performance decreases over long distances from the telephone exchange, making it less suitable for heavy Internet use.</p>
 </section>
    <section> 
            <p>Mobile broadband using 4G or 5G networks can provide flexible Internet access without cables. It is useful in areas without fibre infrastructure. However, signal strength may vary depending on coverage, weather, or network congestion.</p>
 </section>
    <section> 
            <p>Satellite broadband is available in remote areas where other broadband types are unavailable. It can provide Internet access almost anywhere. However, it often has higher latency because signals travel long distances to satellites, which can affect online gaming or video calls.</p>
     </section>
    <section> 
            <p>Overall, mobile broadband or satellite broadband may be the most practical choices in remote rural areas where fibre infrastructure is unavailable, although fibre broadband would provide the best performance if accessible.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_2_5.php">next</a></p>
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

