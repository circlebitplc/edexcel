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

	<meta name='description' content='Mock'>
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
					<h4>Mock 2</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  
    <!-- 2(a)(i) -->
    <section>
        <section>
            <h4><b>2(a)(i)</b> Convert denary -86 to sign and magnitude binary. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>86 = 1010110</p>
            <p>Sign bit = 1 (negative)</p>
            <p>Final Answer: 11010110</p>
        </section>
    </section>

    <!-- 2(a)(ii) -->
    <section>
        <section>
            <h4><b>2(a)(ii)</b> Convert two’s complement 11000110 to denary. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>MSB = 1 → negative number</p>
            <p>Invert: 00111001</p>
            <p>Add 1: 00111010</p>
            <p>Denary = 58</p>
            <p>Final Answer: -58</p>
        </section>
    </section>

    <!-- 2(a)(iii) -->
    <section>
        <section>
            <h4><b>2(a)(iii)</b> Explain the error in binary addition. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>An overflow error occurs because the result exceeds the number of bits available in the register.</p>
            <p>The value cannot be represented correctly within the fixed bit size.</p>
        </section>
    </section>

    <!-- 2(b)(i) -->
    <section>
        <section>
            <h4><b>2(b)(i)</b> Give one limitation of ASCII. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> ASCII supports a limited number of characters and cannot represent many international symbols.</p>
        </section>
    </section>

    <!-- 2(b)(ii) -->
    <section>
        <section>
            <h4><b>2(b)(ii)</b> Explain why Unicode was developed. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Unicode was developed to represent a much larger range of characters from different languages.</p>
            <p>It allows global text storage and communication using a universal encoding system.</p>
        </section>
    </section>

    <!-- 2(c) -->
    <section>
        <section>
            <h4><b>2(c)</b> Construct an expression to show image size in bytes. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>(500 × 200 × 12) ÷ 8</p>
        </section>
        <section>
            <p><b>Explanation:</b></p>
            <p>Multiply width and height to get total pixels.</p>
            <p>Multiply by colour depth to get total bits.</p>
            <p>Divide by 8 to convert bits to bytes.</p>
        </section>
    </section>

    <!-- 2(d)(i) -->
    <section>
        <section>
            <h4><b>2(d)(i)</b> Convert using run-length encoding. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> 4S3H1K3A</p>
        </section>
    </section>

    <!-- 2(d)(ii) -->
    <section>
        <section>
            <h4><b>2(d)(ii)</b> Give one other characteristic of lossy compression. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Lossy compression permanently removes some data from the file.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_2_3.php">next</a></p>
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

