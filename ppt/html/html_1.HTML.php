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

	<meta name='description' content='bubble'>
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
	
	<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/styles/github-dark.min.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/highlightjs-line-numbers.js/2.9.0/styles/base.min.css">
	
	
	</head>

	<body>

		<div class='reveal'>
			<div class='slides'>
				<section>
					<h4>html</h4>
					<h2>part 1</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  

<section>
	<section>
    <h1>What is HTML?</h1>
	</section>
	<section>
		<p>
			HTML (HyperText Markup Language) is the standard markup language used to create webpages and web applications.
		</p>
	</section>

	<section>
		<p>
			HTML is not a programming language. Instead, it is a markup language that uses tags to describe the structure and content of a webpage.
		</p>
	</section>
</section>

<section>
 
	<section>
		<h2>HTML Tells the Web Browser</h2>
	</section>

	<section>
		<p>What content should appear on the page</p>
	</section>

	<section>
		<p>Where the content should appear</p>
	</section>

	<section>
		<p>How different elements relate to each other</p>
	</section>

	<section>
		<p>Which content is a heading, paragraph, image, table, form, etc.</p>
	</section>
 
</section>

<section>
 
	<section>
		<h2>Example</h2>
	</section>

<section>

    <h2>Example</h2>

    <div class="mycode">

        <div class="code-header">
            <span class="dot red"></span>
            <span class="dot yellow"></span>
            <span class="dot green"></span>
        </div>

<pre><code class="language-html">&lt;h1&gt;Welcome to My Website&lt;/h1&gt;
&lt;p&gt;This is my first webpage.&lt;/p&gt;</code></pre>

    </div>

</section>
 
</section>

<section>

	<section>
		<h2>In This Example</h2>
	</section>

	<section>
		<p>
			&lt;h1&gt; creates a main heading.
		</p>
	</section>

	<section>
		<p>
			&lt;p&gt; creates a paragraph.
		</p>
	</section>

	<section>
		<p>
			The browser interprets these tags and displays them appropriately.
		</p>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.11.1/highlight.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/highlightjs-line-numbers.js/2.9.0/highlightjs-line-numbers.min.js"></script>

<script> 
hljs.highlightAll();
hljs.initLineNumbersOnLoad();
</script> 
	</body>
</html>

