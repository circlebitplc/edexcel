<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='papers'>
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
					<h4>2020 Oct</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>   
    <!-- 6(a) -->
    <section>
        <section>
             <img src="<?= $dirBase ?>/6a.png" width=100%>
        </section>
    </section>

    <!-- 6(b) -->
    <section>
        <section>
            <h4><b>6(b)</b> Discuss the benefits and drawbacks of Shehani using CPA for the project. (6 marks)</h4>
        </section>

        <section>
            <p><b>Answer:</b></p>

            <p>Critical Path Analysis helps Shehani organise the project more effectively.</p>
</section> 
		<section>
            <p>By identifying task dependencies, she can clearly see which tasks must be completed before others can begin.</p>
</section> 
		<section>
            <p>This improves planning and reduces confusion.</p>
</section> 
		<section>
            <p>CPA also identifies the critical path, which shows the tasks that cannot be delayed without affecting the overall completion date.</p>
</section> 
		<section>
            <p>This allows Shehani to focus on the most important activities and manage time efficiently.</p>
</section> 
		<section>
            <p>Another advantage is improved resource allocation.</p>
</section> 
		<section>
            <p>She can assign staff, materials, and equipment at the correct times, reducing waste and unnecessary costs.</p>
</section> 
		<section>
            <p>CPA can also identify slack time in non-critical tasks, giving some flexibility if minor delays occur.</p>
</section> 
		<section>
            <p>However, CPA relies heavily on estimated task durations.</p>
</section> 
		<section>
            <p>If the time estimates are inaccurate, the final schedule may become unreliable and deadlines could be missed.</p>
</section> 
		<section>
            <p>Some activities may also be difficult to represent accurately in the diagram, especially if unexpected problems occur during the project.</p>
</section> 
		<section>
            <p>Since Shehani has not used CPA before, she may make mistakes when identifying dependencies or calculating timings.</p>
</section> 
		<section>
            <p>Even small errors could affect the project schedule and cause the launch event deadline to be missed.</p>
</section> 
		<section>
            <p>Overall, CPA is a useful planning tool because it improves organisation, time management, and resource allocation.</p>
</section> 
		<section>
            <p>However, it depends on accurate information and the user’s experience with the method.</p>
        </section>
    </section> 
		<section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2020_o_u3_q1.php">Back to question 1</a></p>
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

