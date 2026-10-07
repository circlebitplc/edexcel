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
  <h2>SECTION 2 – COMMUNICATION METHODS</h2>
</section>
 
<section>
  <section><h4><b>5) </b>Define synchronous communication. [2]</h4></section>
  <section><p>the real-time exchange of information where all parties are present simultaneously to engage, discuss, and receive immediate responses. </p></section>
   
</section>

<section>
  <section><h4><b>6) </b>Define asynchronous communication. [2]</h4></section>
  <section><p>Asynchronous communication is a method of communication where the sender and receiver do not need to be online at the same time. Messages are stored and accessed later, meaning the receiver can read and respond after a delay, not immediately.</p></section>
   </section>  

<section>
  <section><h4><b>7) </b>Give two examples of synchronous communication. [2]</h4></section>
  <section><p>Video conferencing (e.g. live online meetings or classes)</p></section>
  <section><p>Telephone calls</p></section>
  <section><p>Face-to-face conversation</p></section>
  <section><p>Live text chat / instant messaging (e.g. real-time chat)</p></section>
</section>

<section>
  <section><h4><b>8) </b>Give two examples of asynchronous communication. [2]</h4></section>
  <section><p>Email</p></section>
  <section><p>Online discussion forums</p></section>
  <section><p>SMS / text messages</p></section>
  <section><p>Recorded voice messages</p></section>
</section>

<section>
  <section><h4><b>9) </b>Compare synchronous and asynchronous communication. [4]</h4></section>
  <section><p>Synchronous communication happens in real time, where all participants are online at the same time and messages are sent and received immediately, such as during a phone call or live chat. This allows for instant feedback and quick decision-making.</p></section>
  <section><p>In contrast, asynchronous communication does not require participants to be online at the same time, and messages are read and responded to later, such as email or forums. This is more flexible but feedback is delayed.</p></section>
 
</section>

<section>
  <section><h4><b>10) </b>One advantage and one disadvantage of synchronous communication. [4]</h4></section>
  <section><p>Advantage:
Synchronous communication allows immediate feedback, so questions can be answered instantly and decisions can be made quickly, improving efficiency.</p></section>
  <section><p>Disadvantage:
All participants must be available at the same time, which can be inconvenient and difficult to arrange, especially across different time zones or busy schedules.</p></section>
</section>

<section>
  <section><h4><b>11) </b>One advantage and one disadvantage of asynchronous communication. [4]</h4></section>
  <section><p>Advantage: Asynchronous communication allows users to send and receive messages at different times, giving flexibility and allowing people to respond when convenient, which is useful for busy schedules or different time zones.</p></section>
  <section><p>Disadvantage: Feedback is not immediate, so responses can be delayed, which may slow down decision-making and problem solving.</p></section>
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

