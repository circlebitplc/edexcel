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
<!-- 5(b)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>5(b)(i)</b> Define Scrum. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Scrum is a framework used
            to manage agile software development projects.
        </p>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            It helps development teams organise tasks,
            collaborate efficiently,
            and complete work in short development cycles
            called sprints.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 5(b)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>5(b)(ii)</b> State two features of a sprint. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Sprints have fixed start and finish dates.
        </p>

        <p>
            Each sprint has clear planned goals.
        </p>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            This helps the development team
            track progress and remain focused
            on completing objectives.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 5(c) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>5(c)</b> Discuss requirements and planning in an agile software project. (8 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            At the beginning of an agile project,
            the team creates requirements documents
            describing what the software should do.
        </p>
    </section>

    <section>
        <p><b>Requirements should:</b></p>

        <ul>
            <li>Be simple</li>
            <li>Be prioritised</li>
            <li>Avoid unnecessary features</li>
        </ul>
    </section>

    <section>
        <p><b>Planning activities include:</b></p>

        <ul>
            <li>Meetings with stakeholders</li>
            <li>Assigning scrum master roles</li>
            <li>Estimating project times</li>
            <li>Planning the first sprint</li>
        </ul>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            Agile planning is flexible
            and allows development teams
            to improve software gradually
            while responding to changing requirements.
        </p>
    </section>

    <section>
        <p><b>Conclusion:</b></p>

        <p>
            Good planning and clear requirements
            increase the likelihood
            of delivering successful software on time.
        </p>
    </section>
</section>
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2022_m_u3_q6.php">next</a></p>
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

