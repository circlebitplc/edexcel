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

    <!-- 6(a) -->
    <section>
        <section>
            <h4><b>6(a)</b> Compare two features of high-level and low-level programming languages. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>High-level languages are easier for humans to read and write because they use English-like syntax.</p>
            <p>Low-level languages are closer to machine code and are harder to understand.</p>
            <p>High-level languages are portable across different systems, while low-level languages are usually hardware-specific.</p>
        </section>
    </section>

    <!-- 6(b) -->
    <section>
        <section>
            <h4><b>6(b)</b> Construct the Boolean expression. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> A AND (B OR C)</p>
        </section>
    </section>

    <!-- 6(c) -->
    <section>
        <section>
            <h4><b>6(c)</b> Construct the truth table. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A&nbsp;&nbsp;B&nbsp;&nbsp;C&nbsp;&nbsp;OUTPUT</p>
            <p>0&nbsp;&nbsp;0&nbsp;&nbsp;0&nbsp;&nbsp;0</p>
            <p>0&nbsp;&nbsp;0&nbsp;&nbsp;1&nbsp;&nbsp;0</p>
            <p>0&nbsp;&nbsp;1&nbsp;&nbsp;0&nbsp;&nbsp;0</p>
            <p>0&nbsp;&nbsp;1&nbsp;&nbsp;1&nbsp;&nbsp;0</p>
            <p>1&nbsp;&nbsp;0&nbsp;&nbsp;0&nbsp;&nbsp;0</p>
            <p>1&nbsp;&nbsp;0&nbsp;&nbsp;1&nbsp;&nbsp;1</p>
            <p>1&nbsp;&nbsp;1&nbsp;&nbsp;0&nbsp;&nbsp;1</p>
            <p>1&nbsp;&nbsp;1&nbsp;&nbsp;1&nbsp;&nbsp;1</p>
        </section>
    </section>

    <!-- 6(d) -->
    <section>
        <section>
            <h4><b>6(d)</b> Give two ways Akiko can demonstrate professionalism. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Following legal and ethical guidelines when developing software.</p>
            <p>Producing reliable, well-tested, and properly documented programs.</p>
        </section>
    </section>

    <!-- 6(e) -->
    <section>
        <section>
            <h4><b>6(e)</b> Temperature conversion algorithm (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Start</p>
            <p>Display menu:</p>
            <p>1 = Celsius to Fahrenheit</p>
            <p>2 = Fahrenheit to Celsius</p>

            <p>Input choice</p>
            <p>Input temperature</p>

            <p>IF choice = 1 THEN</p>
            <p>&nbsp;&nbsp;F = (9/5 × C) + 32</p>
            <p>&nbsp;&nbsp;Display F</p>
            <p>ELSE</p>
            <p>&nbsp;&nbsp;C = (5/9 × (F − 32))</p>
            <p>&nbsp;&nbsp;Display C</p>
            <p>ENDIF</p>

            <p>Ask "Another conversion?"</p>
            <p>IF Yes → repeat</p>
            <p>IF No → Stop</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_2_1.php">next</a></p>
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

