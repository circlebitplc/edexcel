<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Topic 2 – Networking'>
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
					<h4>Topic 2</h4>
					<h2> Networking</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h2>SECTION 9 – TYPES OF NETWORK</h2>
</section>

<section>
  <section><h4><b>46) </b>Define LAN. [2]</h4></section>
  <section><p>A LAN covers a small geographical area.</p></section>
  <section><p>Examples include schools and offices.</p></section>
</section>

<section>
  <section><h4><b>47) </b>Define WAN. [2]</h4></section>
  <section><p>A WAN covers a large geographical area.</p></section>
  <section><p>It connects networks across cities or countries.</p></section>
</section>

<section>
  <section><h4><b>48) </b>Define PAN. [2]</h4></section>
  <section><p>A PAN connects devices around one person.</p></section>
  <section><p>Examples include phones and smartwatches.</p></section>
</section>

<section>
  <section><h4><b>49) </b>Compare LAN and WAN. [4]</h4></section>
  <section><p>LANs cover small areas and are faster.</p></section>
  <section><p>WANs cover large areas.</p></section>
  <section><p>WANs depend on external infrastructure.</p></section>
  <section><p>WANs are slower and more expensive.</p></section>
</section>

<section>
  <section><h4><b>50) </b>One use of PAN. [2]</h4></section>
  <section><p>Connecting a smartphone to wireless earbuds using Bluetooth.</p></section>
</section>

<section>
  <section><h4><b>51) </b>One advantage of WAN for organisations. [3]</h4></section>
  <section><p>Connects multiple branches.</p></section>
  <section><p>Allows data sharing across locations.</p></section>
  <section><p>Improves communication.</p></section>
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

