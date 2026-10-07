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

    <!-- 4(a) -->
    <section>
        <section>
            <h4><b>4(a)</b> Minimum practical data unit? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Mebibyte</p>
        </section>
    </section>

    <!-- 4(b) -->
    <section>
        <section>
            <h4><b>4(b)</b> State one operating system function essential during audio recording. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Memory management</p>
        </section>
    </section>

    <!-- 4(c) -->
    <section>
        <section>
            <h4><b>4(c)</b> Explain one benefit of open-source recording software. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Open-source software is usually free to use, reducing software costs for Yash.</p>
            <p>The source code can also be modified and improved by developers to add new features or fix problems.</p>
        </section>
    </section>

    <!-- 4(d) -->
    <section>
        <section>
            <h4><b>4(d)</b> Explain two reasons why software should be updated regularly. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Updates fix security vulnerabilities and help protect the system from malware and cyberattacks.</p>
            <p>Updates improve performance, compatibility, stability, and may add new features.</p>
        </section>
    </section>

    <!-- 4(e) -->
    <section>
        <section>
            <h4><b>4(e)</b> Discuss benefits and drawbacks of different storage media. (8)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Different storage media provide different advantages and disadvantages for Yash’s music and recording collection.</p>

            <p>SSDs provide very fast read and write speeds, allowing audio files and software to load quickly. They are also more durable because they contain no moving parts, making them suitable for travel. However, SSDs are usually more expensive per gigabyte than HDDs.</p>

            <p>HDDs provide very large storage capacities at lower cost, making them suitable for storing large collections of recordings. However, they are slower and more easily damaged because they contain moving mechanical parts.</p>

            <p>Cloud storage allows files to be accessed from different devices and locations using the Internet. It also provides backup protection if local devices fail. However, cloud storage depends on Internet access and may raise privacy or security concerns.</p>

            <p>USB flash drives are portable and easy to carry between locations. They are useful for transferring files quickly between devices. However, they can be lost easily and usually have lower capacity compared to other storage media.</p>

            <p>Overall, Yash may benefit from using multiple storage types together to balance speed, portability, capacity, reliability, and cost.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_1_5.php">next</a></p>
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

