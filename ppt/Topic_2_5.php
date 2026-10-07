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
  <h2>SECTION 5 – VOICE OVER INTERNET PROTOCOL (VoIP)</h2>
</section>

<section>
  <section><h4><b>25) </b>Define VoIP. [2]</h4></section>
  <section><p>VoIP (Voice over Internet Protocol) is a technology that allows voice communication (phone calls) to be made over the internet instead of using traditional telephone lines.</p></section> 
</section>

<section>
  <section><h4><b>26) </b>Explain how VoIP works. [4]</h4></section>
  <section><p>Voice is captured – When a person speaks into a microphone, their voice (analogue signal) is recorded.</p></section>
  <section><p>Conversion to digital data – The analogue voice signal is converted into digital data using an analogue-to-digital converter (ADC).</p></section>
  <section><p>Data transmission over the internet – The digital data is broken into packets and transmitted through the internet using IP (Internet Protocol).</p></section>
  <section><p>Reconversion at the receiver’s end – The digital packets are reassembled and converted back into analogue sound using a digital-to-analogue converter (DAC), so the receiver can hear the voice.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Two advantages of VoIP. [2]</h4></section>
  <section><p>Low cost – Calls, especially international calls, are cheaper than traditional telephone services.
Uses existing internet connection – No need for separate phone lines.
</p></section>
  <section><p>Supports additional features – Such as video calls, call recording, voicemail, and conference calls.
Portability – Users can make and receive calls from anywhere with an internet connection.
</p></section>
</section>

<section>
  <section><h4><b>28) </b>Two disadvantages of VoIP. [2]</h4></section>
  <section><p>Depends on internet connection – Poor or unstable internet can cause delays, echo, or dropped calls.</p></section>
  <section><p>Power dependency – It may not work during power cuts unless backup power is available.</p></section>
  <section><p>Security risks – Calls can be vulnerable to hacking or cyberattacks if not properly secured.</p></section>
  <section><p>Call quality issues – Voice quality may be lower than traditional phone lines if bandwidth is limited.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Compare VoIP and traditional telephone calls. [4]</h4></section>
  <section><p>Transmission method – VoIP uses the internet (IP network) to transmit voice data, whereas traditional telephone calls use Public Switched Telephone Network (PSTN) lines.</p></section>
  <section><p>Cost – VoIP is usually cheaper, especially for international calls, while traditional telephone calls can be more expensive.</p></section>
  <section><p>Equipment required – VoIP requires an internet connection and compatible device (computer, smartphone, or IP phone), whereas traditional calls require a landline phone and telephone connection.</p></section>
  <section><p>Call quality and reliability – VoIP quality depends on internet speed and bandwidth, while traditional telephone calls are generally more stable and consistent.</p></section>
</section>

<section>
  <section><h4><b>30) </b>How network quality affects VoIP performance. [3]</h4></section>
  <section><p>Bandwidth – If there is insufficient bandwidth, voice data packets cannot be transmitted smoothly, causing poor audio quality or dropped calls.
</p></section>
  <section><p>Latency (delay) – High latency causes a noticeable delay between speaking and hearing the response, making conversations difficult.</p></section>
  <section><p>Jitter – If data packets arrive at uneven intervals, the voice may sound distorted, broken, or robotic.</p></section>
  <section><p>Packet loss – If some data packets are lost during transmission, parts of the conversation may be missing or unclear</p></section>
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

