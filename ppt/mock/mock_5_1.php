<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Mock 5'>
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
					<h4>Mock 5</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 
  <section>
        <section>
            <h4><b>1(a)</b> Which one of the following is a wireless communication method? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Infrared</p>
        </section>
        <section>
            <p><b>Explanation:</b> Infrared transmits data using light waves without cables, so it is wireless.</p>
        </section>
    </section>
	<section>
        <section>
            <h4><b>1(b)</b> Which type of memory is volatile? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> RAM</p>
        </section>
        <section>
            <p><b>Explanation:</b> RAM loses all stored data when the power is turned off.</p>
        </section>
    </section>
<section>
        <section>
            <h4><b>1(c)</b> Give one advantage of using portable devices in emergency situations. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Easy to carry and use in different locations</p>
        </section>
        <section>
            <p><b>Explanation:</b> This allows workers to access and input data directly in affected areas.</p>
        </section>
    </section>

    <!-- 1(d)(i) -->
    <section>
        <section>
            <h4><b>1(d)(i)</b> Give one reason why portable devices may be less secure in emergency situations. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Portable devices can be easily lost or stolen, which may allow unauthorized users to access sensitive data.</p>
        </section>
        <section>
            <p><b>Explanation:</b> Their small size and use in chaotic environments increase the risk of theft or loss.</p>
        </section>
    </section>

    <!-- 1(d)(ii) -->
    <section>
        <section>
            <h4><b>1(d)(ii)</b> Explain one disadvantage of using tablets. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Tablets have lower processing power than desktop computers, so they may run complex applications slowly.</p>
        </section>
        <section>
            <p><b>Explanation:</b> This can reduce efficiency during emergency operations.</p>
        </section>
    </section>

    <!-- 1(e)(i) -->
    <section>
        <section>
            <h4><b>1(e)(i)</b> Construct an expression and calculate number of files (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>64 GiB = 64 × 1024 = 65536 MiB</p>
            <p>65536 ÷ 4 = 16384 files</p>
        </section>
        <section>
            <p><b>Explanation:</b> Convert storage into the same unit before dividing.</p>
        </section>
    </section>

    <!-- 1(e)(ii) -->
    <section>
        <section>
            <h4><b>1(e)(ii)</b> State the difference between KiB and Kb (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>KiB = 1024 bytes</p>
            <p>Kb = 1000 bits</p>
        </section>
        <section>
            <p><b>Explanation:</b> KiB measures storage, while Kb measures data transmission.</p>
        </section>
    </section>

    <!-- 1(f) -->
    <section>
        <section>
            <h4><b>1(f)</b> Which is secondary storage? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Hard disk</p>
        </section>
        <section>
            <p><b>Explanation:</b> It stores data permanently even when power is off.</p>
        </section>
    </section>

    <!-- 1(g) -->
    <section>
        <section>
            <h4><b>1(g)</b> Explain one benefit of data encryption when transmitting data (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Encryption converts data into unreadable form so that if intercepted, unauthorized users cannot understand it.</p>
        </section>
        <section>
            <p><b>Explanation:</b> This protects sensitive information during transmission.</p>
        </section>
    </section>

    <!-- 1(h) -->
    <section>
        <section>
            <h4><b>1(h)</b> Describe how cache memory improves processor performance (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Cache stores frequently used data close to the CPU, reducing access time and speeding up processing.</p>
        </section>
        <section>
            <p><b>Explanation:</b> The CPU does not need to access slower RAM as often.</p>
        </section>
    </section>

    <!-- 1(i) -->
    <section>
        <section>
            <h4><b>1(i)</b> Name the printers (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Inkjet printer</p>
            <p>• Dot matrix printer</p>
        </section>
    </section>

    <!-- 1(j) -->
    <section>
        <section>
            <h4><b>1(j)</b> Describe how satellite monitoring systems are used (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Satellites capture real-time images and environmental data from affected areas and transmit it to control centres.</p>
            <p>This allows organisations to monitor damage, track weather conditions, and identify areas needing urgent help.</p>
            <p>The data is analysed to support decision-making and coordinate rescue operations efficiently.</p>
        </section>
        <section>
            <p><b>Explanation:</b> Covers data collection → transmission → use → impact.</p>
        </section>
    </section>
 <section>
            <p><a href="<?= $dirBase ?>/mock_5_2.php">next</a></p>
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

