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

	<meta name='description' content='Topic 14 MCQ – Secction D'>
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
					<h4>Topic 14 MCQ</h4>
					<h2> Secction D</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

<section> 
    <section>
        <h4><b>1)</b> Intelligent Transportation Systems (ITS) are designed to:</h4>
		<p>A. Increase traffic congestion</p>
		<p>B. Improve transport planning and operations</p>
		<p>C. Remove public transport</p>
		<p>D. Delete traffic data</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>2)</b> ITS uses sensors and cameras to:</h4>
		<p>A. Delete data</p>
		<p>B. Collect real-time traffic information</p>
		<p>C. Increase delays</p>
		<p>D. Remove vehicles</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>3)</b> Dynamic scheduling in ITS allows:</h4>
		<p>A. Fixed bus routes only</p>
		<p>B. Adjustment of routes based on demand</p>
		<p>C. Removal of timetables</p>
		<p>D. Increased congestion</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>4)</b> Incident management systems help to:</h4>
		<p>A. Ignore accidents</p>
		<p>B. Respond quickly to traffic disruptions</p>
		<p>C. Delete road data</p>
		<p>D. Reduce monitoring</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>5)</b> Timetabling systems use algorithms to:</h4>
		<p>A. Predict passenger demand</p>
		<p>B. Delete routes</p>
		<p>C. Increase waiting time</p>
		<p>D. Remove transport services</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>6)</b> Location-based data in ITS can be collected from:</h4>
		<p>A. GPS devices</p>
		<p>B. Traffic cameras</p>
		<p>C. User smartphones</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>7)</b> User-generated data in ITS may include:</h4>
		<p>A. Traffic reports</p>
		<p>B. Road condition updates</p>
		<p>C. Incident alerts</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>8)</b> Community-based reporting improves:</h4>
		<p>A. Traffic awareness</p>
		<p>B. Data deletion</p>
		<p>C. RAID performance</p>
		<p>D. BIOS settings</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>9)</b> Fleet management systems track vehicles using:</h4>
		<p>A. RAID</p>
		<p>B. GPS and telematics</p>
		<p>C. BIOS</p>
		<p>D. Firmware</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>10)</b> Telematics combines:</h4>
		<p>A. Telecommunications and informatics</p>
		<p>B. Storage and RAM</p>
		<p>C. RAID and BIOS</p>
		<p>D. Encryption and firmware</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>11)</b> Fleet management improves efficiency by:</h4>
		<p>A. Ignoring routes</p>
		<p>B. Optimising routes</p>
		<p>C. Increasing fuel use</p>
		<p>D. Removing drivers</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>12)</b> Real-time vehicle tracking allows managers to:</h4>
		<p>A. Monitor driver performance</p>
		<p>B. Delete vehicles</p>
		<p>C. Remove tracking</p>
		<p>D. Increase delays</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>13)</b> ITS reduces fuel consumption by:</h4>
		<p>A. Increasing congestion</p>
		<p>B. Optimising routes</p>
		<p>C. Removing timetables</p>
		<p>D. Deleting schedules</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>14)</b> Traffic flow analysis helps to:</h4>
		<p>A. Identify bottlenecks</p>
		<p>B. Increase congestion</p>
		<p>C. Remove vehicles</p>
		<p>D. Delete data</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>15)</b> An expert system is designed to:</h4>
		<p>A. Replace all employees</p>
		<p>B. Provide decision support in a specific domain</p>
		<p>C. Remove data</p>
		<p>D. Increase hardware</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>16)</b> Expert systems use a:</h4>
		<p>A. Knowledge base</p>
		<p>B. RAID system</p>
		<p>C. BIOS</p>
		<p>D. Hypervisor</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>17)</b> The knowledge base contains:</h4>
		<p>A. Random data</p>
		<p>B. Facts and rules about a specific domain</p>
		<p>C. Hardware details</p>
		<p>D. Firmware code</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>18)</b> The inference engine in an expert system:</h4>
		<p>A. Stores raw data</p>
		<p>B. Applies rules to reach conclusions</p>
		<p>C. Deletes knowledge</p>
		<p>D. Removes rules</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>19)</b> An expert system can assist in:</h4>
		<p>A. Medical diagnosis</p>
		<p>B. Hardware repair only</p>
		<p>C. RAID configuration</p>
		<p>D. BIOS flashing</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>20)</b> Expert systems are useful when:</h4>
		<p>A. No expertise is required</p>
		<p>B. Specialist knowledge is needed</p>
		<p>C. Data is irrelevant</p>
		<p>D. Decisions are random</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>21)</b> A static knowledge base means it:</h4>
		<p>A. Updates automatically</p>
		<p>B. Does not change without manual input</p>
		<p>C. Deletes data</p>
		<p>D. Removes rules</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>22)</b> AI-enhanced expert systems can:</h4>
		<p>A. Learn from new data</p>
		<p>B. Remove inference</p>
		<p>C. Delete rules</p>
		<p>D. Disable processing</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>23)</b> An example of an expert system application is:</h4>
		<p>A. Loan approval assessment</p>
		<p>B. BIOS configuration</p>
		<p>C. RAID setup</p>
		<p>D. Hypervisor management</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>24)</b> Decision trees are commonly used in:</h4>
		<p>A. Expert systems</p>
		<p>B. RAID</p>
		<p>C. Firmware</p>
		<p>D. BIOS</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>25)</b> Rule-based systems operate using:</h4>
		<p>A. If-then rules</p>
		<p>B. Random algorithms</p>
		<p>C. RAID arrays</p>
		<p>D. BIOS settings</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>26)</b> Expert systems improve decision-making by:</h4>
		<p>A. Providing consistent recommendations</p>
		<p>B. Deleting data</p>
		<p>C. Removing knowledge</p>
		<p>D. Ignoring rules</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>27)</b> One limitation of expert systems is:</h4>
		<p>A. They require accurate and updated knowledge</p>
		<p>B. They replace all human expertise</p>
		<p>C. They reduce costs only</p>
		<p>D. They improve hardware</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>28)</b> Knowledge acquisition involves:</h4>
		<p>A. Collecting expert knowledge</p>
		<p>B. Deleting data</p>
		<p>C. Removing rules</p>
		<p>D. Disabling systems</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>29)</b> Fleet management data may include:</h4>
		<p>A. Vehicle speed</p>
		<p>B. Fuel usage</p>
		<p>C. Driver behaviour</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>30)</b> ITS can improve public transport by:</h4>
		<p>A. Reducing reliability</p>
		<p>B. Increasing punctuality</p>
		<p>C. Removing timetables</p>
		<p>D. Deleting routes</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>31)</b> Traffic prediction systems use:</h4>
		<p>A. Historical and real-time data</p>
		<p>B. BIOS updates</p>
		<p>C. RAID</p>
		<p>D. Firmware</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>32)</b> Expert systems reduce risk by:</h4>
		<p>A. Providing structured decision support</p>
		<p>B. Deleting data</p>
		<p>C. Removing compliance</p>
		<p>D. Increasing uncertainty</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>33)</b> Inference engines may use:</h4>
		<p>A. Forward chaining</p>
		<p>B. Backward chaining</p>
		<p>C. Both</p>
		<p>D. Neither</p>
    </section> 
    <section><p><b>Answer: C</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>34)</b> ITS improves safety by:</h4>
		<p>A. Ignoring accidents</p>
		<p>B. Detecting hazards early</p>
		<p>C. Removing cameras</p>
		<p>D. Deleting reports</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>35)</b> Telematics systems transmit data via:</h4>
		<p>A. Communication networks</p>
		<p>B. RAID</p>
		<p>C. BIOS</p>
		<p>D. Firmware</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>36)</b> Expert systems are particularly effective in:</h4>
		<p>A. Well-defined problem domains</p>
		<p>B. Random decisions</p>
		<p>C. Hardware installation</p>
		<p>D. RAID management</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>37)</b> A major benefit of ITS is:</h4>
		<p>A. Increased congestion</p>
		<p>B. Improved transport efficiency</p>
		<p>C. Reduced monitoring</p>
		<p>D. Deleted data</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>38)</b> Knowledge representation in expert systems must be:</h4>
		<p>A. Clear and structured</p>
		<p>B. Random</p>
		<p>C. Deleted</p>
		<p>D. Encrypted only</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>39)</b> Fleet optimisation can reduce:</h4>
		<p>A. Fuel costs</p>
		<p>B. Delivery times</p>
		<p>C. Environmental impact</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>40)</b> The overall aim of ITS and expert systems is to:</h4>
		<p>A. Increase system complexity</p>
		<p>B. Improve decision-making and efficiency</p>
		<p>C. Remove data</p>
		<p>D. Reduce performance</p>
    </section> 
    <section><p><b>Answer: B</b>  <br><a href="<?= $dirBase ?>/IAL_topic_14_E.php">Section E</a></p></section> 
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

