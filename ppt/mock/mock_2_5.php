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

	<meta name='description' content='Mock 2'>
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
            <h4><b>5(a)</b> Explain two reasons why locally installed software may be used instead of online software. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Reason 1:</b></p>
            <p>Locally installed software can work without an Internet connection.</p>
            <p>This allows users to continue working even when network access is unavailable.</p>

            <p><b>Reason 2:</b></p>
            <p>Locally installed software may provide faster performance because files and processing are handled directly by the computer hardware.</p>
            <p>This is useful for demanding tasks such as video editing or graphic design.</p>
        </section>
    </section>

    <!-- 5(b) -->
    <section>
        <section>
            <h4><b>5(b)(i)</b> State two purposes of communication software. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Sending emails or instant messages</p>
            <p>Video conferencing and online meetings</p>
        </section>
    </section>

    <!-- 5(c) -->
    <section>
        <section>
            <h4><b>5(c)</b> Explain why system software should be kept up to date. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>System software updates fix security vulnerabilities and protect the system from malware or cyberattacks.</p>
            <p>Updates may also improve performance, stability, and compatibility with new hardware or software.</p>
        </section>
    </section>

    <!-- 5(d) -->
    <section>
        <section>
            <h4><b>5(d)</b> State two input devices for users with disabilities. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Voice recognition microphone</p>
            <p>Braille keyboard</p>
        </section>
    </section>

    <!-- 5(e) -->
    <section>
        <section>
            <h4><b>5(e)</b> Explain one benefit of defragmentation. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Defragmentation reorganizes fragmented files so related data is stored closer together on the disk.</p>
            <p>This improves file access speed and overall system performance on hard disk drives.</p>
        </section>
    </section>

    <!-- 5(f) -->
    <section>
        <section>
            <h4><b>5(f)</b> State the utility software used to reduce file size. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Compression software</p>
        </section>
    </section>

    <!-- 5(g) -->
    <section>
        <section>
            <h4><b>5(g)</b> Discuss the ethical issues involved in monitoring individuals online. (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Monitoring online activity can provide benefits for organizations and users. Employers may monitor websites visited, emails sent, or time spent online to improve security, prevent cyberattacks, and ensure employees follow company policies. Monitoring can also help detect inappropriate behavior or illegal activity.</p>
</section>
    <section>
            <p>However, monitoring raises serious ethical concerns about privacy. Individuals may feel uncomfortable knowing their online activities are constantly tracked. Excessive monitoring may reduce trust between employers and employees and create stress.</p>
</section>
    <section>
            <p>There are also concerns about how collected data is stored and used. Personal information could be misused, shared without permission, or accessed by unauthorized users if security is weak. Organizations must ensure monitoring is fair, transparent, and follows data protection laws.</p>
</section>
    <section>
            <p>Another ethical issue is consent. Users should be informed clearly about what information is being monitored and why it is collected. Monitoring should only collect necessary information rather than excessive personal data.</p>
   </section>
    <section>
            <p>Overall, online monitoring can improve security and productivity, but organizations must balance these benefits with privacy rights, transparency, ethical responsibility, and proper data protection.</p>
        </section>
    </section>
 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_2_1.php">next</a></p>
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

