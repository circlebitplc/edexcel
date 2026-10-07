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
  <h2>SECTION 4 – INSTANT MESSAGING (IM)</h2>
</section>

<section>
  <section><h4><b>20) </b>Define instant messaging. [2]</h4></section>
  <section><p>Instant messaging is a form of real-time electronic communication that allows users to send and receive text messages over the internet immediately using computers or mobile devices.</p></section> 
</section>

<section>
  <section><h4><b>21) </b>Two advantages of instant messaging. [2]</h4></section>
  <section><p>Instant communication – Messages are delivered in real time.</p></section>
  <section><p>Low cost – Uses the internet, so it is cheaper than calls or SMS.</p></section>
  <section><p>Supports multimedia sharing – Users can send images, videos, and documents.</p></section>
  <section><p>Group communication – Allows group chats for team discussions.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Two disadvantages of instant messaging. [2]</h4></section>
  <section><p>Security and privacy risks – Messages can be hacked or intercepted.</p></section>
  <section><p>Distractions at work – Frequent notifications can reduce productivity.</p></section>
  <section><p>Miscommunication – Tone and meaning can be misunderstood in text messages.</p></section>
  <section><p>Dependence on internet connection – It cannot work properly without stable internet access.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Compare instant messaging with email. [4]</h4></section>
  <section><p>Speed – Instant messaging is real-time and messages are delivered immediately, whereas email may not be read instantly.</p></section>
  <section><p>Formality – Instant messaging is usually informal, while email is more formal and suitable for official communication.</p></section>
  <section><p>Message length – Instant messaging is mainly for short, quick messages, whereas email is suitable for longer, detailed messages.</p></section>
  <section><p>Response time – Instant messaging expects quick replies, while email responses may take longer.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Why instant messaging is unsuitable for formal communication. [3]</h4></section>
  <section><p>It is informal in nature – Messages are usually short and casual, which may not reflect a professional tone required in formal situations.</p></section>
  <section><p>Lack of proper formatting and structure – It does not always support formal layouts, signatures, or detailed documentation like official letters or emails.</p></section>
  <section><p>Security and record issues – Messages may not be securely stored or easily archived for official reference and legal purposes.</p></section>
</section>

<section>
  <h2>SECTION 5 – VOICE OVER INTERNET PROTOCOL (VoIP)</h2>
</section>

<section>
  <section><h4><b>25) </b>Define VoIP. [2]</h4></section>
  <section><p>VoIP allows voice communication over the internet.</p></section>
  <section><p>It replaces traditional telephone lines.</p></section>
</section>

<section>
  <section><h4><b>26) </b>Explain how VoIP works. [4]</h4></section>
  <section><p>Voice signals are converted into digital data.</p></section>
  <section><p>Data is transmitted over IP networks.</p></section>
  <section><p>The data is reassembled at the receiver.</p></section>
  <section><p>It is converted back into sound.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Two advantages of VoIP. [2]</h4></section>
  <section><p>Lower call costs</p></section>
  <section><p>Works on multiple devices</p></section>
</section>

<section>
  <section><h4><b>28) </b>Two disadvantages of VoIP. [2]</h4></section>
  <section><p>Depends on internet connection</p></section>
  <section><p>Call quality can be unstable</p></section>
</section>

<section>
  <section><h4><b>29) </b>Compare VoIP and traditional telephone calls. [4]</h4></section>
  <section><p>VoIP uses the internet.</p></section>
  <section><p>It is cheaper for long-distance calls.</p></section>
  <section><p>Traditional calls use telephone lines.</p></section>
  <section><p>They are usually more reliable.</p></section>
</section>

<section>
  <section><h4><b>30) </b>How network quality affects VoIP performance. [3]</h4></section>
  <section><p>Low bandwidth reduces audio quality.</p></section>
  <section><p>High latency causes delays.</p></section>
  <section><p>Packet loss causes dropped calls.</p></section>
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

