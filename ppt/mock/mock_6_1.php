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

	<meta name='description' content='Edexcel.college'>
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
					<h4>Mock 6</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section> 

    <!-- 1(a) -->
    <section>
        <section>
            <h4><b>1(a)</b> Which one of the following is primarily responsible for executing program instructions? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> CPU</p>
        </section>
    </section>

    <!-- 1(b) -->
    <section>
        <section>
            <h4><b>1(b)</b> Which type of memory is non-volatile? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> ROM</p>
        </section>
    </section>

    <!-- 1(c) -->
    <section>
        <section>
            <h4><b>1(c)</b> Explain why solid-state storage is important for use in spacecraft during space missions. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Solid-state storage is important because it has no moving mechanical parts, making it more resistant to vibration, shock, and physical damage during rocket launches and space travel.</p>
            <p>It also provides faster data access speeds and uses less power, which improves reliability and efficiency in spacecraft systems.</p>
        </section>
    </section>

    <!-- 1(d)(i) -->
    <section>
        <section>
            <h4><b>1(d)(i)</b> Explain one advantage of using embedded systems in spacecraft. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Embedded systems are designed to perform a dedicated task, such as controlling navigation or monitoring spacecraft sensors.</p>
            <p>Because they are specialised for one purpose, they operate efficiently, reliably, and with reduced chances of system failure.</p>
        </section>
    </section>

    <!-- 1(d)(ii) -->
    <section>
        <section>
            <h4><b>1(d)(ii)</b> Explain one disadvantage of embedded systems. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Embedded systems are difficult to upgrade or modify because the hardware and software are designed for a specific task.</p>
            <p>If mission requirements change or faults occur, replacing or updating the system can be expensive and time-consuming.</p>
        </section>
    </section>

    <!-- 1(e)(i) -->
    <section>
        <section>
            <h4><b>1(e)(i)</b> Storage calculation (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>256 GiB = 256 × 1024 = 262144 MiB</p>
            <p>262144 ÷ 8 = 32768 files</p>
            <p>Final Answer: 32,768 files</p>
        </section>
        <section>
            <p><b>Explanation:</b> Convert storage into the same unit before dividing.</p>
        </section>
    </section>

    <!-- 1(e)(ii) -->
    <section>
        <section>
            <h4><b>1(e)(ii)</b> State the difference between MiB and MB. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>MiB (Mebibyte) is based on the binary system and equals 1,048,576 bytes (1024 × 1024).</p>
            <p>MB (Megabyte) is based on the decimal system and equals 1,000,000 bytes.</p>
        </section>
    </section>

    <!-- 1(f) -->
    <section>
        <section>
            <h4><b>1(f)</b> Which is primary storage? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> RAM</p>
        </section>
    </section>

    <!-- 1(g) -->
    <section>
        <section>
            <h4><b>1(g)</b> Explain one benefit of data compression when transmitting data. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Data compression reduces the file size before transmission, meaning less bandwidth is required to send the data.</p>
            <p>This allows spacecraft data and telemetry information to be transmitted more quickly and efficiently across communication networks.</p>
        </section>
    </section>

    <!-- 1(h) -->
    <section>
        <section>
            <h4><b>1(h)</b> Explain one advantage of multi-core processors when processing spacecraft data in real time. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Multi-core processors can handle multiple tasks simultaneously, such as processing sensor data while managing communication systems.</p>
            <p>This improves processing speed and allows real-time analysis of spacecraft information without delays.</p>
        </section>
    </section>

    <!-- 1(i) -->
    <section>
        <section>
            <h4><b>1(i)</b> CPU Diagram (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Left Box: ALU</p>
            <p>Right Box: Cache</p>
        </section>
    </section>

    <!-- 1(j) -->
    <section>
        <section>
            <h4><b>1(j)</b> Describe how a CPU processes instructions. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The CPU first fetches instructions and data from main memory (RAM).</p>
            <p>The Control Unit then decodes the instruction to determine the operation required.</p>
            <p>Next, the instruction is executed using components such as the ALU for calculations and logical operations.</p>
            <p>Finally, the result is stored back in memory or sent to an output device.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_6_2.php">next</a></p>
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

