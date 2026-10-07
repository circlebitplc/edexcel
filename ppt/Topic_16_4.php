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
  <h3>SECTION 4 – SUPERVISED & UNSUPERVISED LEARNING</h3>
</section>
    <section>
  <section><h4><b>44) </b>Define supervised learning. [1]</h4></section>
  <section><p>Supervised learning is a type of machine learning where a model is trained using labelled data, meaning each input is paired with a correct output.</p></section>
</section>

<section>
  <section><h4><b>45) </b>Explain how supervised learning works. [3]</h4></section>
  <section>
    <p>• The model is trained using data that includes inputs and correct outputs (labels)</p>
    <p>• It learns the relationship between inputs and outputs by analysing the data</p>
    <p>• The model adjusts its parameters to minimise errors and make accurate predictions on new data</p>
  </section>
</section>

<section>
  <section><h4><b>46) </b>Define unsupervised learning. [1]</h4></section>
  <section><p>Unsupervised learning is a type of machine learning where a model is trained using unlabelled data without predefined outputs.</p></section>
</section>

<section>
  <section><h4><b>47) </b>Explain how unsupervised learning works. [3]</h4></section>
  <section>
    <p>• The model is given data without labels or expected outputs</p>
    <p>• It identifies hidden patterns, relationships, or structures in the data</p>
    <p>• It groups similar data (clustering) or finds trends without prior guidance</p>
  </section>
</section>

<section>
  <section><h4><b>48) </b>Compare supervised and unsupervised learning. [3]</h4></section>
  <section>
    <table border="1" cellpadding="5">
      <tr>
        <th>Supervised Learning</th>
        <th>Unsupervised Learning</th>
      </tr>
      <tr>
        <td>Uses labelled data</td>
        <td>Uses unlabelled data</td>
      </tr>
      <tr>
        <td>Predicts specific outputs</td>
        <td>Finds hidden patterns</td>
      </tr>
      <tr>
        <td>Requires known answers</td>
        <td>No predefined answers</td>
      </tr>
    </table>
  </section>
</section>

<section>
  <section><h4><b>49) </b>Explain when supervised learning should be used. [2]</h4></section>
  <section>
    <p>• When labelled data is available and outputs are known</p>
    <p>• Suitable for tasks like classification and regression requiring accurate predictions</p>
  </section>
</section>

<section>
  <section><h4><b>50) </b>Explain when unsupervised learning should be used. [2]</h4></section>
  <section>
    <p>• When data is unlabelled and no outputs are predefined</p>
    <p>• Useful for discovering patterns, trends, or groupings such as clustering</p>
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

