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
    <!-- 4(a) -->
    <section>
        <section>
            <h4><b>4(a)</b> Explain the purpose of a trace table. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A trace table is used to track the values of variables step-by-step while an algorithm executes.</p>
            <p>This helps programmers test, debug, and identify logic errors in algorithms.</p>
        </section>
    </section>

    <!-- 4(b) -->
    <section>
        <section>
            <h4><b>4(b)</b> Describe a record. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A record is a data structure that stores multiple related fields about one entity.</p>
            <p>Each field can store different data types such as text, integers, or dates.</p>
            <p>Example: A student record may contain name, age, and grade.</p>
        </section>
    </section>

    <!-- 4(c) -->
    <section>
        <section>
            <h4><b>4(c)</b> Complete the flowchart (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>INPUT number</p>
            <p>IF number MOD 2 = 0 THEN</p>
            <p>&nbsp;&nbsp;OUTPUT "Even"</p>
            <p>ELSE</p>
            <p>&nbsp;&nbsp;OUTPUT "Odd"</p>
            <p>ENDIF</p>
        </section>
    </section>

    <!-- 4(d) -->
    <section>
        <section>
            <h4><b>4(d)</b> Describe differences between linear and binary search. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Linear search checks each item in a list one by one until the target value is found or the end of the list is reached.</p>
            <p>It can work on unsorted lists but may be slower for large datasets.</p>

            <p>Binary search repeatedly divides a sorted list into halves to locate the target value more efficiently.</p>
            <p>It is much faster than linear search for large lists but only works on sorted data.</p>
        </section>
    </section>

    <!-- 4(e) -->
    <section>
        <section>
            <h4><b>4(e)</b> Complete the truth table. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Logic: NOT S AND M</p>

            <p>S&nbsp;&nbsp;M&nbsp;&nbsp;OUTPUT</p>
            <p>0&nbsp;&nbsp;0&nbsp;&nbsp;0</p>
            <p>0&nbsp;&nbsp;1&nbsp;&nbsp;1</p>
            <p>1&nbsp;&nbsp;0&nbsp;&nbsp;0</p>
            <p>1&nbsp;&nbsp;1&nbsp;&nbsp;0</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_1_5.php">next</a></p>
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

