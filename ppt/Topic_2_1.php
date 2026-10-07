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
  <h2>SECTION 1 – COMMUNICATION IN ICT</h2>
</section>

<section>
  <section><h4><b>1) </b>Define communication in ICT. [2]</h4></section>
  <section><p>Two or mor devices connect together to share the information using  the process of transmitting and receiving information.</p></section>
  <section><p>This is done between people or devices using digital technologies such as computers, networks, and the internet.</p></section>
</section>

<section>
  <section><h4><b>2) </b>Explain why communication is important in organisations. [3]</h4></section>
  <section><p>Communication allows employees to share information and instructions.</p></section>
  <section><p>This ensures tasks are completed correctly and on time.</p></section>
  <section><p>It also improves coordination and teamwork, helping organisations achieve their objectives.</p></section>
</section>

<section>
  <section><h4><b>3) </b>Explain the difference between communication and data transfer. [3]</h4></section>
  <section><p>Communication is the process of exchanging information between people or systems where the message is understood and interpreted by the receiver. It focuses on meaning, context, and feedback.</p></section>
  <section><p>Data transfer, on the other hand, is the movement of raw data from one device or location to another without considering whether the data is understood or meaningful.</p></section>
   
</section>

<section>
  <section><h4><b>4) </b>Explain how effective communication improves organisational efficiency. [4]</h4></section>
  <section><p>Effective communication improves organisational efficiency by ensuring that information is shared clearly and accurately, so employees understand their tasks and objectives without confusion. This reduces errors and the need for rework, saving time and resources. Clear communication also speeds up decision-making because managers and staff receive the right information at the right time. In addition, it improves coordination and teamwork between departments, helping tasks to be completed faster and more effectively.</p></section>
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

