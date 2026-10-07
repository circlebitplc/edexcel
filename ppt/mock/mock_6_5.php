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

	<meta name='description' content='Mock 6'>
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
					<h4>Mock 6</h4>
					<h2></h2>
					<?= ppt_teacher_credit_markup() ?>
 </section> 

    <!-- 5(a) -->
    <section>
        <section>
            <h4><b>5(a)</b> Explain two reasons why application software is used in the space agency. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Reason 1:</b></p>
            <p>Application software is used to analyze spacecraft data quickly and accurately.</p>
            <p>This helps scientists and engineers monitor spacecraft performance and make informed decisions.</p>
</section>
<section>
            <p><b>Reason 2:</b></p>
            <p>Application software is used to create reports, presentations, spreadsheets, and mission documents.</p>
            <p>This improves communication and organization within the space agency.</p>
        </section>
    </section>

    <!-- 5(b) -->
    <section>
        <section>
            <h4><b>5(b)</b> State two purposes of communication software used in the space mission. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Video conferencing between mission teams</p>
            <p>• Sending emails and instant messages</p>
        </section>
    </section>

    <!-- 5(c) -->
    <section>
        <section>
            <h4><b>5(c)</b> Explain why system software is important. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>System software manages the computer hardware and provides a platform for application software to run.</p>
           </section>
<section> <p>Without system software, the computer system would not function properly.</p>
        </section>
    </section>

    <!-- 5(d) -->
    <section>
        <section>
            <h4><b>5(d)</b> Give two input devices that space travelers could use when wearing a spacesuit. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Microphone</p>
            <p>• Touchscreen</p>
        </section>
    </section>

    <!-- 5(e) -->
    <section>
        <section>
            <h4><b>5(e)</b> Explain why defragmentation is not necessary for SSDs. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>SSDs can access data directly without relying on moving parts, so fragmented files do not significantly reduce performance.</p>
            </section>
<section><p>Defragmentation also causes unnecessary write operations, which can reduce the lifespan of the SSD.</p>
        </section>
    </section>

    <!-- 5(f) -->
    <section>
        <section>
            <h4><b>5(f)</b> State the type of utility software used to reduce file size without noticeable loss of quality. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Compression software</p>
        </section>
    </section>

    <!-- 5(g) -->
    <section>
        <section>
            <h4><b>5(g)</b> Discuss the positive impacts of ICT on scientific research and global collaboration. (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>ICT has significantly improved scientific research and global collaboration in space missions. Scientists and engineers from different countries can communicate instantly using online systems, video conferencing, and cloud-based collaboration tools. This improves teamwork and allows experts to work together efficiently regardless of location.</p>
</section>
<section>
            <p>ICT systems allow large amounts of spacecraft and telemetry data to be collected, processed, and analyzed quickly. Advanced software can identify patterns and produce accurate results much faster than manual methods. Real-time communication also allows mission control teams worldwide to coordinate effectively during missions.</p>
</section>
<section>
            <p>Cloud storage and online databases make scientific information easily accessible to authorized researchers around the world. This improves data sharing and reduces duplication of research work. Simulation software can also test mission scenarios safely before launch, reducing risks and improving mission success rates.</p>
</section>
<section>
            <p>Automation systems controlled by ICT improve accuracy and reduce human error in scientific experiments and spacecraft operations. Monitoring systems also improve astronaut safety by tracking environmental and health conditions continuously.</p>
</section>
<section>
            <p>Overall, ICT has transformed scientific research and global collaboration by improving communication, efficiency, accuracy, safety, and access to information.</p>
        </section>
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

