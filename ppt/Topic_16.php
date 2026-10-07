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
	<section>
		<h4><b>1)</b> Artificial intelligence that allows systems to learn from data is known as?</h4>
				<p>A. Automation</p>
				<p>B. Machine learning</p>
				<p>C. Programming</p>
				<p>D. Simulation</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>2)</b> Machine learning differs from traditional programming because it:</h4>
				<p>A. Uses no data</p>
				<p>B. Cannot improve over time</p>
				<p>C. Learns patterns from data</p>
				<p>D. Requires no algorithms</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>3)</b> Which type of learning uses labelled data?</h4>
				<p>A. Unsupervised learning</p>
				<p>B. Reinforcement learning</p>
				<p>C. Supervised learning</p>
				<p>D. Random learning</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>4)</b> Which is an example of supervised learning?</h4>
				<p>A. Clustering</p>
				<p>B. Classification</p>
				<p>C. Grouping</p>
				<p>D. Pattern discovery</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>5)</b> Clustering is best described as:</h4>
				<p>A. Predicting values</p>
				<p>B. Using labelled data</p>
				<p>C. Grouping similar data</p>
				<p>D. Error checking</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>6)</b> Regression is used to:</h4>
				<p>A. Group data</p>
				<p>B. Predict continuous values</p>
				<p>C. Classify objects</p>
				<p>D. Detect anomalies</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>7)</b> Which is NOT part of machine learning components?</h4>
				<p>A. Algorithms</p>
				<p>B. Data</p>
				<p>C. Predictions</p>
				<p>D. Keyboard</p>
	</section> 
	<section><p><b>Answer: D</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>8)</b> Training data is used to:</h4>
				<p>A. Test accuracy</p>
				<p>B. Train the model</p>
				<p>C. Store results</p>
				<p>D. Remove errors</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>9)</b> Test data is used to:</h4>
				<p>A. Train model</p>
				<p>B. Evaluate model</p>
				<p>C. Store data</p>
				<p>D. Label data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>10)</b> Unsupervised learning uses:</h4>
				<p>A. Labelled data</p>
				<p>B. Structured data only</p>
				<p>C. Unlabelled data</p>
				<p>D. No data</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>11)</b> Which is an example of anomaly detection?</h4>
				<p>A. Spam filtering</p>
				<p>B. Fraud detection</p>
				<p>C. Translation</p>
				<p>D. Navigation</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>12)</b> Natural Language Processing (NLP) enables computers to:</h4>
				<p>A. Store images</p>
				<p>B. Understand human language</p>
				<p>C. Process numbers</p>
				<p>D. Encrypt data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>13)</b> Speech recognition allows computers to:</h4>
				<p>A. Display images</p>
				<p>B. Understand spoken words</p>
				<p>C. Store files</p>
				<p>D. Encrypt messages</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>14)</b> Image recognition is used for:</h4>
				<p>A. Audio processing</p>
				<p>B. Identifying objects in images</p>
				<p>C. Data storage</p>
				<p>D. Encryption</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>15)</b> Virtual Reality (VR) provides:</h4>
				<p>A. Real-world interaction only</p>
				<p>B. Fully simulated environment</p>
				<p>C. Text-based interface</p>
				<p>D. Data storage</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>16)</b> Augmented Reality (AR) combines:</h4>
				<p>A. Virtual world only</p>
				<p>B. Real world + digital overlay</p>
				<p>C. Audio + video</p>
				<p>D. Hardware + software</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>17)</b> Internet of Things (IoT) refers to:</h4>
				<p>A. Internet browsing</p>
				<p>B. Network of connected devices</p>
				<p>C. Software development</p>
				<p>D. Programming language</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>18)</b> IoT devices communicate using:</h4>
				<p>A. Paper</p>
				<p>B. Sensors and networks</p>
				<p>C. CDs</p>
				<p>D. Printers</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>19)</b> Which is an example of IoT device?</h4>
				<p>A. Notebook</p>
				<p>B. Smart thermostat</p>
				<p>C. Pencil</p>
				<p>D. Book</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>20)</b> The main goal of IoT is to:</h4>
				<p>A. Replace computers</p>
				<p>B. Automate tasks and improve efficiency</p>
				<p>C. Remove internet</p>
				<p>D. Reduce data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>
			<section> 
	<section>
		<h4><b>21)</b> A machine learning model gives very high accuracy on training data but poor accuracy on test data. This is an example of:</h4>
				<p>A. Underfitting</p>
				<p>B. Overfitting</p>
				<p>C. Clustering</p>
				<p>D. Regression</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>22)</b> Which action would MOST likely reduce overfitting in a machine learning model?</h4>
				<p>A. Increase training data</p>
				<p>B. Remove test data</p>
				<p>C. Reduce algorithm complexity</p>
				<p>D. Use only labelled data</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>23)</b> A dataset used in supervised learning must include:</h4>
				<p>A. Only input values</p>
				<p>B. Only output values</p>
				<p>C. Input-output pairs</p>
				<p>D. Random values</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>24)</b> Which scenario BEST represents unsupervised learning?</h4>
				<p>A. Predicting house prices</p>
				<p>B. Detecting spam emails using labelled data</p>
				<p>C. Grouping customers based on buying patterns</p>
				<p>D. Translating languages</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>25)</b> A bank uses machine learning to detect unusual transactions. Which technique is MOST appropriate?</h4>
				<p>A. Regression</p>
				<p>B. Classification</p>
				<p>C. Clustering</p>
				<p>D. Anomaly detection</p>
	</section> 
	<section><p><b>Answer: D</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>26)</b> Which statement about training and test data is correct?</h4>
				<p>A. Both are used to train the model</p>
				<p>B. Test data must be labelled and unseen</p>
				<p>C. Training data must be unlabelled</p>
				<p>D. Test data improves training speed</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>27)</b> Why is large data important in machine learning?</h4>
				<p>A. Reduces storage</p>
				<p>B. Improves accuracy of predictions</p>
				<p>C. Eliminates algorithms</p>
				<p>D. Removes need for testing</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>28)</b> Which is a limitation of machine learning systems?</h4>
				<p>A. Cannot process data</p>
				<p>B. Require no training</p>
				<p>C. Depend heavily on data quality</p>
				<p>D. Cannot make predictions</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>29)</b> A voice assistant misinterprets accents due to limited training data. This is an issue of:</h4>
				<p>A. Hardware failure</p>
				<p>B. Poor algorithm speed</p>
				<p>C. Biased dataset</p>
				<p>D. Network error</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>30)</b> Which application uses NLP MOST directly?</h4>
				<p>A. Face recognition</p>
				<p>B. Chatbots</p>
				<p>C. GPS navigation</p>
				<p>D. Image compression</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>31)</b> Which improvement would MOST enhance speech recognition accuracy?</h4>
				<p>A. Reducing storage</p>
				<p>B. Increasing training data variety</p>
				<p>C. Using fewer algorithms</p>
				<p>D. Removing microphones</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>32)</b> Image recognition in healthcare is mainly used to:</h4>
				<p>A. Store patient records</p>
				<p>B. Identify abnormalities in scans</p>
				<p>C. Improve network speed</p>
				<p>D. Encrypt data</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>33)</b> Which is a major ethical concern of image recognition?</h4>
				<p>A. Slow processing</p>
				<p>B. High storage use</p>
				<p>C. Privacy invasion</p>
				<p>D. Low accuracy</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>34)</b> In VR systems, latency (delay) must be minimized because:</h4>
				<p>A. It reduces storage</p>
				<p>B. It improves battery life</p>
				<p>C. It enhances user immersion</p>
				<p>D. It reduces hardware cost</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>35)</b> Which is a key limitation of VR?</h4>
				<p>A. Cannot display graphics</p>
				<p>B. Requires real-world objects</p>
				<p>C. Expensive hardware and motion sickness</p>
				<p>D. No user interaction</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>36)</b> Which scenario BEST describes augmented reality?</h4>
				<p>A. Playing a fully virtual game</p>
				<p>B. Watching a movie</p>
				<p>C. Viewing navigation directions overlaid on a real road</p>
				<p>D. Using a spreadsheet</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>37)</b> Why is AR more practical than VR in some applications?</h4>
				<p>A. It replaces reality</p>
				<p>B. It requires no devices</p>
				<p>C. It enhances real-world interaction</p>
				<p>D. It uses no data</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>38)</b> Which is the MOST critical risk in IoT systems?</h4>
				<p>A. High speed</p>
				<p>B. Data redundancy</p>
				<p>C. Security vulnerabilities</p>
				<p>D. Low storage</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>39)</b> Why is encryption important in IoT?</h4>
				<p>A. Reduces cost</p>
				<p>B. Increases speed</p>
				<p>C. Protects data during transmission</p>
				<p>D. Improves UI</p>
	</section> 
	<section><p><b>Answer: C</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>40)</b> Which type of attack overwhelms IoT devices with traffic?</h4>
				<p>A. Phishing</p>
				<p>B. DDoS</p>
				<p>C. Malware</p>
				<p>D. Spoofing</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
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

