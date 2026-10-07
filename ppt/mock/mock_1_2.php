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

    <!-- 2(a) -->
    <section>
        <section>
            <h4><b>2(a)</b> Explain one reason why Yash avoids uploading high-resolution video clips while streaming live. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>High-resolution video files require large amounts of bandwidth and upload speed.</p>
            <p>Uploading them during live streaming could cause buffering, lag, or reduced streaming quality for listeners.</p>
        </section>
    </section>

    <!-- 2(b) -->
    <section>
        <section>
            <h4><b>2(b)</b> Which is the most suitable type of online community? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Social networking site</p>
        </section>
    </section>

    <!-- 2(c)(i) -->
    <section>
        <section>
            <h4><b>2(c)(i)</b> State one threat to users in online communities. (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Cyberbullying</p>
        </section>
    </section>

    <!-- 2(c)(ii) -->
    <section>
        <section>
            <h4><b>2(c)(ii)</b> Describe one effective moderation method. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Moderators can monitor posts and remove harmful, offensive, or inappropriate content from the online community.</p>
            <p>They can also block or ban users who break community rules.</p>
        </section>
    </section>

    <!-- 2(c)(iii) -->
    <section>
        <section>
            <h4><b>2(c)(iii)</b> State the purpose of an acceptable use policy (AUP). (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> To provide rules and guidelines for safe and appropriate behavior when using the online community.</p>
        </section>
    </section>

    <!-- 2(d) -->
    <section>
        <section>
            <h4><b>2(d)</b> Describe how one feature of a social networking platform helps create and maintain online communities. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Group and discussion features allow users with similar interests to communicate, share content, and interact regularly.</p>
            <p>This helps build stronger online communities and encourages user participation.</p>
        </section>
    </section>

    <!-- 2(e)(i) -->
    <section>
        <section>
            <h4><b>2(e)(i)</b> Which negatively affects reliability and usefulness of information online? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Bias</p>
        </section>
    </section>

    <!-- 2(e)(ii) -->
    <section>
        <section>
            <h4><b>2(e)(ii)</b> State two methods users can use to check reliability of online information. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Compare information from multiple trusted sources.</p>
            <p>Check the author, publication date, and references used.</p>
        </section>
    </section>

    <!-- 2(e)(iii) -->
    <section>
        <section>
            <h4><b>2(e)(iii)</b> Best way to avoid plagiarism? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Paraphrasing and referencing sources</p>
        </section>
    </section>

    <!-- 2(f) -->
    <section>
        <section>
            <h4><b>2(f)</b> Describe one way features of an online community can increase the reach of Yash’s posts. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Users can like, share, repost, or comment on Yash’s content, causing the platform’s algorithms to recommend the posts to more people.</p>
            <p>This increases audience reach and visibility.</p>
        </section>
    </section>

    <!-- 2(g) -->
    <section>
        <section>
            <h4><b>2(g)</b> Which best improves online safety? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Limiting the amount of personal information shared</p>
        </section>
    </section> 
        <section>    
			<p><a href="<?= $dirBase ?>/mock_1_3.php">next</a></p>
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

