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
    <!-- 3(a) -->
    <section>
        <section>
            <h4><b>3(a)</b> State two different types of online services used by individuals. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Online banking</p>
            <p>Online shopping</p>
        </section>
    </section>

    <!-- 3(b) -->
    <section>
        <section>
            <h4><b>3(b)</b> Describe two features of an online booking system. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Users can check real-time availability of hotel rooms or flights.</p>
            <p>Users can make payments and receive instant booking confirmations online.</p>
        </section>
    </section>

    <!-- 3(c) -->
    <section>
        <section>
            <h4><b>3(c)</b> Which best describes the role of cookies? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Track user activity</p>
        </section>
    </section>

    <!-- 3(d) -->
    <section>
        <section>
            <h4><b>3(d)</b> Which is the most appropriate safe practice? (1)</h4>
        </section>
        <section>
            <p><b>Answer:</b> Using strong passwords</p>
        </section>
    </section>

    <!-- 3(e) -->
    <section>
        <section>
            <h4><b>3(e)</b> Describe two positive impacts of technology in everyday life. (4)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Impact 1:</b></p>
            <p>Technology allows instant communication through email, messaging, and video calls.</p>
            <p>This makes it easier for people to stay connected globally.</p>

            <p><b>Impact 2:</b></p>
            <p>Technology allows online services such as shopping, banking, and booking systems to be accessed conveniently from home.</p>
            <p>This saves time and improves efficiency.</p>
        </section>
    </section>

    <!-- 3(f)(i) -->
    <section>
        <section>
            <h4><b>3(f)(i)</b> Explain one difference between a wiki and an online forum. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>A wiki allows users to collaboratively edit and update shared content pages.</p>
            <p>An online forum is mainly used for discussions where users post messages and replies.</p>
        </section>
    </section>

    <!-- 3(f)(ii) -->
    <section>
        <section>
            <h4><b>3(f)(ii)</b> Explain how moderation helps users follow rules. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Moderators monitor user activity and remove harmful or inappropriate content.</p>
            <p>They can also warn, suspend, or ban users who break community guidelines.</p>
        </section>
    </section>

    <!-- 3(g) -->
    <section>
        <section>
            <h4><b>3(g)</b> Explain one reason users are asked to create an account. (2)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>
            <p>Creating an account allows online services to identify users and store personalized settings or booking information securely.</p>
            <p>It also helps improve security and user management.</p>
        </section>
    </section>

</section>
        <section>    
			<p><a href="<?= $dirBase ?>/mock_2_4.php">next</a></p>
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

