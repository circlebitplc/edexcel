<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Paper 1 – QUESTION 4'>
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
					<h2>QUESTION 4</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 
<section>
  <h2>QUESTION 4 – ICT in Transport and Employment</h2>
</section>

<section>
  <section><h4><b>4(a)</b> How a sensor improves vehicle safety. (2)</h4></section>
  <section><p>One other sensor that can improve vehicle safety is a proximity (parking) sensor. This sensor detects objects close to the vehicle using ultrasonic or radar signals. When the car approaches an obstacle, the sensor measures the distance and alerts the driver with a warning sound or visual display. This helps prevent collisions when parking or moving at low speeds, reducing the risk of accidents and damage. Therefore, the use of proximity sensors improves vehicle safety by helping the driver avoid hitting nearby objects or pedestrians.</p></section> 
</section>

<section>
  <section><h4><b>4(b)</b> Wireless communication used. (1)</h4></section>
  <section><p>GPS</p></section>
</section>

<section>
  <section><h4><b>4(c)</b> Two impacts of the Internet on taxi business. (4)</h4></section>
  <section><p>One impact of the use of the Internet on the taxi company is that bookings can be made online through a website or mobile app. This allows customers to request a taxi at any time without needing to phone the company. As a result, the company can operate more efficiently, reduce the need for call centre staff, and manage bookings automatically using a central system.</p></section>
  <section><p>Another impact is that the company can use GPS and online tracking systems to monitor vehicles in real time. This enables the company to allocate the nearest available driver to a customer, reducing waiting times and fuel costs. It also allows customers to track their taxi’s location, improving customer satisfaction and service quality.</p></section>
</section>

<section>
  <section><h4><b>4(d)</b> Why data protection laws give control to employees. (2)</h4></section>
  <section><p>Data protection laws give employees more control over their personal information because they provide legal rights over how that data is collected, stored, and used. Employees have the right to access the personal data their employer holds about them, request corrections if the data is inaccurate, and in some cases request that it is deleted. Employers must also obtain data lawfully, use it only for specified purposes, and keep it secure. This prevents misuse, unauthorised sharing, or excessive collection of personal information. Therefore, data protection laws ensure employees have greater transparency and control over their own data.</p></section> 
</section>

<section>
  <section><h4><b>4(e)</b> One impact of Internet on employment. (2)</h4></section>
  <section><p>impact of the Internet on employment is the increase in flexible or gig-based work. Taxi companies can use online platforms or apps to recruit drivers who work on a self-employed or part-time basis instead of being full-time employees. This allows drivers to choose their own working hours and accept jobs through the app. However, it may also mean fewer permanent contracts and less job security. Therefore, the Internet has changed employment by making work more flexible but sometimes less stable.</p></section>
</section>

<section>
  <section><h4><b>4(f)</b> Ethical impacts of monitoring communication. (8)</h4></section>
  <section><p>One ethical benefit is improved security and safety. For example, organisations may monitor emails or messages to prevent illegal activities, data breaches, or harassment. Governments may also monitor communications to detect criminal or terrorist activity. This can help protect individuals and society from harm.</p></section>
  <section><p>However, there are significant ethical concerns regarding privacy. Individuals have a right to private communication, and monitoring can be seen as an invasion of that privacy. If people know they are being monitored, they may feel uncomfortable or restricted in expressing their opinions freely. This can reduce freedom of speech and trust.</p></section>
  <section><p>Another ethical issue is consent and transparency. It may be unethical if individuals are monitored without their knowledge or clear consent. Ethical practice requires that people are informed about what data is being collected, why it is being monitored, and how it will be used.</p></section>
  <section><p>There is also the risk of misuse of information. Collected communication data could be accessed by unauthorised persons, shared inappropriately, or used unfairly against someone. For example, messages could be taken out of context or used to discriminate against an employee.</p></section>
  <section><p>In conclusion, while monitoring communication can improve security and organisational control, it raises serious ethical concerns about privacy, consent, freedom of expression, and potential misuse of data. A balance must be maintained between safety and individual rights.</p></section> 
</section>

<section>
  <h2>QUESTION 5 – Online Communities and the Environment</h2>
</section>

<section>
  <section><h4><b>5(a)</b> Two features of video sharing sites. (2)</h4></section>
  <section><p>Video uploading.</p></section>
  <section><p>Commenting and rating.</p></section>
</section>

<section>
  <section><h4><b>5(b)</b> Two risks of online communities. (2)</h4></section>
  <section><p>Cyberbullying.</p></section>
  <section><p>Identity theft.</p></section>
</section>

<section>
  <section><h4><b>5(c)</b> How acceptable use policies keep communities safe. (2)</h4></section>
  <section><p>Set rules on behaviour.</p></section>
  <section><p>Prevent misuse.</p></section>
</section>

<section>
  <section><h4><b>5(d)</b> Action after reading inappropriate post. (2)</h4></section>
  <section><p>Report the post to moderators.</p></section>
</section>

<section>
  <section><h4><b>5(e)</b> One way to evaluate fitness for purpose. (2)</h4></section>
  <section><p>Check relevance to topic and audience.</p></section>
</section>

<section>
  <section><h4><b>5(f)</b> Positive impacts of ICT on the environment. (8)</h4></section>
  <section><p>Reduces resource wastage using sensors.</p></section>
  <section><p>Supports remote working.</p></section>
  <section><p>Reduces travel emissions.</p></section>
  <section><p>Centralised cloud computing.</p></section>
  <section><p>Lower energy consumption.</p></section>
  <section><p>Reduces electronic waste.</p></section>
  <section><p>Digital documents reduce paper use.</p></section>
  <section><p>Lowers deforestation and pollution.</p></section>
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

