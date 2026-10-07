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

	<meta name='description' content='Part 1'>
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
					<h2> Concept of Computing</h2><br><h3>part 1</h3>
					<?= ppt_teacher_credit_markup() ?>
				</section>

			<section>
				<section>
					<h1>Definition of data</h1>
				</section>
				<section>
						<p>Data is a collection of raw facts, which has no meaningful idea <br>such as<br> numbers, <br>words,<br> measurements,<br> observations <br>or even just descriptions of things.</p>	
				</section>
				
			</section>
			<section>
    <section>
        <h1>Life cycle of data</h1>
    </section>

    <section>
        <h3>1) Generation or Capture</h3>
    </section>

    <section>
        <h3>1) Generation or Capture</h3>
        <p>The process of collecting or creating data from sources such as users, sensors, devices, transactions, websites, and applications.</p>
    </section>

    <section>
        <h3>1.1) Data Acquisition</h3>
        <p>The process of obtaining existing data from external sources, databases, files, websites, or other organizations for use within a system.</p>
    </section>

    <section>
        <h3>1.2) Data Entry</h3>
        <p>The process of entering new data into a computer system manually by users or automatically by devices and software applications.</p>
    </section>

    <section>
        <h3>1.3) Signal Reception</h3>
        <p>The process of receiving data signals from electronic devices, sensors, networks, or Internet of Things (IoT) devices for processing and storage.</p>
    </section>

    <section>
        <p>1) Generation or Capture</p>
        <p>2) Data Maintenance</p>
    </section>

    <section>
        <h3>Data Maintenance</h3>
        <p>The process of storing, organizing, updating, validating, and securing data to ensure its accuracy, consistency, and availability.</p>
    </section>

    <section>
        <p>1) Generation or Capture</p>
        <p>2) Data Maintenance</p>
        <p>3) Active Use</p>
    </section>

    <section>
        <h3>Active Use</h3>
        <p>Data is used for daily operations, decision-making, reporting, communication, and analysis within an organization.</p>
    </section>

    <section>
        <p>1) Generation or Capture</p>
        <p>2) Data Maintenance</p>
        <p>3) Active Use</p>
        <p>4) Data Sharing / Publication</p>
    </section>

    <section>
        <h3>Data Sharing / Publication</h3>
        <p>The process of distributing data or information to authorized users, departments, organizations, or the public when required.</p>
    </section>

    <section>
        <p>1) Generation or Capture</p>
        <p>2) Data Maintenance</p>
        <p>3) Active Use</p>
        <p>4) Data Sharing / Publication</p>
        <p>5) Archiving</p>
    </section>

    <section>
        <h3>Archiving</h3>
        <p>Inactive or rarely used data is moved to long-term storage for future reference, legal requirements, or historical purposes.</p>
    </section>

    <section>
        <p>1) Generation or Capture</p>
        <p>2) Data Maintenance</p>
        <p>3) Active Use</p>
        <p>4) Data Sharing / Publication</p>
        <p>5) Archiving</p>
        <p>6) Data Purging / Disposal</p>
    </section>

    <section>
        <h3>Data Purging / Disposal</h3>
        <p>Data that is no longer needed is permanently deleted from all storage locations according to data retention and security policies.</p>
    </section>
</section>
				
			<section>
				<section>
						<h3>Basic forms of data</h3>
				</section>
				<section>
						<h3>1) Text</h3>
				</section>
				<section>
						<h3>2) Audio</h3>
				</section>
				<section>
						<h3>3) Visual</h3>
				</section>
				<section>
						<p>1) Text</p>
						<p>2) Audio</p>
						<p>3) Visual</p>
				</section>
			</section>
			<section>
				<section>
						<h3>Classification of data</h3>
				</section>
				<section>
						<h3>1) Quantitative</h3>
						<p>Quantitative data may be used in computation and statistical test. It is concerned with measurements like height, weight, volume, length, size, humidity, speed, age etc. The tabular and diagrammatic presentation of data is also possible, in the form of charts, graphs, tables, etc. Further, the quantitative data can be classified as discrete or continuous data.</p>
						
				</section>
				<section>
						<h3>2) Qualitative</h3>
						<p>The nature of data is descriptive and so it is a bit difficult to analyze it. This type of data can be classified into categories, on the basis of physical attributes and properties of the object. The data is interpreted as spoken or written narratives rather than numbers. It is concerned with the data that is observable in terms of smell, appearance, taste, feel, texture, gender, nationality and so on.</p>
				</section>
				
			</section>
			<section>
				<section>
						<h3>Nature of Data</h3>
				</section>
				<section>
						<p>Data can be collected and stored</p>
						<p>Data can be transmitted</p>
						<p>Data can be obtained from the stored medium</p>
						<p>Data can be processed</p>
						<p>Data can be rearranged</p>
				</section>
			</section>
			<section>
				<section>
						<h3>Process of Data</h3>
				</section>
				<section>
						<p>Mathematical process</p>
						<p>Statistics process</p>
						<p>Organized process</p>
						
				</section>
			</section>
			<section>
				<section>
						<h3>Information</h3>
				</section>
				<section>
						<p>Information is organized or classified data, which has some meaningful values for the receiver. Information is the processed data on which decisions and actions are based.</p>
				</section>
			</section>
			<section>
				<section>
						<h3>Use of Information</h3>
				</section>
				<section>
						<p>For Planing</p>
						<p>For getting knowledge</p>
						<p>For daily work</p>
						<p>For predictions</p>
						<p>For determination</P>
				</section>
			</section>
			<section>
				<section>
						<h3>Characteristics of Information</h3>
				</section>
				<section>
						<h3>Relevant</h3>
						<p> must meet the requirements of the information consumer group.</p>
				</section>
				<section>
						<h3>accuracy</h3>
						<p>Information needs to be of high quality to be useful and accurate.  The information that is input into a data base is presumed to be perfect as well as accurate.  The information that is accessed is deemed reliable.  Flaws do arise with database design but do not let something in your control, accurate and reliable data, be one of them.  A database design that is accurate and reliable will help achieve the development of new business ideas as well as promoting the organizational goals. </p>
				</section>
				<section>
						<h3>completeness</h3>
						<p>Completeness is another attribute of high quality information.  Partial information may as well be incomplete information because it is only a small part of the picture.  Completeness is as necessary as accuracy when inputting data into a database.</p>
				</section>
				<section>
						<h3>consistency</h3>
						<p>Consistency is key when entering information into a database.  For example, with a column for a phone number entry 10 digits is the expected length of the field.  Once the fields have been set in the database, a number more or less than 10 digits will not be accepted.  The same applies for any field, whether it is an entry that requires a number, a series of numbers, an address, or a name, etc.  If the fields are not set to a specific limit for information then consistency is even more important. </p>
				</section>
				<section>
						<h3>uniqueness</h3>
						<p>Uniqueness is the fourth component of high quality information.  In order to add value to any organization, information must be unique and distinctive.  Information is a very essential part of any organization and if used properly can make a company competitive or can keep a company competitive. </p>
				</section>
				<section>
						<h3>timeliness</h3>
						<p>  New and current data is more valuable to organizations than old outdated information.  Especially now, in this era of high technological advances, out-of-date information can keep a company from achieving their goals or from surviving in a competitive arena.  The information does not necessarily need to be out of date to have effect, it just needs to not be the most current.  Real-time information is an element of timeliness.   </p>
				</section>
				<section>
						<h3>Accessible</h3>
						<p>it must be accessible and within reach. If information consumers are not able to access the data when they need it, it can lead to frustration. An example of an issue that can cause computer information to be inaccessible is performance. If a system is slow or goes down periodically, it can affect a consumer's ability to perform job duties effectively.</p>
				</section>
				<section>
						<h3>Understandable</h3>
						<p>Unambiguous and understandable computer information means it is explicit, clear and concise. There is no chance the data can be misinterpreted or misunderstood. On the other hand, ambiguous information can result in multiple interpretations of the same data, which can cause confusion and discord in a system's framework. Even if the information is clarified, the damage to a consumer's perception of the data may be permanent or may take time to reverse</p>
				</section>
				<section>
						<p>Relevant</p>
						<p>Accuracy</p>
						<p>Completeness</p>
						<p>Consistency</p>
						<p>Uniqueness</p>
						<p>Timeliness</P>
						<p>Accessible</p>
						<p>Understandable</p>
						<p>Valuable</p>
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

