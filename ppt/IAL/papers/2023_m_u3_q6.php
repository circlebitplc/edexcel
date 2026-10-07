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

    <section>
    <section>
        <h4><b>6(b)</b> Evaluate the interface designs using the four rules of interface design. (12 marks)</h4>
    </section>

    <section>
        <p><b>Introduction</b></p>

        <p>
            Good interface design improves usability,
            accessibility,
            user satisfaction,
            and efficiency.
        </p>

        <p>
            The supplied hotel TV interface
            has several strengths and weaknesses
            when evaluated using the four rules
            of interface design.
        </p>
    </section>

    <section>
        <p><b>Rule 1 — Strive for Consistency</b></p>

        <p><b>Problems with the Design</b></p>

        <ul>
            <li>Different fonts are used</li>
            <li>Font sizes vary</li>
            <li>Colours change between screens</li>
            <li>Button positions are inconsistent</li>
        </ul>

        <p><b>Examples:</b></p>

        <ul>
            <li>“Back” and “Next” buttons appear in different locations</li>
            <li>Some text is green while other text is red</li>
        </ul>

        <p>
            This may confuse users.
        </p>
    </section>

    <section>
        <p><b>Improvements</b></p>

        <ul>
            <li>Use one font style</li>
            <li>Use consistent colours</li>
            <li>Place navigation buttons in the same position on every screen</li>
        </ul>

        <p>
            This would improve familiarity and navigation.
        </p>
    </section>

    <section>
        <p><b>Rule 2 — Seek Universal Usability</b></p>

        <p><b>Problems with the Design</b></p>

        <ul>
            <li>Small fonts are difficult to read</li>
            <li>Stylised fonts reduce readability</li>
            <li>Red and green colours are problematic for colour-blind users</li>
        </ul>

        <p>
            Elderly users or visually impaired users
            may experience difficulty.
        </p>
    </section>

    <section>
        <p><b>Improvements</b></p>

        <ul>
            <li>Use larger text</li>
            <li>Improve colour contrast</li>
            <li>Avoid relying only on colours</li>
            <li>Provide accessibility options</li>
        </ul>

        <p>
            This would make the interface usable
            by a wider range of people.
        </p>
    </section>

    <section>
        <p><b>Rule 3 — Offer Informative Feedback</b></p>

        <p><b>Positive Features</b></p>

        <ul>
            <li>The “characters left” counter updates while typing</li>
            <li>Navigation buttons indicate movement between screens</li>
        </ul>

        <p>
            This helps users understand system responses.
        </p>
    </section>

    <section>
        <p><b>Problems</b></p>

        <ul>
            <li>Invalid actions</li>
            <li>Errors</li>
            <li>Incorrect inputs</li>
        </ul>

        <p>
            Users may become confused
            if something goes wrong.
        </p>
    </section>

    <section>
        <p><b>Improvements</b></p>

        <ul>
            <li>Display error messages</li>
            <li>Confirm successful actions</li>
            <li>Provide loading indicators</li>
        </ul>

        <p>
            This would improve communication with users.
        </p>
    </section>

    <section>
        <p><b>Rule 4 — Permit Easy Reversal of Actions</b></p>

        <p><b>Positive Features</b></p>

        <ul>
            <li>A Back button</li>
            <li>A Backspace key</li>
        </ul>

        <p>
            These allow users to correct mistakes.
        </p>
    </section>

    <section>
        <p><b>Problems</b></p>

        <p>
            The buttons are inconsistently placed,
            making them difficult to locate quickly.
        </p>

        <p>
            There are limited undo features.
        </p>
    </section>

    <section>
        <p><b>Improvements</b></p>

        <ul>
            <li>Keep undo controls in standard locations</li>
            <li>Include cancel options</li>
            <li>Allow users to reverse actions easily</li>
        </ul>

        <p>
            This reduces frustration.
        </p>
    </section>

    <section>
        <p><b>Overall Evaluation</b></p>

        <p>
            The interface partially follows
            the four interface design rules
            but has several weaknesses:
        </p>

        <ul>
            <li>Inconsistent design</li>
            <li>Poor accessibility</li>
            <li>Limited feedback</li>
        </ul>
    </section>

    <section>
        <p>
            However, improvements such as:
        </p>

        <ul>
            <li>Consistent layouts</li>
            <li>Better colour choices</li>
            <li>Larger fonts</li>
            <li>Improved error handling</li>
        </ul>

        <p>
            would make the interface
            more effective and user-friendly.
        </p>
    </section>

    <section>
        <p><b>Conclusion</b></p>

        <p>
            Although the interface contains
            some useful navigation and feedback features,
            it does not fully satisfy
            the four rules of interface design.
        </p>

        <p>
            Improving consistency,
            accessibility,
            and user feedback
            would significantly improve usability
            and user experience.
        </p>
    </section>

</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2023_m_u3_q1.php">next</a></p>
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

