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

    <!-- 1(a) -->
    <section>
        <section>
            <h4><b>1(a)</b> Which one of these is a camcorder used for? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Creating video files</p>
        </section>
    </section>

    <!-- 1(b) -->
    <section>
        <section>
            <h4><b>1(b)</b> Which one of these is the fastest way to transfer files? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> USB</p>
        </section>
    </section>

    <!-- 1(c) -->
    <section>
        <section>
            <h4><b>1(c)</b> State one reason why Wi-Fi is better than Bluetooth for file transfer. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Wi-Fi supports faster data transfer speeds and can transfer larger files more efficiently than Bluetooth.</p>
        </section>
    </section>

    <!-- 1(d)(i) -->
    <section>
        <section>
            <h4><b>1(d)(i)</b> Explain one reason to share photos using social media instead of email. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Social media allows photos and videos to be shared instantly with a large audience at the same time.</p>
            <p>It also provides interactive features such as comments, likes, and sharing, making communication easier and faster than email.</p>
        </section>
    </section>

    <!-- 1(d)(ii) -->
    <section>
        <section>
            <h4><b>1(d)(ii)</b> State one reason why permission is not needed for own photos. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> The photos belong to Saku because she created or took them herself, so she owns the copyright.</p>
        </section>
    </section>

    <!-- 1(e)(i) -->
    <section>
        <section>
            <h4><b>1(e)(i)</b> Show the calculation used to determine the maximum number of photos. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>128 GiB = 128 × 1024 = 131072 MiB</p>
            <p>131072 ÷ 4 = 32768 photos</p>
            <p>Final Answer: 32,768 photos</p>
        </section>
        <section>
            <p><b>Explanation:</b> Convert storage into the same unit before dividing.</p>
        </section>
    </section>

    <!-- 1(e)(ii) -->
    <section>
        <section>
            <h4><b>1(e)(ii)</b> Type of storage used by memory card? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Flash</p>
        </section>
    </section>

    <!-- 1(e)(iii) -->
    <section>
        <section>
            <h4><b>1(e)(iii)</b> Explain one use of ROM in a digital camera. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> ROM stores the camera’s firmware and startup instructions needed to boot and operate the device.</p>
        </section>
    </section>

    <!-- 1(e)(iv) -->
    <section>
        <section>
            <h4><b>1(e)(iv)</b> State one benefit of data compression. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Data compression reduces file size, allowing more photos and videos to be stored.</p>
        </section>
    </section>

    <!-- 1(f) -->
    <section>
        <section>
            <h4><b>1(f)</b> Explain one advantage of a faster processor. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A faster processor can process and edit photos or videos more quickly, reducing delays when rendering or saving content.</p>
            <p>This improves performance and allows multitasking more efficiently.</p>
        </section>
    </section>

    <!-- 1(g)(i) -->
    <section>
        <section>
            <h4><b>1(g)(i)</b> Identify two components shown in the USB flash drive image. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Flash memory chip</p>
            <p>Controller chip</p>
        </section>
    </section>

    <!-- 1(g)(ii) -->
    <section>
        <section>
            <h4><b>1(g)(ii)</b> Explain one advantage of a solid-state drive (SSD). (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>An SSD has no moving mechanical parts, making it faster, quieter, and more durable than a traditional hard disk drive.</p>
            <p>This reduces the risk of damage when travelling.</p>
        </section>
    </section>

    <!-- 1(h) -->
    <section>
        <section>
            <h4><b>1(h)</b> Explain what is meant by an embedded system and state one other example. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>An embedded system is a computer system designed to perform a specific dedicated task within a larger device.</p>
            <p>One example is a washing machine controller or a microwave oven.</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_2_2.php">next</a></p>
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

