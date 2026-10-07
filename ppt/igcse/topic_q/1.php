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

	<meta name='description' content='Chapter 1 – Digital Devices'>
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
					<h4>Chapter 1</h4>
					<h2>Digital Devices</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

`<section>
  <h3>Section A — Multiple-Choice Questions</h3>
</section>

<!-- Question 1 -->
<section>
  <section>
    <h4><b>1)</b> What is the main feature of a mainframe computer?</h4>
    <ol type="A">
      <li>Designed primarily for a single user running simple applications</li>
      <li>Uses a small embedded processor for controlling one specific device</li>
      <li>Handles complex tasks and supports large numbers of users simultaneously</li>
      <li>Provides portable computing using a battery-powered touchscreen</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: C.</strong> Handles complex tasks and supports large numbers of users simultaneously</p>
  </section>
</section>

<!-- Question 2 -->
<section>
  <section>
    <h4><b>2)</b> A microprocessor:</h4>
    <ol type="A">
      <li>Stores files permanently even when power is removed</li>
      <li>Processes instructions and controls what the computer does</li>
      <li>Connects a computer directly to satellites for GPS positioning</li>
      <li>Provides the physical surface on which data is stored</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: B.</strong> Processes instructions and controls what the computer does</p>
  </section>
</section>

<!-- Question 3 -->
<section>
  <section>
    <h4><b>3)</b> A SIM card is required because it:</h4>
    <ol type="A">
      <li>Stores all applications installed on the mobile device</li>
      <li>Increases the processing speed of the mobile device</li>
      <li>Identifies the mobile device/user on the mobile network</li>
      <li>Provides satellite signals used to calculate GPS coordinates</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: C.</strong> Identifies the mobile device/user on the mobile network</p>
  </section>
</section>

<!-- Question 4 -->
<section>
  <section>
    <h4><b>4)</b> Which is an example of embedded computing?</h4>
    <ol type="A">
      <li>A desktop computer used for office administration</li>
      <li>A calculator designed to perform a specific set of functions</li>
      <li>A general-purpose laptop running many different applications</li>
      <li>A mainframe computer processing transactions for thousands of users</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: B.</strong> A calculator designed to perform a specific set of functions</p>
  </section>
</section>

<!-- Question 5 -->
<section>
  <section>
    <h4><b>5)</b> GPS requires:</h4>
    <ol type="A">
      <li>Magnetic sensors installed inside the mobile device</li>
      <li>Signals from satellites to determine geographical position</li>
      <li>A wired connection to a geographic information system</li>
      <li>A barcode reader connected to a mobile network</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: B.</strong> Signals from satellites to determine geographical position</p>
  </section>
</section>

<!-- Question 6 -->
<section>
  <section>
    <h4><b>6)</b> Which device is an example of convergence?</h4>
    <ol type="A">
      <li>A calculator designed only for mathematical calculations</li>
      <li>A laptop that can fold or transform into a tablet</li>
      <li>A printer designed only to produce hard-copy documents</li>
      <li>A magnetic stripe reader used only to read payment cards</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: B.</strong> A laptop that can fold or transform into a tablet</p>
  </section>
</section>

<!-- Question 7 -->
<section>
  <section>
    <h4><b>7)</b> Which input device uses a diaphragm to detect sound?</h4>
    <ol type="A">
      <li>Touchpad</li>
      <li>Optical mouse</li>
      <li>Microphone</li>
      <li>Barcode scanner</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: C.</strong> Microphone</p>
  </section>
</section>

<!-- Question 8 -->
<section>
  <section>
    <h4><b>8)</b> Which printer generally has the lowest cost per copy for high-volume printing?</h4>
    <ol type="A">
      <li>Inkjet printer</li>
      <li>Laser printer</li>
      <li>Thermal printer</li>
      <li>Dot-matrix printer</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: B.</strong> Laser printer</p>
  </section>
</section>

<!-- Question 9 -->
<section>
  <section>
    <h4><b>9)</b> Blu-Ray discs store more data because:</h4>
    <ol type="A">
      <li>They use a larger physical disc than standard optical discs</li>
      <li>They use a shorter-wavelength violet/blue laser to read smaller data features</li>
      <li>They store all data using magnetic particles instead of optical technology</li>
      <li>They use multiple independent hard disks inside the disc</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: B.</strong> They use a shorter-wavelength violet/blue laser to read smaller data features</p>
  </section>
</section>

<!-- Question 10 -->
<section>
  <section>
    <h4><b>10)</b> Which biometric authentication method is generally considered the most secure among these options?</h4>
    <ol type="A">
      <li>Voice recognition</li>
      <li>Facial recognition</li>
      <li>Fingerprint scanner</li>
      <li>Iris scanner</li>
    </ol>
  </section>
  <section>
    <p><strong>Answer: D.</strong> Iris scanner</p>
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

