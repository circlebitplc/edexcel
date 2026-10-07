<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?><!doctype html>
<html lang='en'>
<head>  
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='IGCSE Paper 1 – Question 2: Types of Computer and Software'>
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
					<h4>Paper 1</h4>
					<h2>Question 2</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <section>
  <h2>QUESTION 2 – Types of Computer and Software</h2>
</section>

<section>
  <section><h4><b>2(a)</b> Best choice for complex tasks. (1)</h4></section>
  <section><p>C. Mainframe</p></section>
</section>

<section>
  <section><h4><b>2(b)</b> Two examples of an embedded system. (2)</h4></section>
  <section><p>Washing machine controller.</p></section>
  <section><p>Microwave oven controller.</p></section>
</section>

<section>
  <section><h4><b>2(c)</b> Why some computers do not need application software. (2)</h4></section>
  <section><p>They perform a single dedicated task.</p></section>
  <section><p>The software is built into the system.</p></section>
</section>

<section>
  <section><h4><b>2(d)</b> Not a type of personal computer. (1)</h4></section>
  <section><p>B. Media player</p></section>
</section>

<section>
  <section><h4><b>2(e)</b> Two benefits of using a plotter. (2)</h4></section>
  <section><p>Produces large-scale accurate drawings.</p></section>
  <section><p>Provides high precision and fine detail.</p></section>
</section>

<section>
  <section><h4><b>2(f)</b> Why routers contain an IP address table. (2)</h4></section>
  <section><p>To identify destination devices.</p></section>
  <section><p>To forward data along the correct path.</p></section>
</section>

<section>
  <section><h4><b>2(g)</b> Expression for bits in 256 GiB. (3)</h4></section>
  <section><p>256 × 1024 × 1024 × 1024 × 8</p></section>
</section>

<section>
  <section><h4><b>2(h)</b> Two types of application software. (2)</h4></section>
  <section><p>Word processing software.</p></section>
  <section><p>Spreadsheet software.</p></section>
</section>

<section>
  <section><h4><b>2(i)</b> Match device types. (3)</h4></section>
  <section><p>Input → Webcam</p></section>
  <section><p>Storage → Hard disk</p></section>
  <section><p>Output → Data projector</p></section>
</section>

<section>
  <section><h4><b>2(j)</b> Define convergence. (2)</h4></section>
  <section><p>Convergence combines multiple technologies into one device.</p></section>
</section>

<section>
  <section><h4><b>2(k)</b> Order storage capacities. (3)</h4></section>
  <section><p>kibibyte → mebibyte → gibibyte → tebibyte</p></section>
</section>

<section>
  <section><h4><b>2(l)</b> Why operating systems use print spooling. (2)</h4></section>
  <section><p>Documents are queued for printing.</p></section>
  <section><p>Users can continue working while printing.</p></section>
</section>

<section>
  <section><h4><b>2(m)</b> Two benefits of proprietary software. (4)</h4></section>
  <section><p>Professionally developed and tested, making it reliable.</p></section>
  <section><p>Includes technical support and regular updates.</p></section>
</section> 
<section>
	<section><h4><a href='<?= $dirBase ?>/q3.php'>Q3</a></h4> </section>
   </section>

			</div>
		</div>

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

