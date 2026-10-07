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
    <!-- 5(a) -->
    <section>
        <section>
            <h4><b>5(a)</b> State two causes of the digital divide. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Lack of Internet access or network infrastructure</p>
            <p>High cost of digital devices and Internet services</p>
        </section>
    </section>

    <!-- 5(b) -->
    <section>
        <section>
            <h4><b>5(b)</b> Explain two negative social impacts of limited Internet access. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Impact 1:</b></p>
            <p>People with limited Internet access may struggle to access online education and learning resources.</p>
            <p>This can reduce educational opportunities and widen inequality.</p>

            <p><b>Impact 2:</b></p>
            <p>Limited Internet access can reduce communication with others and limit access to online communities and services.</p>
            <p>This may cause social isolation and reduced participation in modern society.</p>
        </section>
    </section>

    <!-- 5(c) -->
    <section>
        <section>
            <h4><b>5(c)</b> Explain two negative environmental impacts of streaming and storing digital media online. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Impact 1:</b></p>
            <p>Data centers and streaming services consume large amounts of electricity to store and transmit digital media.</p>
            <p>This increases carbon emissions and energy consumption.</p>

            <p><b>Impact 2:</b></p>
            <p>Frequent upgrading of devices used for streaming can increase electronic waste.</p>
            <p>Improper disposal of electronic devices may release harmful chemicals into the environment.</p>
        </section>
    </section>

    <!-- 5(d) -->
    <section>
        <section>
            <h4><b>5(d)</b> Discuss how reliability of broadcast information compares with online information. (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Traditional broadcast media such as radio and television are usually more regulated than online platforms. Broadcasting organizations must follow laws and professional standards to ensure information is accurate and suitable for audiences. Because of this regulation, broadcast information is often considered more reliable.</p>

            <p>Online information can be published by anyone, including individuals without professional qualifications or fact-checking processes. This increases the risk of misinformation, bias, fake news, and unreliable content being shared quickly online.</p>

            <p>However, online platforms also provide access to a large range of information sources and allow information to be updated quickly. Users can compare multiple sources, check references, verify authors, and fact-check information using trusted websites.</p>

            <p>Broadcast information may still contain bias depending on the organization or presenter. Similarly, some online information from trusted organizations, educational institutions, or official government websites can be highly reliable.</p>

            <p>Users must take responsibility for evaluating the accuracy of online information by checking sources, publication dates, evidence, references, and whether information is supported by other trusted sources.</p>

            <p>Overall, broadcast media is generally more reliable due to stronger regulation and editorial control, but reliable online information can also be found if users apply critical evaluation skills.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_1_1.php">next</a></p>
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

