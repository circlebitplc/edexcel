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

	<meta name='description' content='MOCK – 6'>
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
					<h4>MOCK</h4>
					<h2>6</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <section class="question-block">

  <section class="qa">
    <h4><b>1(a)</b> Which one of the following is primarily responsible for executing program instructions? [1]</h4>
    <p><b>Answer:</b> CPU</p>
  </section>

  <section class="qa">
    <h4><b>1(b)</b> Which type of memory is non-volatile? [1]</h4>
    <p><b>Answer:</b> ROM</p>
  </section>

  <section class="qa">
    <h4><b>1(c)</b> Explain why solid-state storage is important for use in spacecraft during space missions. [2]</h4>
    <p><b>Answer:</b> Solid-state storage has no moving parts, making it more durable and resistant to vibration during launch and space travel. It is also faster and more reliable, reducing the risk of data loss in critical missions.</p>
  </section>

  <section class="qa">
    <h4><b>1(d)(i)</b> Explain one advantage of using embedded systems in spacecraft. [2]</h4>
    <p><b>Answer:</b> Embedded systems are dedicated to a specific task, so they are more efficient and reliable. This ensures real-time processing, which is essential for spacecraft operations.</p>
  </section>

  <section class="qa">
    <h4><b>1(d)(ii)</b> Explain one disadvantage of embedded systems. [2]</h4>
    <p><b>Answer:</b> Embedded systems are difficult to modify or upgrade because they are built for a single purpose. If the system fails, it may require complete replacement rather than repair.</p>
  </section>

  <section class="qa">
    <h4><b>1(e)(i)</b> A storage device has a capacity of 256 GiB. Each file has a size of 8 MiB. Construct an expression and show your working to calculate the number of files stored. [2]</h4>
    <p><b>Answer:</b><br>
    256 GiB = 256 × 1024 = 262144 MiB<br>
    Number of files = 262144 ÷ 8 = 32768 files
    </p>
  </section>

  <section class="qa">
    <h4><b>1(e)(ii)</b> State the difference between MiB and MB. [2]</h4>
    <p><b>Answer:</b> MiB is based on binary (1024 bytes), while MB is based on decimal (1000 bytes). Therefore, 1 MiB = 1024 KB, but 1 MB = 1000 KB.</p>
  </section>

  <section class="qa">
    <h4><b>1(f)</b> Which is primary storage? [1]</h4>
    <p><b>Answer:</b> RAM</p>
  </section>

  <section class="qa">
    <h4><b>1(g)</b> Explain one benefit of data compression when transmitting data. [2]</h4>
    <p><b>Answer:</b> Data compression reduces file size, allowing data to be transmitted faster. It also uses less bandwidth, making communication more efficient.</p>
  </section>

  <section class="qa">
    <h4><b>1(h)</b> Explain one advantage of multi-core processors when processing spacecraft data in real time. [2]</h4>
    <p><b>Answer:</b> Multi-core processors can process multiple tasks simultaneously, improving performance. This allows real-time data processing without delays.</p>
  </section>

  <section class="qa">
    <h4><b>1(i)</b> The diagram shows a CPU. Label two parts of a CPU diagram. [2]</h4>
    <p><b>Answer:</b> ALU, Cache</p>
  </section>

  <section class="qa">
    <h4><b>1(j)</b> Describe how a CPU processes instructions. [4]</h4>
    <p><b>Answer:</b><br>
    The CPU fetches the instruction from memory.<br>
    It then decodes the instruction to understand what action is required.<br>
    The instruction is executed by the ALU or other components.<br>
    The result is stored in registers or memory.
    </p>
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

