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

	<meta name='description' content='Chapter 12 – The Cloud'>
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
					<h4>Chapter 12</h4>
					<h2>The Cloud</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple Choice Questions (MCQs)</h3>
</section>

<section>
  <section><h4><b>1) </b>Hosted applications run on:</h4></section>
  <section><p>Answer: B. Online servers</p></section>
</section>

<section>
  <section><h4><b>2) </b>Locally installed software:</h4></section>
  <section><p>Answer: B. Is unaffected by bandwidth and latency</p></section>
</section>

<section>
  <section><h4><b>3) </b>A drawback of hosted applications is:</h4></section>
  <section><p>Answer: B. They require an internet connection</p></section>
</section>

<section>
  <section><h4><b>4) </b>Online data storage allows:</h4></section>
  <section><p>Answer: A. Automatic backups</p></section>
</section>

<section>
  <section><h4><b>5) </b>A drawback of cloud storage is:</h4></section>
  <section><p>Answer: A. Requires internet connection</p></section>
</section>

<section>
  <section><h4><b>6) </b>Cloud-based services are:</h4></section>
  <section><p>Answer: C. Online software and storage services</p></section>
</section>

<section>
  <section><h4><b>7) </b>Bandwidth and latency affect:</h4></section>
  <section><p>Answer: B. Hosted applications</p></section>
</section>

<section>
  <section><h4><b>8) </b>Which of the following is an example of a hosted application?</h4></section>
  <section><p>Answer: B. Google Docs</p></section>
</section>

<section>
  <section><h4><b>9) </b>Which is a benefit of online storage?</h4></section>
  <section><p>Answer: B. Saves local storage space</p></section>
</section>

<section>
  <section><h4><b>10) </b>Locally installed software:</h4></section>
  <section><p>Answer: B. Is limited to one device</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define hosted application.</h4></section>
  <section><p>A hosted application is software that runs on remote servers.</p></section>
  <section><p>It is accessed through the internet using a web browser.</p></section>
</section>

<section>
  <section><h4><b>12) </b>Define locally installed software.</h4></section>
  <section><p>Locally installed software is installed directly on a user’s device.</p></section>
  <section><p>It runs using the device’s own hardware.</p></section>
</section>

<section>
  <section><h4><b>13) </b>What is bandwidth?</h4></section>
  <section><p>Bandwidth is the amount of data that can be transferred.</p></section>
  <section><p>It is measured per second over an internet connection.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Why are hosted apps affected by latency?</h4></section>
  <section><p>Hosted apps rely on communication with online servers.</p></section>
  <section><p>Delays in data transmission reduce responsiveness.</p></section>
</section>

<section>
  <section><h4><b>15) </b>One benefit and one drawback of hosted applications.</h4></section>
  <section><p>Benefit: Accessible from any device with internet access.</p></section>
  <section><p>Drawback: Cannot be used without an internet connection.</p></section>
</section>

<section>
  <section><h4><b>16) </b>What is meant by online data storage?</h4></section>
  <section><p>Online data storage stores files on remote servers.</p></section>
  <section><p>Files are accessed through the internet.</p></section>
</section>

<section>
  <section><h4><b>17) </b>State one disadvantage of cloud storage.</h4></section>
  <section><p>Data cannot be accessed without an internet connection.</p></section>
</section>

<section>
  <section><h4><b>18) </b>Why is online data storage considered scalable?</h4></section>
  <section><p>Storage capacity can be increased or reduced easily.</p></section>
  <section><p>No physical hardware changes are required.</p></section>
</section>

<section>
  <section><h4><b>19) </b>What impact has the cloud had on software companies?</h4></section>
  <section><p>Companies now use subscription-based models.</p></section>
  <section><p>Physical software distribution is reduced.</p></section>
</section>

<section>
  <section><h4><b>20) </b>How does cloud storage support collaboration?</h4></section>
  <section><p>Multiple users can access shared files.</p></section>
  <section><p>Files can be edited in real time from different locations.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Compare hosted applications and locally installed applications.</h4></section>
  <section><p>Hosted applications run on online servers.</p></section>
  <section><p>They require an internet connection.</p></section>
  <section><p>Locally installed applications run on the user’s device.</p></section>
  <section><p>They can operate without internet access.</p></section>
</section>

<section>
  <section><h4><b>22) </b>How bandwidth and latency affect cloud services.</h4></section>
  <section><p>Low bandwidth slows data transfer.</p></section>
  <section><p>High latency causes lag.</p></section>
  <section><p>Both reduce performance of hosted applications.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Benefits and drawbacks of online data storage.</h4></section>
  <section><p>Benefits include remote access and automatic backups.</p></section>
  <section><p>Scalability allows flexible storage capacity.</p></section>
  <section><p>Drawbacks include internet dependency and security risks.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Cloud computing and examples.</h4></section>
  <section><p>Cloud computing uses online servers for storage and software.</p></section>
  <section><p>Examples include online document editors and cloud storage services.</p></section>
</section>

<section>
  <section><h4><b>25) </b>How collaboration is enabled through cloud software.</h4></section>
  <section><p>Users work on the same file simultaneously.</p></section>
  <section><p>Changes are saved automatically.</p></section>
  <section><p>Permissions control access levels.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Inability to access a hosted app.</h4></section>
  <section><p>Hosted apps require continuous internet access.</p></section>
  <section><p>Poor connectivity prevents server communication.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Choosing Google Docs over offline Word.</h4></section>
  <section><p>Files are accessible from any device.</p></section>
  <section><p>Multiple users can collaborate in real time.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Reducing local storage usage.</h4></section>
  <section><p>Files are stored online instead of locally.</p></section>
  <section><p>This frees up hard drive space.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Simultaneous editing of shared files.</h4></section>
  <section><p>Real-time collaboration synchronises changes instantly.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Storing sensitive data online.</h4></section>
  <section><p>Risk: Data may be accessed by hackers.</p></section>
  <section><p>Precaution: Use strong passwords and encryption.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Cloud-based services for individuals.</h4></section>
  <section><p>Advantages include accessibility and automatic updates.</p></section>
  <section><p>Local storage requirements are reduced.</p></section>
  <section><p>Disadvantages include privacy concerns and internet reliance.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Cloud services and productivity in organizations.</h4></section>
  <section><p>Remote working and collaboration are enabled.</p></section>
  <section><p>Hardware and maintenance costs are reduced.</p></section>
  <section><p>Security and downtime risks must be managed.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Impact of cloud technology on services and communities.</h4></section>
  <section><p>Services are accessible globally.</p></section>
  <section><p>Communities collaborate in real time.</p></section>
  <section><p>Content sharing and communication are improved.</p></section>
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

