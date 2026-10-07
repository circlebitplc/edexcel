<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?><!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='IGCSE Paper 1 – Question 3: Internet, Data, and Entertainment'>
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
					<h4>Paper 1</h4>
					<h2>QUESTION 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 
<section>
  <h2>QUESTION 3 – Internet, Data, and Entertainment</h2>
</section>

<section>
  <section><h4><b>3(a)</b> Two risks to data. (2)</h4></section>
  <section><p>Phishing attacks – Fake emails or websites trick users into revealing personal information such as passwords or bank details.</p></section>
  <section><p>Data interception (e.g. Man-in-the-Middle attacks) – Data is intercepted while being transmitted over a network.</p></section>
  <section><p>Human error – Accidental deletion, sending data to the wrong person, or misconfiguring security settings.</p></section>
</section>

<section>
  <section><h4><b>3(b)</b> How authentication protects data. (2)</h4></section>
  <section><p>A password protects data by ensuring that only authorised users can access a system or account. When a user enters their username and password, the system compares the entered password with the stored (usually encrypted or hashed) password in the database.

If the passwords match, access is granted. If they do not match, access is denied.</p></section>
  <section><p>Biometric authentication protects data by using a unique physical characteristic of the user, such as a fingerprint or facial recognition. When the user tries to access the system, the scanner captures their biometric data and compares it with the stored template in the system.

If the biometric data matches, access is granted. If it does not match, access is denied.</p></section>
</section>

<section>
  <section><h4><b>3(c)(i)</b> Files storing browsing habits. (1)</h4></section>
  <section><p>Cookies</p></section>
</section>

<section>
  <section><h4><b>3(c)(ii)</b> One benefit of transactional data. (2)</h4></section>
  <section><p>When a company collects transactional data (such as purchase history), it can analyse what products or services the individual regularly buys. The company can then recommend similar items or provide targeted discounts and special offers.</p></section> 
</section>

<section>
  <section><h4><b>3(d)</b> Two positive impacts of operating online. (4)</h4></section>
  <section><p>Wider audience reach<br>

Operating online allows entertainment organisations to reach a global audience instead of being limited to one physical location.<br>

For example, a film released on a streaming platform can be watched by millions of people worldwide at the same time.<br>

This has a positive impact because:<br>


More customers can access the content.<br>


Potential revenue increases.<br>


The organisation can expand its brand internationally.</p></section>
  <section><p>Reduced operating costs<br>


Running services online can reduce costs compared to physical venues or distribution.<br>

For example:

No need to print DVDs or CDs.,
Fewer physical stores required.,
Lower staffing and maintenance costs.

This positively impacts the organisation because:

Expenses decrease.

Profit margins increase.

Resources can be invested into creating more content.</p></section> 
</section>

<section>
  <section><h4><b>3(e)</b> Two effective search engine techniques. (4)</h4></section>
  <section><p>One way to use a search engine effectively is by using specific and relevant keywords instead of typing full sentences. When clear keywords are entered, the search engine can more accurately match the terms to web pages, reducing the number of irrelevant results and saving time.</p></section>
  <section><p>Another effective method is using advanced search techniques such as quotation marks to search for an exact phrase. This ensures that the search engine only shows results containing those words in the exact order, which increases the accuracy and relevance of the information found.</p></section>
</section>
<section>
	<section><h4><a href='<?= $dirBase ?>/q4.php'>Q4</a></h4> </section>
   </section>

			</div>
		</div>

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

