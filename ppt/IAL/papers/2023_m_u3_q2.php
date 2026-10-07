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
        <h4><b>2(a)</b> Explain one other benefit of normalising a relational database. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Normalisation improves data consistency
            by reducing update anomalies.
        </p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            When databases are normalised:
        </p>

        <ul>
            <li>Related data is stored in separate linked tables</li>
            <li>Duplicate data is removed</li>
        </ul>
    </section>

    <section>
        <p>
            This means updates only need to occur once.
        </p>

        <p><b>For example:</b></p>

        <p>
            If a customer changes address,
            it only needs updating in one table
            rather than many records.
        </p>
    </section>

    <section>
        <p>
            This reduces:
        </p>

        <ul>
            <li>Inconsistent data</li>
            <li>Update errors</li>
            <li>Insertion anomalies</li>
            <li>Deletion anomalies</li>
        </ul>
    </section>

    <section>
        <p>
            Normalisation therefore improves
            database reliability and integrity.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 2(b)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>2(b)(i)</b> Complete the validation table. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>Information</th>
                <th>Validation Type</th>
            </tr>

            <tr>
                <td>Equipment names</td>
                <td>Presence check</td>
            </tr>

            <tr>
                <td>Rental period in hours</td>
                <td>Range check</td>
            </tr>

        </table>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p><b>Presence Check</b></p>

        <p>
            A presence check ensures
            the field is not left blank.
        </p>

        <p><b>Example:</b></p>

        <p>
            The equipment name field
            must contain values such as:
        </p>

        <ul>
            <li>Paddleboard</li>
            <li>Kayak</li>
            <li>Fishing canoe</li>
        </ul>

        <p>
            A blank value would therefore be invalid.
        </p>
    </section>

    <section>
        <p><b>Range Check</b></p>

        <p>
            A range check ensures
            the value falls within acceptable limits.
        </p>

        <p><b>Example:</b></p>

        <p>
            Rental hours must be between 1 and 24 hours.
        </p>

        <p>
            30 hours would therefore be invalid.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 2(b)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>2(b)(ii)</b> Complete the logical database model. (6 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>
		<img src='<?= $dirBase ?>/2023_m_u3_q2_2(b)(ii).png'>
		  </section>

    <section>
        <p><b>Additional Entities:</b></p>

        <ul>
            <li>Rental/Booking</li>
            <li>Inspection/Service</li>
        </ul>
    </section>

    <section>
        <p><b>Relationships:</b></p>

        <ul>
            <li>Customer → Rental (1:M)</li>
            <li>Equipment → Rental (1:M)</li>
            <li>Equipment → Inspection (1:M)</li>
            <li>Technician → Inspection (1:M)</li>
        </ul>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            A customer may create many rentals,
            but each rental belongs to one customer.
        </p>
    </section>

    <section>
        <p>
            One piece of equipment
            may be rented many times.
        </p>

        <p>
            One technician
            can perform many inspections.
        </p>
    </section>

    <section>
        <p>
            Each inspection relates
            to one item of equipment.
        </p>

        <p>
            This structure reduces redundancy
            and improves organisation
            of data relationships.
        </p>
    </section>
</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2023_m_u3_q3.php">next</a></p>
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

