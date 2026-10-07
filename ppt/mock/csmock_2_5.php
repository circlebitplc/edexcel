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
    <!-- 5(a) -->
    <section>
        <section>
            <h4><b>5(a)</b> State an appropriate method for writing the algorithm and justify your answer. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Flowchart</p>
        </section>
        <section>
            <p><b>Explanation:</b></p>
            <p>A flowchart uses symbols and arrows to represent the sequence of steps visually.</p>
            <p>This makes the algorithm easier for non-technical users to understand.</p>
        </section>
    </section>

    <!-- 5(b)(i) -->
    <section>
        <section>
            <h4><b>5(b)(i)</b> Complete the trace table for input 1300. (3)</h4>
        </section>
        <section>
            <table border="1" cellpadding="5" cellspacing="0">
    <tr>
        <th>target</th>
        <th>found</th>
        <th>lowIndex</th>
        <th>highIndex</th>
        <th>mid</th>
        <th>output</th>
    </tr>

    <tr>
        <td>1300</td>
        <td>FALSE</td>
        <td>0</td>
        <td>8</td>
        <td>4</td>
        <td></td>
    </tr>

    <tr>
        <td>1300</td>
        <td>FALSE</td>
        <td>0</td>
        <td>3</td>
        <td>1</td>
        <td></td>
    </tr>

    <tr>
        <td>1300</td>
        <td>TRUE</td>
        <td>0</td>
        <td>3</td>
        <td>1</td>
        <td>Cargo ship available of weight : 1300</td>
    </tr>
</table>
        </section>
    </section>

    <!-- 5(b)(ii) -->
    <section>
        <section>
            <h4><b>5(b)(ii)</b> Give the purpose of the algorithm. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> The algorithm searches for a cargo ship with a matching weight capacity using binary search.</p>
        </section>
    </section>

    <!-- 5(b)(iii) -->
    <section>
        <section>
            <h4><b>5(b)(iii)</b> Explain why variable mid is needed. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>The variable mid stores the middle position of the search range in the array.</p>
            <p>This allows the binary search algorithm to repeatedly divide the search area in half efficiently.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_2_6.php">next</a></p>
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

