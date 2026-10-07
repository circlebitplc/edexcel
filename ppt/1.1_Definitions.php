<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));


// Include Moodle's configuration and session libraries
require_once('./config.php'); // Update this with the correct path to your Moodle's config.php

// Check if the user is logged in
require_login();

// If the user is logged in, the rest of your code will execute
echo "<h1>Welcome, " . fullname($USER) . "!</h1>";
echo "<p>You are successfully logged in.</p>";

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?><!doctype html>
<html lang='en'>
<head>
    <meta charset='utf-8'>
    <title>IAL Unit 1 - Concepts of Computing</title>
    <meta name='description' content='IAL ICT Unit 1 Presentation'>
    <meta name='author' content='Enidu Batuwanthudawe'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>

    <link rel='stylesheet' href='<?= $pptBase ?>/css/reveal.min.css'>
    <link rel='stylesheet' href='<?= $pptBase ?>/css/theme/default.css' id='theme'>
    <link rel='stylesheet' href='<?= $pptBase ?>/css/custom.css'>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel='stylesheet' href='<?= $pptBase ?>/lib/css/zenburn.css'>

    <script src='<?= $pptBase ?>/js/moment.min.js'></script>
</head>

<body>
<div class='reveal'>
<div class='slides'>

<section>
    <h2>IAL ICT Unit 1</h2>
    <h3>1.1_Definitions.php</h3>
    <?= ppt_teacher_credit_markup() ?>
</section>

<section>
    <h2>Definitions and Relationships</h2>
</section>

<section>
    <section><h3>Computer</h3></section>
    <section>
    <p>A computer is an electronic device that processes digital inputs(data) to produce digital output (meaningful information).</p></section>
</section>

<section>
    <section><h3>Digital</h3></section>
    <section><p>Digital refers to the representation of data using binary code (0s and 1s), enabling processing by electronic devices.</p></section>
</section>

<section>
    <section><h3>Data</h3></section>
    <section><p>Data is raw facts and figures without context(No meanig full idea), such as numbers, words, and measurements.</p></section>
</section>

<section>
<section>
    <h3>Data Life Cycle</h3>
</section>

<section>
    <section><h3>1. Creation / Capture</h3></section>
</section>
	<section>
		<li>Data Entry</li>
	</section>
		<section>
			<p>Manually inputting new data into a system.</p>
		</section>
	<section>
		<li>Data Acquisition</li>
	</section>
		<section>
			<p>Collecting data from external sources.</p>
		</section> 
    <section>
		<li>Signal Reception</li>
	</section>
		<section>
			<p>Capturing signals from sensors and devices.</p>
		</section>
	
<section>
    <h3>2. Maintenance</h3>
    
</section>
	<section>
    <p>Keeping data accurate, relevant, and up to date over time so that the data can be usable.</p>
    
</section>

<section>
    <h3>3. Active Use / Processing</h3>
</section>
	<section>
    <p>Using data to produce information that supports decision-making.</p>
</section>

<section>
    <h3>4. Sharing / Distribution</h3>
	</section>
	<section>
    <p>Making data accessible to other users or systems.</p>
</section>

<section>
    <h3>5. Archiving</h3>
	</section>
	<section>
    <p>Storing data that is no longer actively used but may be needed later.</p>
</section>

<section>
    <h3>6. Deletion / Disposal</h3>
	</section>
	<section>
    <p>Securely removing data that is no longer required.</p>
</section>
</section>
<section>
<section>
    <h2>Basic Forms of Data</h2>
    </section>
	<section>
        <li>Text</li>
	</section>
	<section>
        <li>Audio</li>
	</section>
	<section>
        <li>Visual (Images, Videos)</li>
		</section>
</section>

<section>
<section>
    <h2>Classification of Data</h2>
	</section>
	<section>
    <ul>
		<li>Quantitative</li>
		</section>
	<section><p>Measurable data (e.g., height, weight).</p>
		</section>
	<section>
		<li>Qualitative</li>
		</section>
	<section><p>Descriptive data (e.g., color, taste).</p>
		</section>
	 
</section>

<section>
<section>    <h2>Nature of Data</h2>
    <ul>
 </section>
	<section>
		be collected and stored</li>
		</section>
	<section>      
  <li>Can be transmitted</li>
		</section>
	<section>    
    <li>Can be retrieved and processed</li>
</section> 
</section>

<section>
<section>
    <h2>Information</h2>
	</section>
	<section>
    <p>Information is processed data that has meaning and can be used to make decisions.</p>
</section>
</section>

<section>
<section>
    <h2>Characteristics of Good Information</h2>
	</section>
	<section>
        <li>Relevant</li>
		</section>
	<section>
        <li>Accurate</li>
		</section>
	<section>
        <li>Complete</li>
		</section>
	<section>
        <li>Consistent</li>
		</section>
	<section>
        <li>Timely</li>
		</section>
	<section>
        <li>Accessible</li></section>
	<section>
        <li>Understandable</li></section>
	<section>
        <li>Unique</li>
</section>
</section>

<section>
<section>
    <h2>Use of Information</h2>
	</section>
	<section>
        <li>Planning</li>
		</section>
	<section>
        <li>Knowledge acquisition</li>
		</section>
	<section>
        <li>Day-to-day operations</li>
		</section>
	<section>
        <li>Predictions and decision-making</li>
    
</section>
</section>

<section>
<section>
    <h2>Real-Life Example</h2>
	</section>
	<section>
    <p><strong>Shopping Example:</strong></p>
    <ul>
        <li><strong>Data:</strong> 1200, 750, 920</li>
        <li><strong>Information:</strong> These are prices of products in a store.</li>
        <li><strong>Knowledge:</strong> Product A is more expensive than Product B.</li>
        <li><strong>Wisdom:</strong> Wait for a discount before buying Product A.</li>
    </ul>
</section>
</section>

<section>
    <h2>Paper Style Questions</h2>
    <p>See handout for full list of practice questions and multiple-choice assessments.</p>
</section>

</div>
</div>

<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
<script src="<?= $pptBase ?>/js/reveal.min.js"></script>
<script>
Reveal.initialize({
    controls: true,
    progress: true,
    history: true,
    center: true,
    transition: 'default',
    dependencies: [
        { src: '<?= $pptBase ?>/plugin/markdown/marked.js', condition: () => !!document.querySelector('[data-markdown]') },
        { src: '<?= $pptBase ?>/plugin/markdown/markdown.js', condition: () => !!document.querySelector('[data-markdown]') },
        { src: '<?= $pptBase ?>/plugin/highlight/highlight.js', async: true, callback: () => hljs.initHighlightingOnLoad() }
    ]
});
</script>
</body>
</html>