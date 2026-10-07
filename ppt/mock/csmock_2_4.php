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
    <!-- 4(a) -->
    <section>
        <section>
            <h4><b>4(a)</b> Describe one way technology makes society more inclusive. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Technology allows people with disabilities to communicate and access services more easily.</p>
            <p>Tools such as screen readers, voice recognition, and video conferencing improve participation in education, work, and social activities.</p>
        </section>
    </section>

    <!-- 4(b)(i) -->
    <section>
        <section>
            <h4><b>4(b)(i)</b> Explain one difference between DNA computing and traditional computing. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Traditional computers process data electronically using binary digits and silicon-based hardware.</p>
            <p>DNA computing uses biological molecules such as DNA strands to perform calculations and store data.</p>
        </section>
    </section>

    <!-- 4(b)(ii) -->
    <section>
        <section>
            <h4><b>4(b)(ii)</b> What is the basic unit of information in quantum computing? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Qubit</p>
        </section>
    </section>

    <!-- 4(c) -->
    <section>
        <section>
            <h4><b>4(c)</b> State two applications of nanotechnology. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Targeted drug delivery in medicine</p>
            <p>Manufacturing smaller and faster computer chips</p>
        </section>
    </section>

    <!-- 4(d)(i) -->
    <section>
        <section>
            <h4><b>4(d)(i)</b> Identify the software protecting against unauthorized access. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Firewall</p>
        </section>
    </section>

    <!-- 4(d)(ii) -->
    <section>
        <section>
            <h4><b>4(d)(ii)</b> Explain why hackers can sometimes benefit network owners. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Ethical hackers can identify security vulnerabilities before malicious attackers exploit them.</p>
            <p>This allows organizations to strengthen security and protect systems more effectively.</p>
        </section>
    </section>

    <!-- 4(e) -->
    <section>
        <section>
            <h4><b>4(e)</b> Discuss how programmers could minimize security vulnerabilities. (6)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Programmers can minimize security vulnerabilities by using secure coding practices during development. Input validation should be implemented to prevent invalid or malicious data from being entered into the system.</p>
 </section>
    <section>
            <p>Software should be thoroughly tested using debugging, penetration testing, and vulnerability scanning to identify weaknesses before release.</p>
 </section>
    <section>
            <p>Encryption should be used to protect sensitive data during transmission, and strong authentication methods such as multi-factor authentication should be implemented.</p>
 </section>
    <section>
            <p>Regular updates and patches should be applied to fix known vulnerabilities and maintain system security.</p>
 </section>
    <section>
            <p>Access control systems should ensure users only have permission to access the data and functions they require.</p>
   </section>
    <section>
            <p>Overall, combining secure coding, testing, encryption, and updates reduces the risk of cyberattacks and unauthorized access.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/csmock_2_5.php">next</a></p>
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

