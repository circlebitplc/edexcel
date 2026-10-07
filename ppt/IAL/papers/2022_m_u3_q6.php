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
<!-- 6 -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>6</b> Discuss the use of analytics in Electronic Health Records (EHRs). (12 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Analytics can be used in Electronic Health Records (EHRs)
            to study medical data,
            improve healthcare services,
            and support decision making.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- Descriptive Analytics -->
    <!-- ====================================================== -->

    <section>
        <p><b>Descriptive Analytics</b></p>

        <p>
            Descriptive analytics studies past data.
        </p>

        <p><b>Example:</b></p>

        <p>
            Hospitals can analyse historical disease records
            to identify trends or seasonal illnesses.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- Predictive Analytics -->
    <!-- ====================================================== -->

    <section>
        <p><b>Predictive Analytics</b></p>

        <p>
            Predictive analytics forecasts future events.
        </p>

        <p><b>Example:</b></p>

        <p>
            Healthcare systems can predict future disease outbreaks
            or staffing needs using existing data patterns.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- Prescriptive Analytics -->
    <!-- ====================================================== -->

    <section>
        <p><b>Prescriptive Analytics</b></p>

        <p>
            Prescriptive analytics recommends actions.
        </p>

        <p><b>Example:</b></p>

        <p>
            Systems may suggest the best treatments
            based on current patient information.
        </p>
    </section>

    <!-- ====================================================== -->
    <!-- Benefits for Citizens -->
    <!-- ====================================================== -->

    <section>
        <p><b>Benefits for Citizens</b></p>

        <ul>
            <li>Better diagnosis</li>
            <li>Early detection of disease</li>
            <li>Improved treatment planning</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- Benefits for Healthcare Workers -->
    <!-- ====================================================== -->

    <section>
        <p><b>Benefits for Healthcare Workers</b></p>

        <ul>
            <li>Better decision making</li>
            <li>Improved resource planning</li>
            <li>Better management of hospital services</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- Benefits for Governments -->
    <!-- ====================================================== -->

    <section>
        <p><b>Benefits for Governments</b></p>

        <ul>
            <li>Planning healthcare budgets</li>
            <li>Predicting future healthcare demands</li>
            <li>Managing public health campaigns</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- Tools Used -->
    <!-- ====================================================== -->

    <section>
        <p><b>Tools Used</b></p>

        <p>
            Examples include:
        </p>

        <ul>
            <li>Hadoop</li>
            <li>MongoDB</li>
            <li>Google Analytics</li>
        </ul>
    </section>

    <!-- ====================================================== -->
    <!-- Conclusion -->
    <!-- ====================================================== -->

    <section>
        <p><b>Conclusion</b></p>

        <p>
            Analytics in EHR systems improves healthcare efficiency,
            supports medical decisions,
            and helps governments plan healthcare services
            more effectively.
        </p>
    </section>
</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2022_m_u3_q1.php">next</a></p>
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

