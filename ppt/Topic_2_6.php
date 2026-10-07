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
  <h2>SECTION 6 – VIDEO CONFERENCING</h2>
</section>

<section>
  <section><h4><b>31) </b>Define video conferencing. [2]</h4></section>
  <section><p>Video conferencing is a communication method that allows people to see and hear each other.</p></section>
  <section><p>It uses audio and video over a network in real time.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Identify two advantages of video conferencing. [2]</h4></section>
  <section><p>Reduces travel costs.</p></section>
  <section><p>Allows real-time collaboration between different locations.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Identify two disadvantages of video conferencing. [2]</h4></section>
  <section><p>Requires high-speed internet.</p></section>
  <section><p>Needs suitable hardware such as cameras and microphones.</p></section>
</section>

<section>
  <section><h4><b>34) </b>Explain why video conferencing is useful for businesses. [4]</h4></section>
  <section><p>It allows meetings without physical travel.</p></section>
  <section><p>This saves time and money.</p></section>
  <section><p>It supports remote working.</p></section>
  <section><p>It improves collaboration and decision-making.</p></section>
</section>

<section>
  <section><h4><b>35) </b>Explain two technical requirements for effective video conferencing. [4]</h4></section>
  <section><p>A high-bandwidth internet connection is required.</p></section>
  <section><p>This ensures smooth audio and video transmission.</p></section>
  <section><p>Quality cameras and microphones are required.</p></section>
  <section><p>This ensures participants can see and hear clearly.</p></section>
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

