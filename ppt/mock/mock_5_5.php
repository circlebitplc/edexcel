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
  <!-- 5(a) -->
    <section>
        <section>
            <h4><b>5(a)</b> Two reasons for application software (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Application software allows organisations to process and manage data efficiently, such as maintaining records and generating reports.</p>
            <p>It also improves communication and coordination by enabling tools like spreadsheets and databases.</p>
        </section>
    </section>

    <!-- 5(b) -->
    <section>
        <section>
            <h4><b>5(b)</b> Communication software purposes (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Sending messages</p>
            <p>• Video communication</p>
        </section>
    </section>

    <!-- 5(c) -->
    <section>
        <section>
            <h4><b>5(c)</b> System software importance (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> System software controls hardware and allows applications to run.</p>
        </section>
    </section>

    <!-- 5(d) -->
    <section>
        <section>
            <h4><b>5(d)</b> Input devices for disabled users (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>• Voice recognition</p>
            <p>• Adaptive keyboard</p>
        </section>
    </section>

    <!-- 5(e) -->
    <section>
        <section>
            <h4><b>5(e)</b> Disk cleanup benefit (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Removes unnecessary files, freeing space and improving performance.</p>
        </section>
    </section>

    <!-- 5(f) -->
    <section>
        <section>
            <h4><b>5(f)</b> Utility software type (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Compression software</p>
        </section>
    </section>

    <!-- 5(g) -->
    <section>
        <section>
            <h4><b>5(g)</b> Positive impacts (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Software systems play a critical role in improving global communication during a crisis by enabling real-time communication between organisations, governments, and emergency teams across different countries. For example, tools such as video conferencing and messaging platforms allow teams to instantly share updates about affected areas. This reduces delays that would occur with traditional communication methods, meaning decisions can be made faster, which is essential in life-threatening situations.</p>
            </section>
        <section>
			<p>Another major benefit is the use of cloud-based software systems, which allow data to be stored centrally and accessed from anywhere in the world. This means that all teams involved in the crisis response can access the same information, such as maps, medical records, and supply data. As a result, there is less confusion and fewer errors, because everyone is working with accurate and up-to-date data, improving coordination between teams.</p>
            </section>
        <section>
			<p>Software systems also support collaborative working environments, where multiple users can work on the same documents or systems at the same time. For instance, shared documents or crisis management platforms allow teams to update plans in real time. This ensures that changes are immediately visible to all users, preventing miscommunication and allowing faster, more organised responses.</p>
            </section>
        <section>
			<p>In addition, data analysis software is used to process large volumes of data collected from monitoring systems, such as satellite images or sensors. This software can identify patterns, trends, and high-risk areas, allowing organisations to prioritise resources effectively. For example, it can help determine which areas need urgent medical aid or evacuation, leading to more efficient use of limited resources.</p>
            </section>
        <section>
			<p>Furthermore, automation within software systems reduces the need for manual data entry and repetitive tasks. This not only saves time but also reduces the risk of human error, which is particularly important during crises where mistakes could have serious consequences. As a result, operations become more reliable and efficient.</p>
            </section>
        <section>
			<p>However, despite these advantages, software systems depend heavily on internet connectivity and infrastructure, which may be damaged or unavailable in disaster zones. This can limit access to systems when they are needed most. Additionally, there are cybersecurity risks, such as hacking or data breaches, which could expose sensitive information about affected individuals.</p>
            </section>
        <section>
			<p>Overall, software systems greatly enhance global communication and coordination by enabling fast information sharing, improving decision-making, and increasing efficiency. While there are some limitations, the benefits in improving response time and saving lives significantly outweigh the risks when proper security and backup systems are in place.</p>
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

