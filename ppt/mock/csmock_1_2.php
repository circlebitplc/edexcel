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

	<meta name='description' content='Mock 1'>
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
					<h4>Mock 1</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  
    <!-- 2(a)(i) -->
    <section>
        <section>
            <h4><b>2(a)(i)</b> Binary unit equivalents (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>2000 bytes = 2 kilobytes</p>
            <p>2000 terabytes = 2 petabytes</p>
            <p>16 bits = 2 bytes</p>
            <p>4 nibbles = 2 bytes</p>
        </section>
    </section>

    <!-- 2(a)(ii) -->
    <section>
        <section>
            <h4><b>2(a)(ii)</b> Complete the binary addition. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>11111010 + 10010101 = 1 1000 1111</p>
        </section>
    </section>

    <!-- 2(a)(iii) -->
    <section>
        <section>
            <h4><b>2(a)(iii)</b> Convert denary 221 into 8-bit binary. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>221 = 128 + 64 + 16 + 8 + 4 + 1</p>
            <p>Binary: 11011101</p>
        </section>
    </section>

    <!-- 2(a)(iv) -->
    <section>
        <section>
            <h4><b>2(a)(iv)</b> Identify how many unique values can be represented by 4 bits. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> 16</p>
        </section>
    </section>

    <!-- 2(a)(v) -->
    <section>
        <section>
            <h4><b>2(a)(v)</b> Convert hexadecimal E2F into denary. (3)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>(14 × 16²) + (2 × 16¹) + (15 × 16⁰)</p>
            <p>3584 + 32 + 15 = 3631</p>
            <p>Final Answer: 3631</p>
        </section>
    </section>

    <!-- 2(a)(vi) -->
    <section>
        <section>
            <h4><b>2(a)(vi)</b> Perform a binary shift of 3 places right on 10001110 and show denary result. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>10001110 → 00010001</p>
            <p>Denary: 17</p>
        </section>
    </section>

    <!-- 2(b) -->
    <section>
        <section>
            <h4><b>2(b)</b> CPU Components and Registers (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Stores address of next instruction – Program Counter (PC)</p>
            <p>Control Unit – Controls execution of instructions</p>
            <p>Stores address of data to fetch/store – Memory Address Register (MAR)</p>
            <p>Performs calculations and logic – ALU</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_1_3.php">next</a></p>
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

