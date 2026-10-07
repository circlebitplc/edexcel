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
  <h3>SECTION 3 – IRIS DATA SET</h3>
</section>
    <section>
  <section><h4><b>37) </b>Describe the purpose of the Iris data set. [2]</h4></section>
  <section>
    <p>• The Iris dataset is used to train and evaluate machine learning models</p>
    <p>• It allows models to classify flower species based on measurements such as sepal and petal size</p>
  </section>
</section>

<section>
  <section><h4><b>38) </b>Explain what is meant by labelled data. [2]</h4></section>
  <section>
    <p>• Labelled data includes input features along with the correct output (target value)</p>
    <p>• This enables the model to learn relationships between inputs and outputs during training</p>
  </section>
</section>

<section>
  <section><h4><b>39) </b>Identify the inputs and outputs in the Iris data set. [2]</h4></section>
  <section>
    <table border="1" cellpadding="5">
      <tr>
        <th>Inputs (Features)</th>
        <th>Output</th>
      </tr>
      <tr>
        <td>Sepal length, sepal width, petal length, petal width</td>
        <td>Species of flower (e.g. Setosa, Versicolor, Virginica)</td>
      </tr>
    </table>
  </section>
</section>

<section>
  <section><h4><b>40) </b>Define correlation. [1]</h4></section>
  <section><p>Correlation is a statistical measure that shows the strength and direction of the relationship between two variables.</p></section>
</section>

<section>
  <section><h4><b>41) </b>Explain how correlation affects prediction accuracy. [2]</h4></section>
  <section>
    <p>• Strong correlation improves prediction accuracy by providing useful relationships</p>
    <p>• Weak correlation provides little useful information, reducing model performance</p>
  </section>
</section>

<section>
  <section><h4><b>42) </b>Identify which variables have the highest correlation. [1]</h4></section>
  <section><p>Petal length and petal width.</p></section>
</section>

<section>
  <section><h4><b>43) </b>Explain why some variables are more useful than others. [2]</h4></section>
  <section>
    <p>• Variables with strong relationships to the output improve prediction accuracy</p>
    <p>• Less useful variables may add noise and reduce model performance</p>
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

