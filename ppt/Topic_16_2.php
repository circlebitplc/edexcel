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
  <h3>SECTION 2 – DATA, TRAINING, PREDICTIONS</h3>
</section>
<section>
  <section><h4><b>26) </b>Explain why large amounts of data are required in machine learning. [2]</h4></section>
  <section>
    <p>• Large datasets allow the model to learn a wide range of patterns and relationships</p>
    <p>• More data improves accuracy, helps generalisation, and reduces errors such as overfitting</p>
  </section>
</section>

<section>
  <section><h4><b>27) </b>Describe how data quality affects machine learning. [3]</h4></section>
  <section>
    <p>• High-quality data (accurate, complete, relevant) improves model accuracy</p>
    <p>• Poor-quality data (missing values, noise, incorrect labels) leads to incorrect pattern learning</p>
    <p>• Biased data can cause unfair or unreliable predictions</p>
  </section>
</section>

<section>
  <section><h4><b>28) </b>Identify different types of data used in machine learning. [2]</h4></section>
  <section>
    <p>• Numerical data (e.g. age, temperature, price)</p>
    <p>• Categorical data (e.g. gender, product type)</p>
    <p>• Text data (e.g. emails, reviews, documents)</p>
    <p>• Image/audio data (e.g. photos, speech recordings)</p>
    <p>(Any two)</p>
  </section>
</section>

<section>
  <section><h4><b>29) </b>Define training in machine learning. [1]</h4></section>
  <section><p>Training is the process of feeding data into a machine learning model so it can learn patterns and relationships between inputs and outputs.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Explain the purpose of a training set. [2]</h4></section>
  <section>
    <p>• A training set provides input data with correct outputs to teach the model</p>
    <p>• It enables the model to learn patterns and adjust parameters to make predictions</p>
  </section>
</section>

<section>
  <section><h4><b>31) </b>Explain how a model learns from training data. [3]</h4></section>
  <section>
    <p>• The model identifies relationships between inputs and outputs in the training data</p>
    <p>• It compares predictions with actual outputs to calculate error</p>
    <p>• It adjusts internal parameters to reduce errors and improve accuracy</p>
  </section>
</section>

<section>
  <section><h4><b>32) </b>Define test set. [1]</h4></section>
  <section><p>A test set is a separate dataset used to evaluate the performance of a trained model.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Explain the purpose of a test set. [2]</h4></section>
  <section>
    <p>• It evaluates how well the model performs on unseen data</p>
    <p>• It provides an unbiased measure of accuracy and generalisation</p>
  </section>
</section>

<section>
  <section><h4><b>34) </b>Explain why test data must be unseen. [2]</h4></section>
  <section>
    <p>• Ensures fair and realistic evaluation of the model</p>
    <p>• Prevents memorisation, which would give misleadingly high accuracy</p>
  </section>
</section>

<section>
  <section><h4><b>35) </b>Define predictions in machine learning. [1]</h4></section>
  <section><p>Predictions are the outputs produced by a trained model when given new input data.</p></section>
</section>

<section>
  <section><h4><b>36) </b>Explain how predictions are made using trained models. [2]</h4></section>
  <section>
    <p>• The model applies learned patterns to new input data</p>
    <p>• It processes inputs through learned parameters to generate outputs</p>
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

