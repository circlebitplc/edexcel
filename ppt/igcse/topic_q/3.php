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

	<meta name='description' content='Chapter 3 – Memory & Processors'>
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
					<h4>Chapter 3</h4>
					<h2>Memory & Processors</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions</h3>
</section>

<section>
  <section><h4><b>1) </b>RAM is described as volatile because:</h4></section>
  <section><p>Answer: B. Data is lost when power is removed</p></section>
</section>

<section>
  <section><h4><b>2) </b>Which type of memory stores instructions needed to boot the computer?</h4></section>
  <section><p>Answer: B. ROM</p></section>
</section>

<section>
  <section><h4><b>3) </b>Virtual memory is created when:</h4></section>
  <section><p>Answer: B. RAM is full</p></section>
</section>

<section>
  <section><h4><b>4) </b>Which type of ROM can be erased using ultraviolet light?</h4></section>
  <section><p>Answer: D. EPROM</p></section>
</section>

<section>
  <section><h4><b>5) </b>Flash memory is a type of:</h4></section>
  <section><p>Answer: B. EEPROM</p></section>
</section>

<section>
  <section><h4><b>6) </b>A quad-core processor contains:</h4></section>
  <section><p>Answer: C. Four CPUs</p></section>
</section>

<section>
  <section><h4><b>7) </b>Processor speed is measured in:</h4></section>
  <section><p>Answer: B. Hertz</p></section>
</section>

<section>
  <section><h4><b>8) </b>The processor cycle includes:</h4></section>
  <section><p>Answer: B. Fetch, decode, execute</p></section>
</section>

<section>
  <section><h4><b>9) </b>A bigger RAM mainly benefits users by:</h4></section>
  <section><p>Answer: C. Allowing more programs to run at once</p></section>
</section>

<section>
  <section><h4><b>10) </b>Which memory type is non-volatile?</h4></section>
  <section><p>Answer: C. ROM</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define RAM.</h4></section>
  <section><p>RAM (Random Access Memory) is the main memory used to temporarily store data and instructions that are currently being used by the processor.</p></section>
</section>

<section>
  <section><h4><b>12) </b>What is virtual memory used for?</h4></section>
  <section><p>Virtual memory is used to provide extra memory when RAM is full by using part of secondary storage as temporary memory.</p></section>
</section>

<section>
  <section><h4><b>13) </b>Why does virtual memory slow down a computer?</h4></section>
  <section><p>Virtual memory uses secondary storage, which is much slower than RAM, causing delays when data is accessed.</p></section>
</section>

<section>
  <section><h4><b>14) </b>Define ROM.</h4></section>
  <section><p>ROM (Read Only Memory) is non-volatile memory that permanently stores instructions needed to start up the computer.</p></section>
</section>

<section>
  <section><h4><b>15) </b>Give one example of a single-use computer that uses ROM.</h4></section>
  <section><p>A washing machine controller.</p></section>
</section>

<section>
  <section><h4><b>16) </b>What does PROM stand for?</h4></section>
  <section><p>Programmable Read Only Memory.</p></section>
</section>

<section>
  <section><h4><b>17) </b>Explain the meaning of processor cycle.</h4></section>
  <section><p>The processor cycle is the repeated process where the CPU fetches an instruction, decodes it, and then executes it.</p></section>
</section>

<section>
  <section><h4><b>18) </b>Why is flash memory suitable for portable devices?</h4></section>
  <section><p>Flash memory is non-volatile, uses low power, is compact, and is resistant to physical shock.</p></section>
</section>

<section>
  <section><h4><b>19) </b>State one difference between RAM and ROM.</h4></section>
  <section><p>RAM is volatile, while ROM is non-volatile.</p></section>
</section>

<section>
  <section><h4><b>20) </b>What does a processor's clock speed represent?</h4></section>
  <section><p>Clock speed represents the number of instructions the processor can process per second.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>Explain what happens when RAM becomes full.</h4></section>
  <section><p>When RAM becomes full, the operating system moves inactive data from RAM to secondary storage.</p></section>
  <section><p>This area is called virtual memory.</p></section>
  <section><p>Data is swapped back into RAM when needed, but performance is slower because secondary storage is slower.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Compare RAM and ROM.</h4></section>
  <section><p>RAM is volatile, can be upgraded, and stores data currently in use.</p></section>
  <section><p>ROM is non-volatile, cannot usually be upgraded, and stores boot instructions.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Describe how PROM, EPROM, and EEPROM work.</h4></section>
  <section><p>PROM can be programmed once after manufacture.</p></section>
  <section><p>EPROM can be erased using ultraviolet light and reprogrammed.</p></section>
  <section><p>EEPROM can be erased and rewritten electronically without removal from the device.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Explain why more processor cores improve performance.</h4></section>
  <section><p>Multiple cores allow the processor to perform several tasks at the same time.</p></section>
  <section><p>This improves multitasking and overall system performance.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Describe the fetch–decode–execute cycle.</h4></section>
  <section><p>The processor fetches an instruction from memory.</p></section>
  <section><p>It decodes the instruction to understand the required action.</p></section>
  <section><p>It then executes the instruction.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>4 GB RAM laptop running many apps.</h4></section>
  <section><p>Multiple applications use large amounts of RAM, causing it to fill up.</p></section>
  <section><p>The system uses virtual memory, which is slower.</p></section>
  <section><p>Upgrading RAM or closing unused applications would improve performance.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Why is flash memory suitable for a smartwatch?</h4></section>
  <section><p>Flash memory is non-volatile, compact, and energy-efficient.</p></section>
  <section><p>This makes it ideal for small, battery-powered devices.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Should RAM or ROM be upgraded for speed?</h4></section>
  <section><p>RAM should be upgraded.</p></section>
  <section><p>More RAM improves multitasking and reduces reliance on virtual memory.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Benefit of an 8-core processor.</h4></section>
  <section><p>Multiple cores allow tasks to be processed simultaneously, improving performance.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Two causes of freezing while loading programs.</h4></section>
  <section><p>Insufficient RAM causing heavy use of virtual memory.</p></section>
  <section><p>A slow or outdated processor.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>How RAM, storage, and processor work together.</h4></section>
  <section><p>Applications are stored in secondary storage.</p></section>
  <section><p>When opened, they are loaded into RAM.</p></section>
  <section><p>The processor fetches instructions from RAM and executes them.</p></section>
  <section><p>Results may be stored back in RAM or saved to secondary storage.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Compare System A and System B.</h4></section>
  <section><p>System B performs better.</p></section>
  <section><p>More RAM allows more applications to run simultaneously.</p></section>
  <section><p>More processor cores allow parallel processing.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Evolution of memory technologies.</h4></section>
  <section><p>ROM is permanently programmed and cannot be changed.</p></section>
  <section><p>PROM allows one-time programming.</p></section>
  <section><p>EPROM allows reprogramming using UV light.</p></section>
  <section><p>EEPROM allows electronic rewriting.</p></section>
  <section><p>Flash memory improves speed, power efficiency, and storage capacity.</p></section>
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

