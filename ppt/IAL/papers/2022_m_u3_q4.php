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
<!-- 4(a) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>4(a)</b> Discuss health and safety issues with Sarah’s workstation. (8 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Sarah’s workstation has several ergonomic problems.
        </p>
    </section>

    <section>
        <p><b>Keyboard:</b></p>

        <p>
            The keyboard positions are poor
            and may cause repetitive strain injury (RSI).
        </p>
    </section>

    <section>
        <p><b>Chair:</b></p>

        <p>
            The chair does not provide proper back support,
            which may lead to poor posture and back pain.
        </p>
    </section>

    <section>
        <p><b>Monitor Position:</b></p>

        <p>
            One monitor is too high
            and the laptop screen is too low.
            This may cause neck strain.
        </p>
    </section>

    <section>
        <p><b>Lighting:</b></p>

        <p>
            Screen glare from windows or lights
            may strain Sarah’s eyes.
        </p>
    </section>

    <section>
        <p><b>Improvements:</b></p>

        <ul>
            <li>Use an ergonomic adjustable chair</li>
            <li>Position screens at eye level</li>
            <li>Keep keyboards at a comfortable height</li>
            <li>Reduce glare using blinds or anti-glare filters</li>
            <li>Keep the desk organised</li>
        </ul>
    </section>

    <section>
        <p><b>Conclusion:</b></p>

        <p>
            Improving workstation ergonomics
            will reduce health risks
            and improve comfort and productivity.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 4(b) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>4(b)</b> Explain how Sarah’s objective meets SMART criteria. (5 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <table border="1" cellspacing="0" cellpadding="8">

            <tr>
                <th>SMART Criteria</th>
                <th>Explanation</th>
            </tr>

            <tr>
                <td>Specific</td>
                <td>
                    Sarah wants to complete the move
                    and office setup before Monday morning.
                </td>
            </tr>

            <tr>
                <td>Measurable</td>
                <td>
                    Success is measured by completing the move
                    and having the office ready.
                </td>
            </tr>

            <tr>
                <td>Achievable</td>
                <td>
                    The moving company and available weekend
                    make the goal realistic.
                </td>
            </tr>

            <tr>
                <td>Relevant</td>
                <td>
                    The setup is necessary
                    for Sarah to continue working.
                </td>
            </tr>

            <tr>
                <td>Time-bound</td>
                <td>
                    The move must be completed
                    over the weekend before Monday.
                </td>
            </tr>

        </table>
    </section>
</section>

<!-- ====================================================== -->
<!-- 5(a) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>5(a)</b> Describe iterative development. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Iterative development divides software creation
            into repeated cycles.
        </p>
    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            Each cycle includes planning,
            designing, testing, and improving the software.
        </p>

        <p>
            The product becomes more complete
            after every iteration.
        </p>
    </section>
</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2022_m_u3_q5.php">next</a></p>
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

