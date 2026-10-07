<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Topic 16 – Emerging Technologies'>
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
					<h4>Topic 16</h4>
					<h2>Emerging Technologies</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>SECTION 7 – NATURAL LANGUAGE PROCESSING (NLP)</h3>
</section>
    <section>
  <section><h4><b>59) </b>Define natural language processing (NLP). [1]</h4></section>
  <section><p>Natural language processing (NLP) is a branch of artificial intelligence that enables computers to understand, interpret, and process human language in both written and spoken forms.</p></section>
</section>

<section>
  <section><h4><b>60) </b>Explain the purpose of NLP. [2]</h4></section>
  <section>
    <p>• Allows computers to interpret and analyse human language accurately</p>
    <p>• Enables systems to generate meaningful responses for natural human-computer interaction</p>
  </section>
</section>

<section>
  <section><h4><b>61) </b>Describe how NLP differs from traditional text processing. [2]</h4></section>
  <section>
    <p>• Traditional text processing uses simple keyword matching</p>
    <p>• NLP understands grammar, context, and meaning (semantics), making it more accurate</p>
  </section>
</section>

<section>
  <section><h4><b>62) </b>Explain how NLP improves search engine results. [2]</h4></section>
  <section>
    <p>• Understands user intent rather than just matching keywords</p>
    <p>• Provides more relevant and accurate search results</p>
  </section>
</section>

<section>
  <section><h4><b>63) </b>Describe how virtual assistants use NLP. [2]</h4></section>
  <section>
    <p>• Process and understand spoken language from users</p>
    <p>• Interpret commands and provide appropriate actions or responses</p>
  </section>
</section>

<section>
  <section><h4><b>64) </b>Explain how NLP improves language translation. [2]</h4></section>
  <section>
    <p>• Analyses grammar, sentence structure, and context</p>
    <p>• Produces more accurate and natural translations</p>
  </section>
</section>

<section>
  <section><h4><b>65) </b>Describe how chatbots use NLP. [2]</h4></section>
  <section>
    <p>• Understand user queries by analysing language and intent</p>
    <p>• Generate relevant responses to simulate human-like conversation</p>
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

