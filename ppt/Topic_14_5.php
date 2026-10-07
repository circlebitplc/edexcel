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

	<meta name='description' content='Topic 14 – using it systems in organization'>
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
					<h4>Topic 14</h4>
					<h2>using it systems in organization </h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

	<section>
  <h3>SECTION E – Expert Systems, ITS & Emerging Systems</h3>
</section>

<section>
  <section>
    <h4><b>49) </b>Define the term expert system.</h4>
  </section>
  <section>
    <p>An expert system is a computer-based system that uses a knowledge base and an inference engine to replicate the decision-making ability of a human expert in a specific domain. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>50) </b>State two components of an expert system.</h4>
  </section>
  <section>
    <p>Two components of an expert system are:</p>
  </section>
  <section>
    <p>1. Knowledge base</p>
  </section>
  <section>
    <p>2. Inference engine [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>51) </b>Explain the role of the knowledge base in an expert system.</h4>
  </section>

  <section>
    <p>The knowledge base is the part of an expert system that stores expert knowledge required to solve problems. [3]</p>
  </section>

  <section>
    <p>It contains facts, rules, and relationships that represent the knowledge of human experts in a specific domain. This information is usually stored in the form of IF–THEN rules, facts, or decision trees.</p>
  </section>

  <section>
    <p>The knowledge base provides the information that the inference engine uses to reason and make decisions. Without an accurate and up-to-date knowledge base, the expert system would be unable to produce correct or reliable conclusions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>52) </b>Explain the role of the inference engine in an expert system.</h4>
  </section>

  <section>
    <p>The inference engine is the part of an expert system that applies logical reasoning to the knowledge base to reach conclusions. [3]</p>
  </section>

  <section>
    <p>It works by comparing the facts entered by the user with the rules stored in the knowledge base. Using reasoning methods such as forward chaining or backward chaining, the inference engine determines which rules apply.</p>
  </section>

  <section>
    <p>The inference engine controls the decision-making process of the expert system, ensuring that expert knowledge is used correctly and consistently.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>53) </b>Explain how expert systems are used in organizations.</h4>
  </section>

  <section>
    <p>Expert systems are used in organisations to support decision-making by replicating the knowledge and reasoning of human experts. [4]</p>
  </section>

  <section>
    <p>They use a knowledge base and an inference engine to analyse information provided by users and produce recommendations, diagnoses, or decisions similar to those of a human expert.</p>
  </section>

  <section>
    <p>Organisations use expert systems in areas such as medical diagnosis, fault diagnosis, financial decision-making, and customer support.</p>
  </section>

  <section>
    <p>Expert systems help save time and costs, make expert knowledge widely available, and support less experienced staff by guiding them through complex decisions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>54) </b>Explain two advantages of using expert systems.</h4>
  </section>

  <section>
    <p><b>Consistent and Accurate Decision-Making</b></p>
  </section>

  <section>
    <p>Expert systems apply the same rules and knowledge every time, ensuring decisions are consistent and not affected by fatigue or bias. This improves accuracy and ensures best-practice procedures are followed reliably.</p>
  </section>

  <section>
    <p><b>Availability and Knowledge Retention</b></p>
  </section>

  <section>
    <p>Expert systems can be used at any time and store expert knowledge permanently, making expertise widely accessible and reducing dependence on individual specialists. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>55) </b>Explain two disadvantages of using expert systems.</h4>
  </section>

  <section>
    <p><b>High Development and Maintenance Costs</b></p>
  </section>

  <section>
    <p>Expert systems are expensive to develop and maintain because specialised knowledge engineers and ongoing updates are required.</p>
  </section>

  <section>
    <p><b>Lack of Human Judgment and Flexibility</b></p>
  </section>

  <section>
    <p>Expert systems can only make decisions based on programmed knowledge and cannot apply intuition or creativity in unexpected situations. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>56) </b>Define the term Intelligent Transport System (ITS).</h4>
  </section>
  <section>
    <p>An Intelligent Transport System (ITS) is the use of information and communication technologies to monitor, manage, and control transport networks in order to improve traffic flow, safety, and efficiency. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>57) </b>Explain how ITS improves traffic management.</h4>
  </section>

  <section>
    <p>Intelligent Transport Systems (ITS) improve traffic management by using real-time data and automated control. [4]</p>
  </section>

  <section>
    <p>ITS collects live data from sensors, cameras, GPS devices, and traffic signals to monitor congestion and incidents.</p>
  </section>

  <section>
    <p>This data is analysed to adjust traffic signals, lane usage, and speed limits in real time, reducing congestion.</p>
  </section>

  <section>
    <p>ITS also enables faster detection of accidents, allowing quick responses that reduce delays and improve journey times.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>58) </b>Explain two benefits of using ITS.</h4>
  </section>

  <section>
    <p><b>Improved Traffic Flow and Reduced Congestion</b></p>
  </section>

  <section>
    <p>ITS manages traffic dynamically using real-time data, reducing delays and improving journey times.</p>
  </section>

  <section>
    <p><b>Enhanced Road Safety</b></p>
  </section>

  <section>
    <p>ITS detects accidents and hazards quickly, improving emergency response and reducing the likelihood of accidents. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>59) </b>Explain two challenges of implementing ITS.</h4>
  </section>

  <section>
    <p><b>High Implementation and Maintenance Costs</b></p>
  </section>

  <section>
    <p>ITS requires significant investment in hardware, software, communication networks, and ongoing maintenance.</p>
  </section>

  <section>
    <p><b>Integration and Technical Complexity</b></p>
  </section>

  <section>
    <p>Integrating ITS with existing infrastructure can be complex and may reduce system effectiveness if poorly implemented. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>60) </b>Define the term emerging technology.</h4>
  </section>
  <section>
    <p>Emerging technology is a new or developing technology that is still evolving and not yet widely adopted, but has the potential to significantly impact organisations and society. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>61) </b>Explain how artificial intelligence (AI) is used as an emerging technology in organizations.</h4>
  </section>

  <section>
    <p>Artificial intelligence (AI) is used in organisations to analyse data, automate decision-making, and improve efficiency. [4]</p>
  </section>

  <section>
    <p>AI processes large volumes of data to identify patterns, support forecasting, and improve decision-making.</p>
  </section>

  <section>
    <p>AI automates routine tasks such as chatbots and data processing, reducing costs and errors.</p>
  </section>

  <section>
    <p>AI is also used in fraud detection, cybersecurity, and quality control to improve reliability and security.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>62) </b>Explain two benefits of using emerging technologies in organizations.</h4>
  </section>

  <section>
    <p><b>Increased Efficiency and Productivity</b></p>
  </section>

  <section>
    <p>Emerging technologies automate tasks and improve accuracy, saving time and improving productivity.</p>
  </section>

  <section>
    <p><b>Competitive Advantage and Innovation</b></p>
  </section>

  <section>
    <p>Adopting new technologies allows organisations to innovate, respond to market changes, and gain a competitive advantage. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>63) </b>Explain two risks of using emerging technologies.</h4>
  </section>

  <section>
    <p><b>Security and Privacy Risks</b></p>
  </section>

  <section>
    <p>Emerging technologies may contain security vulnerabilities, increasing the risk of data breaches and privacy violations.</p>
  </section>

  <section>
    <p><b>Lack of Reliability and Stability</b></p>
  </section>

  <section>
    <p>Because they are still developing, emerging technologies may be unstable or unsupported, causing operational disruptions. [4]</p>
  </section>
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

