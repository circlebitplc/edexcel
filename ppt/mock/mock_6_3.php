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

    <!-- 3(a)(i) -->
    <section>
        <section>
            <h4><b>3(a)(i)</b> Give two types of online services and state their purpose. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Email – used to send mission updates and communication messages between staff.</p>
            <p>Cloud storage – used to store, share, and access spacecraft data remotely.</p>
        </section>
    </section>

    <!-- 3(a)(ii) -->
    <section>
        <section>
            <h4><b>3(a)(ii)</b> Explain two features of online systems used in the space mission. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Real-time communication allows mission control and astronauts to exchange information instantly during emergencies.</p>
            <p>Remote access allows authorized users to access mission systems and data from different locations.</p>
        </section>
    </section>

    <!-- 3(b) -->
    <section>
        <section>
            <h4><b>3(b)</b> What is the role of authentication? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Verify identity</p>
        </section>
    </section>

    <!-- 3(c) -->
    <section>
        <section>
            <h4><b>3(c)</b> What is the main advantage of multi-factor authentication? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Higher security</p>
        </section>
    </section>

    <!-- 3(d) -->
    <section>
        <section>
            <h4><b>3(d)</b> Explain one positive impact of ICT communication in space missions. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>ICT communication systems allow instant transmission of telemetry data and communication between astronauts and mission control.</p>
            <p>This improves coordination, increases safety, and enables faster decision-making during the mission.</p>
        </section>
    </section>

    <!-- 3(e)(i) -->
    <section>
        <section>
            <h4><b>3(e)(i)</b> Explain one difference between real-time communication and asynchronous communication. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Real-time communication happens instantly with all users communicating at the same time, such as live video calls between astronauts and mission control.</p>
            <p>Asynchronous communication does not require users to communicate simultaneously, such as emails sent and read later.</p>
        </section>
    </section>

    <!-- 3(e)(ii) -->
    <section>
        <section>
            <h4><b>3(e)(ii)</b> Explain one benefit of an acceptable use policy. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>An acceptable use policy provides clear rules for employees when using online systems and communication tools.</p>
            <p>This helps reduce misuse, improve cybersecurity, and protect sensitive spacecraft data.</p>
        </section>
    </section>

    <!-- 3(e)(iii) -->
    <section>
        <section>
            <h4><b>3(e)(iii)</b> Describe how moderation tools are used to manage online communication systems. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Moderation tools monitor online communication by filtering harmful or inappropriate content and blocking unauthorized messages.</p>
            <p>They also help administrators manage user activity and maintain safe communication systems.</p>
        </section>
    </section>

    <!-- 3(f) -->
    <section>
        <section>
            <h4><b>3(f)</b> Explain two risks of using online systems in the space mission. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Risk 1:</b></p>
            <p>Hackers or malware could gain unauthorized access to mission systems and confidential spacecraft data.</p>
            <p>This may disrupt spacecraft operations or lead to data theft.</p>

            <p><b>Risk 2:</b></p>
            <p>Internet or communication system failures could interrupt communication between astronauts and mission control.</p>
            <p>This could delay emergency responses and affect mission safety.</p>
        </section>
    </section>
 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_6_4.php">next</a></p>
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

