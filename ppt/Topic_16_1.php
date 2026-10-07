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
  <h3>SECTION A – MACHINE LEARNING INTRODUCTION</h3>
</section>
 <section>
  <section><h4><b>1) </b>Define artificial intelligence (AI). [1]</h4></section>
  <section><p>Artificial intelligence (AI) is the ability of a computer system to simulate human intelligence by performing tasks such as learning, problem-solving, decision-making, and pattern recognition.</p></section>
</section>

<section>
  <section><h4><b>2) </b>Define machine learning. [1]</h4></section>
  <section><p>Machine learning is a subset of artificial intelligence where computer systems learn from data and improve their performance without being explicitly programmed.</p></section>
</section>

<section>
  <section><h4><b>3) </b>Explain the difference between machine learning and expert systems. [2]</h4></section>
  <section>
    <table border="1" cellpadding="5">
      <tr>
        <th>Machine Learning</th>
        <th>Expert Systems</th>
      </tr>
      <tr>
        <td>Learns automatically from data</td>
        <td>Uses pre-programmed rules</td>
      </tr>
      <tr>
        <td>Improves over time</td>
        <td>Does not learn unless updated</td>
      </tr>
      <tr>
        <td>Adapts to new situations</td>
        <td>Follows fixed decision rules</td>
      </tr>
    </table>
  </section>
</section>

<section>
  <section><h4><b>4) </b>Explain why machine learning systems are not based on pre-programmed rules. [2]</h4></section>
  <section>
    <p>• Machine learning systems learn patterns directly from data rather than relying on fixed instructions</p>
    <p>• This allows them to adapt to new data and make predictions even when situations are not pre-programmed</p>
  </section>
</section>

<section>
  <section><h4><b>5) </b>Describe how machine learning identifies patterns in data. [3]</h4></section>
  <section>
    <p>• The system analyses large datasets using algorithms</p>
    <p>• It identifies relationships or trends between variables</p>
    <p>• A model is created and used to make predictions or decisions</p>
  </section>
</section>

<section>
  <section><h4><b>6) </b>Explain how machine learning improves over time. [2]</h4></section>
  <section>
    <p>• It learns from new data and feedback continuously</p>
    <p>• The model adjusts parameters to reduce errors and improve accuracy</p>
  </section>
</section>

<section>
  <section><h4><b>7) </b>Identify three sectors where machine learning is used. [3]</h4></section>
  <section>
    <p>• Healthcare (e.g. disease diagnosis)</p>
    <p>• Finance (e.g. fraud detection)</p>
    <p>• Retail (e.g. recommendation systems)</p>
  </section>
</section>

<section>
  <section><h4><b>8) </b>Explain why machine learning provides a competitive advantage to organizations. [3]</h4></section>
  <section>
    <p>• Enables fast and accurate analysis of large datasets</p>
    <p>• Improves decision-making and efficiency</p>
    <p>• Allows personalised services, increasing customer satisfaction and profit</p>
  </section>
</section>

<section>
  <section><h4><b>9) </b>Identify the four main features of a machine learning system. [4]</h4></section>
  <section>
    <p>• Data (used for training)</p>
    <p>• Algorithms (process the data)</p>
    <p>• Model (learned representation)</p>
    <p>• Output/predictions</p>
  </section>
</section>

<section>
  <section><h4><b>10) </b>Define an algorithm. [1]</h4></section>
  <section><p>An algorithm is a step-by-step set of instructions used to solve a problem or perform a task.</p></section>
</section>

<section>
  <section><h4><b>11) </b>Explain the role of algorithms in machine learning. [2]</h4></section>
  <section>
    <p>• Algorithms process data and identify patterns</p>
    <p>• They enable learning by building models for predictions or decisions</p>
  </section>
</section>

<section>
  <section><h4><b>12) </b>Describe how algorithms improve performance over time. [3]</h4></section>
  <section>
    <p>• Algorithms learn from training data</p>
    <p>• They adjust internal parameters to reduce errors</p>
    <p>• This increases accuracy and improves decision-making</p>
  </section>
</section>

<section>
  <section><h4><b>13) </b>Explain why machine learning algorithms can be complex. [2]</h4></section>
  <section>
    <p>• They process large volumes of data and identify complex patterns</p>
    <p>• They involve advanced mathematical models and multiple variables</p>
  </section>
</section>

<section>
  <section><h4><b>14) </b>Define regression problems. [1]</h4></section>
  <section><p>Regression problems involve predicting a continuous numerical value based on input data.</p></section>
</section>

<section>
  <section><h4><b>15) </b>Explain how regression problems are solved using machine learning. [2]</h4></section>
  <section>
    <p>• A model is trained on historical data to identify relationships</p>
    <p>• The model predicts continuous values for new data</p>
  </section>
</section>

<section>
  <section><h4><b>16) </b>Give one example of a regression problem. [1]</h4></section>
  <section><p>Predicting house prices based on size, location, and number of rooms.</p></section>
</section>

<section>
  <section><h4><b>17) </b>Define classification problems. [1]</h4></section>
  <section><p>Classification problems involve assigning data into predefined categories or classes.</p></section>
</section>

<section>
  <section><h4><b>18) </b>Explain how classification problems work. [2]</h4></section>
  <section>
    <p>• A model is trained using labelled data</p>
    <p>• It assigns new data to categories based on learned patterns</p>
  </section>
</section>

<section>
  <section><h4><b>19) </b>Give one example of a classification problem. [1]</h4></section>
  <section><p>Email spam detection (spam or not spam).</p></section>
</section>

<section>
  <section><h4><b>20) </b>Define clustering problems. [1]</h4></section>
  <section><p>Clustering problems involve grouping data into clusters based on similarities without predefined labels.</p></section>
</section>

<section>
  <section><h4><b>21) </b>Explain how clustering works in machine learning. [2]</h4></section>
  <section>
    <p>• It groups similar data points based on shared characteristics</p>
    <p>• It identifies patterns without using labelled data</p>
  </section>
</section>

<section>
  <section><h4><b>22) </b>Give one example of clustering. [1]</h4></section>
  <section><p>Grouping customers based on purchasing behaviour.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Define anomaly detection. [1]</h4></section>
  <section><p>Anomaly detection is the process of identifying unusual or abnormal data that does not follow expected patterns.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Explain how anomaly detection is used. [2]</h4></section>
  <section>
    <p>• It identifies unusual behaviour by comparing with normal patterns</p>
    <p>• It is used in areas like fraud detection</p>
  </section>
</section>

<section>
  <section><h4><b>25) </b>Give one real-world example of anomaly detection. [1]</h4></section>
  <section><p>Detecting fraudulent credit card transactions.</p></section>
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

