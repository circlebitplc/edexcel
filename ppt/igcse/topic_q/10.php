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

	<meta name='description' content='Chapter 10 – Online Information'>
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
					<h4>Chapter 10</h4>
					<h2>Online Information</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple-Choice Questions (MCQs)</h3>
</section>

<section>
  <section><h4><b>1) </b>A primary source is:</h4></section>
  <section><p>Answer: B. Data you created yourself</p></section>
</section>

<section>
  <section><h4><b>2) </b>Secondary sources include:</h4></section>
  <section><p>Answer: C. TV programs and books</p></section>
</section>

<section>
  <section><h4><b>3) </b>Using keywords helps search engines:</h4></section>
  <section><p>Answer: C. Find more accurate results</p></section>
</section>

<section>
  <section><h4><b>4) </b>The operator AND (+) is used to:</h4></section>
  <section><p>Answer: C. Combine keywords to narrow results</p></section>
</section>

<section>
  <section><h4><b>5) </b>Phrase matching (“ ”) is used to:</h4></section>
  <section><p>Answer: B. Search for an exact phrase</p></section>
</section>

<section>
  <section><h4><b>6) </b>Accurate information is:</h4></section>
  <section><p>Answer: B. Verified and correct</p></section>
</section>

<section>
  <section><h4><b>7) </b>Information that is too old may be:</h4></section>
  <section><p>Answer: B. Less useful for current needs</p></section>
</section>

<section>
  <section><h4><b>8) </b>Reliable information:</h4></section>
  <section><p>Answer: A. Matches other trusted sources</p></section>
</section>

<section>
  <section><h4><b>9) </b>Copyright prevents users from:</h4></section>
  <section><p>Answer: C. Copying or distributing others’ work illegally</p></section>
</section>

<section>
  <section><h4><b>10) </b>To avoid plagiarism, you should:</h4></section>
  <section><p>Answer: C. Rephrase and cite sources</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define a primary information source.</h4></section>
  <section><p>A primary information source is information created directly by the user.</p></section>
  <section><p>Examples include original research, surveys, and photographs.</p></section>
</section>

<section>
  <section><h4><b>12) </b>What is a secondary information source?</h4></section>
  <section><p>A secondary information source is information created by someone else.</p></section>
  <section><p>Examples include books, websites, and documentaries.</p></section>
</section>

<section>
  <section><h4><b>13) </b>Name two ways to improve search accuracy.</h4></section>
  <section><p>Use specific keywords.</p></section>
  <section><p>Use search operators such as AND, NOT, or quotation marks.</p></section>
</section>

<section>
  <section><h4><b>14) </b>What does the operator NOT (-) do in searches?</h4></section>
  <section><p>It excludes results containing a specific word.</p></section>
</section>

<section>
  <section><h4><b>15) </b>What is autofill in search engines?</h4></section>
  <section><p>Autofill suggests complete search phrases.</p></section>
  <section><p>Suggestions are based on popular or previous searches.</p></section>
</section>

<section>
  <section><h4><b>16) </b>State two characteristics of information that is fit for purpose.</h4></section>
  <section><p>Relevant to the task.</p></section>
  <section><p>Accurate and up to date.</p></section>
</section>

<section>
  <section><h4><b>17) </b>What does ‘unbiased information’ mean?</h4></section>
  <section><p>Information that presents facts fairly.</p></section>
  <section><p>It is not influenced by opinions or prejudice.</p></section>
</section>

<section>
  <section><h4><b>18) </b>Give one way to avoid plagiarism.</h4></section>
  <section><p>Paraphrase the information and reference the source.</p></section>
</section>

<section>
  <section><h4><b>19) </b>Why must old information sometimes be avoided?</h4></section>
  <section><p>It may be outdated.</p></section>
  <section><p>It may no longer be accurate or relevant.</p></section>
</section>

<section>
  <section><h4><b>20) </b>What details should be included when crediting a source?</h4></section>
  <section><p>Author.</p></section>
  <section><p>Title.</p></section>
  <section><p>Date.</p></section>
  <section><p>Website or publisher.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>How search engines match searches to web pages.</h4></section>
  <section><p>Search engines use crawlers to scan web pages.</p></section>
  <section><p>Keywords are indexed.</p></section>
  <section><p>Search terms are matched and pages ranked by relevance.</p></section>
</section>

<section>
  <section><h4><b>22) </b>How keywords, tools, and syntax improve searches.</h4></section>
  <section><p>Keywords narrow the topic.</p></section>
  <section><p>Search tools filter results.</p></section>
  <section><p>Syntax refines searches further.</p></section>
</section>

<section>
  <section><h4><b>23) </b>What makes information reliable and accurate.</h4></section>
  <section><p>It comes from trusted sources.</p></section>
  <section><p>Facts are verified.</p></section>
  <section><p>It matches other reputable sources.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Effects of bias and missing information.</h4></section>
  <section><p>Bias presents one-sided views.</p></section>
  <section><p>Missing information causes incorrect conclusions.</p></section>
</section>

<section>
  <section><h4><b>25) </b>Difference between referencing and plagiarism.</h4></section>
  <section><p>Referencing credits the original author.</p></section>
  <section><p>Plagiarism is using work without acknowledgment.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Copying text without citation.</h4></section>
  <section><p>The student presents someone else’s work as their own.</p></section>
  <section><p>This is dishonest and is plagiarism.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Improving a search using syntax.</h4></section>
  <section><p>Quotation marks search for exact phrases.</p></section>
  <section><p>Adding specific keywords narrows results.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Using old research.</h4></section>
  <section><p>The information may be outdated.</p></section>
  <section><p>Data, laws, or technology may have changed.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Checking reliability of conflicting sources.</h4></section>
  <section><p>Check author credentials.</p></section>
  <section><p>Compare with trusted sources.</p></section>
  <section><p>Check publication dates.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Using online content legally and ethically.</h4></section>
  <section><p>Paraphrase the information.</p></section>
  <section><p>Reference all sources.</p></section>
  <section><p>Respect copyright rules.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Importance of evaluating online information.</h4></section>
  <section><p>Evaluation ensures accuracy and reliability.</p></section>
  <section><p>It prevents misinformation.</p></section>
  <section><p>It supports informed decision-making.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Using search techniques to find quality information.</h4></section>
  <section><p>Identify relevant keywords.</p></section>
  <section><p>Use operators and phrase matching.</p></section>
  <section><p>Apply filters.</p></section>
  <section><p>Evaluate sources.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Impact of copyright and plagiarism.</h4></section>
  <section><p>Copyright protects creators.</p></section>
  <section><p>Plagiarism leads to penalties.</p></section>
  <section><p>Ethical use promotes fairness and trust.</p></section>
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

