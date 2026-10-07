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

	<meta name='description' content='Mock 5'>
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
					<h4>Mock 5</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
    <!-- 4(a) -->
    <section>
        <section>
            <h4><b>4(a)</b> Sensor use (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Temperature sensors collect environmental data and send it to systems for monitoring conditions.</p>
        </section>
    </section>

    <!-- 4(b) -->
    <section>
        <section>
            <h4><b>4(b)</b> Wireless communication (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Satellite</p>
        </section>
    </section>

    <!-- 4(c) -->
    <section>
        <section>
            <h4><b>4(c)</b> ICT improves effectiveness (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>ICT enables fast data collection and analysis, improving decision-making.</p>
            <p>It also improves communication between teams, ensuring efficient coordination and faster response times.</p>
        </section>
    </section>

    <!-- 4(d) -->
    <section>
        <section>
            <h4><b>4(d)</b> Data protection laws (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To protect personal data from misuse and ensure it is stored securely.</p>
        </section>
    </section>

    <!-- 4(e) -->
    <section>
        <section>
            <h4><b>4(e)</b> Impact on employment (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> ICT reduces some manual jobs but creates new skilled roles such as IT specialists.</p>
        </section>
    </section>

    <!-- 4(f) -->
    <section>
        <section>
            <h4><b>4(f)</b> GPS tracking (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To track the location of teams and resources.</p>
        </section>
    </section>

    <!-- 4(g) -->
    <section>
        <section>
            <h4><b>4(g)</b> Ethical issues (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Monitoring systems can provide significant benefits during a global crisis by allowing organisations to track the location and condition of individuals in real time. This can improve safety, as emergency services can quickly identify people in danger and provide assistance faster. For example, tracking systems can help locate missing persons or ensure that individuals in high-risk areas are evacuated promptly, which can ultimately save lives.</p>
			</section>
        <section>
            <p>In addition, monitoring systems can improve the efficiency of resource allocation. By analysing location data, organisations can determine where help is most needed and deploy emergency services more effectively. This reduces delays and ensures that limited resources, such as medical supplies and rescue teams, are used in the most efficient way.</p>
			</section>
        <section>
            <p>However, there are several ethical concerns associated with using monitoring systems. One major issue is privacy. Individuals may be tracked without their full consent or awareness, which can be seen as an invasion of personal freedom. Continuous monitoring may make people feel uncomfortable or controlled, especially if they do not know how their data is being used.</p>
			</section>
        <section>
            <p>Another concern is the risk of data misuse or unauthorised access. If monitoring data is not properly secured, it could be accessed by hackers or misused by organisations for purposes beyond the crisis. For example, sensitive location or personal data could be leaked, leading to potential harm or discrimination.</p>
			</section>
        <section>
            <p>There is also the issue of lack of control over personal data. Individuals may not have the ability to opt out of monitoring systems, which raises ethical questions about consent and individual rights. In some cases, governments or organisations may prioritise public safety over personal privacy, which can create conflict between collective benefit and individual freedom.</p>
			</section>
        <section>
            <p>Overall, while monitoring systems provide clear advantages in improving safety, response time, and efficiency during a crisis, they also raise serious ethical concerns regarding privacy, consent, and data security. Therefore, it is important to implement safeguards such as data protection laws, encryption, and clear consent policies to ensure a balance between protecting individuals and respecting their rights.</p>
        </section>
    </section>
        <section>    
			<p><a href="<?= $dirBase ?>/mock_5_5.php">next</a></p>
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

