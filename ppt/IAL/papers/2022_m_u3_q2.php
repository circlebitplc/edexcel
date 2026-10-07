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

	<meta name='description' content='papers'>
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
					<h4>2022 May</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>   
<!-- ====================================================== -->
<!-- 2(a) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>2(a)</b> Create a project schedule/Gantt chart. (6 marks)</h4>
    </section>

    <section>
        <p><b>Sample Answer:</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Task</th>
                <th>Start Date</th>
                <th>Duration</th>
                <th>Dependency</th>
            </tr>

            <tr>
                <td>Hardware Installation</td>
                <td>05/07</td>
                <td>3 Days</td>
                <td>None</td>
            </tr>

            <tr>
                <td>Software Installation</td>
                <td>08/07</td>
                <td>2 Days</td>
                <td>Hardware Installation</td>
            </tr>

            <tr>
                <td>File Transfer</td>
                <td>10/07</td>
                <td>2 Days</td>
                <td>Hardware Setup</td>
            </tr>

            <tr>
                <td>Final Testing</td>
                <td>12/07</td>
                <td>3 Days</td>
                <td>All Previous Tasks</td>
            </tr>

            <tr>
                <td>Possible Overrun Time</td>
                <td>15/07</td>
                <td>1 Day</td>
                <td>Final Testing</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            A good project schedule should clearly show
            task durations, dependencies, deadlines,
            constraints, and possible delays or overrun time.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 2(b) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>2(b)</b> Design a table for warranty information. (8 marks)</h4>
    </section>

    <section>
        <p><b>Sample Table Design:</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Field Name</th>
                <th>Data Type</th>
                <th>Key</th>
                <th>Field Size</th>
                <th>Validation</th>
            </tr>

            <tr>
                <td>warrantyNumber</td>
                <td>Text</td>
                <td>Primary Key</td>
                <td>9–20</td>
                <td>LLNNNNNNN</td>
            </tr>

            <tr>
                <td>toolID</td>
                <td>Text</td>
                <td>-</td>
                <td>4–10</td>
                <td>Must not be empty</td>
            </tr>

            <tr>
                <td>tool</td>
                <td>Text</td>
                <td>-</td>
                <td>6–20</td>
                <td>Letters only</td>
            </tr>

            <tr>
                <td>make</td>
                <td>Text</td>
                <td>-</td>
                <td>8–20</td>
                <td>Required</td>
            </tr>

            <tr>
                <td>model</td>
                <td>Text</td>
                <td>-</td>
                <td>6–20</td>
                <td>Required</td>
            </tr>

            <tr>
                <td>supplierEmail</td>
                <td>Text</td>
                <td>-</td>
                <td>19–50</td>
                <td>text@text.text</td>
            </tr>

            <tr>
                <td>whenPurchased</td>
                <td>Date</td>
                <td>-</td>
                <td>10</td>
                <td>DD/MM/YYYY</td>
            </tr>

            <tr>
                <td>purchasePrice</td>
                <td>Currency</td>
                <td>-</td>
                <td>8–10</td>
                <td>Positive values only</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            The primary key uniquely identifies each warranty record.
        </p>

        <p>
            Validation rules help reduce incorrect data entry
            and improve data accuracy.
        </p>

        <p>
            Appropriate field sizes improve storage efficiency
            and database performance.
        </p>
    </section>
</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2022_m_u3_q3.php">next</a></p>
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

