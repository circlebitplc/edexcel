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

	<meta name='description' content='Chapter 9 – Implications of Digital Technologies'>
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
					<h4>Chapter 9</h4>
					<h2>Implications of Digital Technologies</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions (MCQs)</h3>
</section>

<section>
  <section><h4><b>1) </b>Data protection laws require companies to:</h4></section>
  <section><p>Answer: C. Use data fairly and lawfully</p></section>
</section>

<section>
  <section><h4><b>2) </b>Individuals have the right to:</h4></section>
  <section><p>Answer: B. Access a copy of their stored data</p></section>
</section>

<section>
  <section><h4><b>3) </b>Copyright gives creators the right to:</h4></section>
  <section><p>Answer: B. Distribute and control their own work</p></section>
</section>

<section>
  <section><h4><b>4) </b>ANPR is used to monitor:</h4></section>
  <section><p>Answer: B. Number plates of vehicles</p></section>
</section>

<section>
  <section><h4><b>5) </b>A major drawback of monitoring individuals is:</h4></section>
  <section><p>Answer: C. Compromised privacy</p></section>
</section>

<section>
  <section><h4><b>6) </b>Data centres consume a lot of energy because of:</h4></section>
  <section><p>Answer: B. Cooling systems</p></section>
</section>

<section>
  <section><h4><b>7) </b>One way to reduce harmful e-waste chemicals is:</h4></section>
  <section><p>Answer: C. Recycling and regulation</p></section>
</section>

<section>
  <section><h4><b>8) </b>Blue light from screens can cause:</h4></section>
  <section><p>Answer: B. Eye strain and sleep disruption</p></section>
</section>

<section>
  <section><h4><b>9) </b>Trip hazards are usually caused by:</h4></section>
  <section><p>Answer: B. Trailing wires</p></section>
</section>

<section>
  <section><h4><b>10) </b>Electric shock can be prevented by:</h4></section>
  <section><p>Answer: C. Keeping liquids away</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>What does ‘data used fairly and lawfully’ mean?</h4></section>
  <section><p>Personal data must be collected legally.</p></section>
  <section><p>It must be used for clear purposes and not misused or shared without permission.</p></section>
</section>

<section>
  <section><h4><b>12) </b>State one individual right under data protection laws.</h4></section>
  <section><p>The right to access personal data held by organisations.</p></section>
</section>

<section>
  <section><h4><b>13) </b>Define copyright.</h4></section>
  <section><p>Copyright is a legal protection that gives creators control over how their original work is used and shared.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Give two ways individuals can be monitored.</h4></section>
  <section><p>CCTV cameras.</p></section>
  <section><p>GPS tracking.</p></section>
</section>

<section>
  <section><h4><b>15) </b>What does sustainability mean in ICT?</h4></section>
  <section><p>Sustainability means using ICT in a way that minimises environmental damage.</p></section>
  <section><p>It ensures resources are preserved for the future.</p></section>
</section>

<section>
  <section><h4><b>16) </b>Give one environmental impact of ICT devices.</h4></section>
  <section><p>High electricity consumption contributing to carbon emissions.</p></section>
</section>

<section>
  <section><h4><b>17) </b>What is meant by e-waste?</h4></section>
  <section><p>E-waste is discarded electronic equipment such as computers, phones, and batteries.</p></section>
</section>

<section>
  <section><h4><b>18) </b>What causes eye strain?</h4></section>
  <section><p>Long periods of screen use without breaks.</p></section>
  <section><p>Exposure to blue light.</p></section>
</section>

<section>
  <section><h4><b>19) </b>State one way to prevent back or neck pain.</h4></section>
  <section><p>Use an adjustable chair with correct posture support.</p></section>
</section>

<section>
  <section><h4><b>20) </b>What is a Residual Current Breaker used for?</h4></section>
  <section><p>It cuts off electricity quickly if a fault is detected.</p></section>
  <section><p>This helps prevent electric shock.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Responsibilities of companies under data protection laws.</h4></section>
  <section><p>Companies must collect data lawfully and for specific purposes.</p></section>
  <section><p>They must keep data accurate, secure, and up to date.</p></section>
  <section><p>They must allow individuals to access their data.</p></section>
</section>

<section>
  <section><h4><b>22) </b>How copyright protects creators.</h4></section>
  <section><p>It prevents unauthorised copying or sharing.</p></section>
  <section><p>It protects income and recognition.</p></section>
  <section><p>This encourages creativity and innovation.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Benefits and drawbacks of monitoring individuals.</h4></section>
  <section><p>Monitoring increases security and crime prevention.</p></section>
  <section><p>However, it reduces privacy and may lead to misuse of data.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Sustainability issues caused by ICT.</h4></section>
  <section><p>High energy consumption of devices and data centres.</p></section>
  <section><p>Increasing amounts of e-waste.</p></section>
  <section><p>Use of toxic and rare materials.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Health and safety risks of ICT.</h4></section>
  <section><p>Poor posture causes back and neck pain.</p></section>
  <section><p>Trailing wires cause trip hazards.</p></section>
  <section><p>Overheating devices can cause burns or fires.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Sharing an e-book online.</h4></section>
  <section><p>The user does not own distribution rights.</p></section>
  <section><p>Sharing without permission breaks copyright law.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Monitoring employees with ID cards.</h4></section>
  <section><p>Benefit: Improves security and attendance tracking.</p></section>
  <section><p>Drawback: Employees may feel privacy is invaded.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Why data centres move to colder countries.</h4></section>
  <section><p>Cooler climates reduce the need for air conditioning.</p></section>
  <section><p>This lowers energy use and costs.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Reducing wrist pain from laptop use.</h4></section>
  <section><p>Use ergonomic equipment.</p></section>
  <section><p>Take regular breaks and adjust posture.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Preventing electric shock incidents.</h4></section>
  <section><p>Inspect and replace damaged cables.</p></section>
  <section><p>Use RCBs and avoid exposed wiring.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Impact of digital technologies on privacy.</h4></section>
  <section><p>Large amounts of personal data are collected.</p></section>
  <section><p>Monitoring systems reduce privacy.</p></section>
  <section><p>Data protection laws give users rights.</p></section>
  <section><p>Security and privacy must be balanced.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Environmental impact of ICT.</h4></section>
  <section><p>ICT increases energy use and emissions.</p></section>
  <section><p>E-waste releases harmful chemicals.</p></section>
  <section><p>Recycling and energy-efficient devices reduce impact.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Health and safety issues and solutions.</h4></section>
  <section><p>Health risks include eye strain and RSI.</p></section>
  <section><p>Safety risks include electric shock and trip hazards.</p></section>
  <section><p>Solutions include ergonomic furniture and cable management.</p></section>
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

