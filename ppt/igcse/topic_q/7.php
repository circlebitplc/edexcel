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

	<meta name='description' content='Chapter 7 – Impact of the Internet'>
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
					<h4>Chapter 7</h4>
					<h2>Impact of the Internet</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions (MCQs)</h3>
</section>

<section>
  <section><h4><b>1) </b>Which of the following is an impact of the internet on individuals?</h4></section>
  <section><p>Answer: B. Easier access to information and services</p></section>
</section>

<section>
  <section><h4><b>2) </b>Collaborative working means:</h4></section>
  <section><p>Answer: B. Working together in real time via the internet</p></section>
</section>

<section>
  <section><h4><b>3) </b>Flexible working allows employees to:</h4></section>
  <section><p>Answer: C. Work at times that suit them</p></section>
</section>

<section>
  <section><h4><b>4) </b>Digital footprint refers to:</h4></section>
  <section><p>Answer: B. All data a user creates online</p></section>
</section>

<section>
  <section><h4><b>5) </b>A major drawback of working from home for individuals is:</h4></section>
  <section><p>Answer: B. Lack of social interaction</p></section>
</section>

<section>
  <section><h4><b>6) </b>Organizations benefit from online working because:</h4></section>
  <section><p>Answer: B. They can access global markets</p></section>
</section>

<section>
  <section><h4><b>7) </b>The digital divide refers to:</h4></section>
  <section><p>Answer: A. A gap between internet users and non users</p></section>
</section>

<section>
  <section><h4><b>8) </b>User generated reference sites can be unreliable because:</h4></section>
  <section><p>Answer: C. Few experts verify content</p></section>
</section>

<section>
  <section><h4><b>9) </b>Which is a positive social impact of the internet?</h4></section>
  <section><p>Answer: B. Better media representation</p></section>
</section>

<section>
  <section><h4><b>10) </b>Social media may negatively affect socialising because:</h4></section>
  <section><p>Answer: C. It can isolate people from real communities</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define collaborative working.</h4></section>
  <section><p>Collaborative working is when people work together on the same task or document in real time.</p></section>
  <section><p>This is done using the internet and online tools.</p></section>
</section>

<section>
  <section><h4><b>12) </b>What is flexible working?</h4></section>
  <section><p>Flexible working allows employees to choose when and where they work.</p></section>
  <section><p>This is often supported by internet access and digital tools.</p></section>
</section>

<section>
  <section><h4><b>13) </b>State one safety rule for staying safe online.</h4></section>
  <section><p>Do not share personal information publicly online.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Define digital footprint.</h4></section>
  <section><p>A digital footprint is the record of all activities and information a person leaves online.</p></section>
  <section><p>This may be intentional or unintentional.</p></section>
</section>

<section>
  <section><h4><b>15) </b>Give one benefit and one drawback of working from home for individuals.</h4></section>
  <section><p>Benefit: Saves travel time and costs.</p></section>
  <section><p>Drawback: Reduced social interaction with colleagues.</p></section>
</section>

<section>
  <section><h4><b>16) </b>What is meant by the term global workforce?</h4></section>
  <section><p>A global workforce is when employees work from different countries.</p></section>
  <section><p>This is made possible using the internet and digital communication tools.</p></section>
</section>

<section>
  <section><h4><b>17) </b>State two reasons why organizations face more security risks with remote workers.</h4></section>
  <section><p>Employees may use unsecured home networks.</p></section>
  <section><p>Devices may be shared with other people.</p></section>
</section>

<section>
  <section><h4><b>18) </b>What is the digital divide?</h4></section>
  <section><p>The digital divide is the gap between people who have access to the internet and technology.</p></section>
  <section><p>It also includes those who do not have such access.</p></section>
</section>

<section>
  <section><h4><b>19) </b>Give one positive impact of the internet on society.</h4></section>
  <section><p>Improved communication between people worldwide.</p></section>
</section>

<section>
  <section><h4><b>20) </b>Give one example of how people without internet access communicate.</h4></section>
  <section><p>Using telephone calls or face-to-face communication.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Explain three impacts of the internet on employment.</h4></section>
  <section><p>The internet allows people to work remotely from home.</p></section>
  <section><p>It enables global job opportunities and outsourcing.</p></section>
  <section><p>Some traditional jobs have declined due to automation and online services.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Compare collaborative working and flexible working.</h4></section>
  <section><p>Collaborative working focuses on people working together online in real time.</p></section>
  <section><p>Flexible working focuses on when and where an individual works.</p></section>
  <section><p>Both rely on internet access and digital communication tools.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Explain how personal information can be misused online and how users can stay safe.</h4></section>
  <section><p>Personal information can be used for identity theft, scams, or stalking.</p></section>
  <section><p>Users can stay safe by using privacy settings.</p></section>
  <section><p>Strong passwords and avoiding oversharing also reduce risk.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Describe how the internet has changed the way organizations communicate.</h4></section>
  <section><p>Organizations use email, video conferencing, and instant messaging.</p></section>
  <section><p>Communication is faster, cheaper, and global.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Explain the causes of the digital divide.</h4></section>
  <section><p>Lack of internet infrastructure.</p></section>
  <section><p>High cost of devices and data.</p></section>
  <section><p>Low digital skills or education.</p></section>
  <section><p>Geographic location such as rural areas.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Why is posting personal information publicly risky?</h4></section>
  <section><p>Personal information can be accessed by strangers.</p></section>
  <section><p>This may lead to identity theft or cyberbullying.</p></section>
  <section><p>It can also cause physical safety risks.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Why may a remote employee feel disconnected?</h4></section>
  <section><p>There is less face-to-face interaction.</p></section>
  <section><p>This reduces teamwork, motivation, and belonging.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Benefit and risk of remote access and VPNs.</h4></section>
  <section><p>Benefit: Secure access to company systems from anywhere.</p></section>
  <section><p>Risk: Stolen login details could allow attackers access.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Effects of no internet access on education and employment.</h4></section>
  <section><p>Students cannot access online learning resources.</p></section>
  <section><p>People cannot apply for jobs or work remotely.</p></section>
</section>

<section>
  <section><h4><b>30) </b>How user generated content spreads misinformation.</h4></section>
  <section><p>User generated content lacks expert verification.</p></section>
  <section><p>False information spreads quickly without fact-checking.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Impact of the internet on individuals.</h4></section>
  <section><p>The internet provides easy access to information and services.</p></section>
  <section><p>It offers entertainment such as streaming and gaming.</p></section>
  <section><p>It enables remote and flexible working.</p></section>
  <section><p>Social interaction has increased online but may reduce face-to-face contact.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Benefits and drawbacks of online working from home.</h4></section>
  <section><p>Individuals save travel time and costs.</p></section>
  <section><p>They may feel isolated or distracted.</p></section>
  <section><p>Organizations reduce office costs.</p></section>
  <section><p>They face security risks and reduced team interaction.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Impact of the digital divide on society.</h4></section>
  <section><p>People without internet access struggle to find jobs.</p></section>
  <section><p>Students miss online learning opportunities.</p></section>
  <section><p>Communities become culturally and socially isolated.</p></section>
  <section><p>This increases inequality in society.</p></section>
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

