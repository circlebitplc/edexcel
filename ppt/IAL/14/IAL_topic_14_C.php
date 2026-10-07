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

	<meta name='description' content='Topic 14 MCQ – Secction C'>
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
					<h2> Secction C</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

<section> 
    <section>
        <h4><b>1)</b> Customer Relationship Management (CRM) systems are used to:</h4>
		<p>A. Delete customer data</p>
		<p>B. Manage customer interactions</p>
		<p>C. Configure BIOS</p>
		<p>D. Install firmware</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>2)</b> CRM systems store data about:</h4>
		<p>A. Hardware only</p>
		<p>B. Customer behaviour and preferences</p>
		<p>C. RAID configurations</p>
		<p>D. Hypervisors</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>3)</b> CRM systems help improve:</h4>
		<p>A. Customer engagement</p>
		<p>B. Hardware performance</p>
		<p>C. Encryption speed</p>
		<p>D. Data deletion</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>4)</b> Synchronous marketing events allow organisations to:</h4>
		<p>A. Send messages instantly across platforms</p>
		<p>B. Delete customer records</p>
		<p>C. Reduce marketing</p>
		<p>D. Remove automation</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>5)</b> CRM systems can identify:</h4>
		<p>A. Server performance</p>
		<p>B. Buying trends</p>
		<p>C. RAID issues</p>
		<p>D. BIOS updates</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>6)</b> Loyalty schemes are designed to:</h4>
		<p>A. Reduce customer retention</p>
		<p>B. Reward repeat customers</p>
		<p>C. Remove discounts</p>
		<p>D. Delete purchase history</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>7)</b> Loyalty cards are often linked to:</h4>
		<p>A. Inventory only</p>
		<p>B. CRM and EPOS systems</p>
		<p>C. RAID systems</p>
		<p>D. Firmware updates</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>8)</b> Customer retention refers to:</h4>
		<p>A. Attracting new customers only</p>
		<p>B. Keeping existing customers</p>
		<p>C. Removing loyalty</p>
		<p>D. Deleting data</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>9)</b> Upselling involves:</h4>
		<p>A. Selling a cheaper product</p>
		<p>B. Encouraging customers to buy a more expensive option</p>
		<p>C. Removing products</p>
		<p>D. Deleting customer data</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>10)</b> Cross-selling involves:</h4>
		<p>A. Selling unrelated products</p>
		<p>B. Suggesting complementary products</p>
		<p>C. Deleting orders</p>
		<p>D. Reducing stock</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>11)</b> CRM systems improve marketing by:</h4>
		<p>A. Ignoring customer data</p>
		<p>B. Personalising promotions</p>
		<p>C. Removing advertisements</p>
		<p>D. Disabling campaigns</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>12)</b> Customer profiles in CRM include:</h4>
		<p>A. Purchase history</p>
		<p>B. Contact details</p>
		<p>C. Preferences</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>13)</b> CRM analytics help organisations to:</h4>
		<p>A. Predict customer behaviour</p>
		<p>B. Reduce reporting</p>
		<p>C. Delete transactions</p>
		<p>D. Remove data</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>14)</b> Follow-up emails after purchases are used to:</h4>
		<p>A. Delete transactions</p>
		<p>B. Improve customer satisfaction</p>
		<p>C. Remove customers</p>
		<p>D. Reduce loyalty</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>15)</b> Customer complaints recorded in CRM help organisations to:</h4>
		<p>A. Ignore feedback</p>
		<p>B. Improve services</p>
		<p>C. Delete data</p>
		<p>D. Increase risk</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>16)</b> Management Information Systems (MIS) provide:</h4>
		<p>A. Raw data only</p>
		<p>B. Processed information for managers</p>
		<p>C. BIOS updates</p>
		<p>D. RAID reports</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>17)</b> MIS supports decision-making by providing:</h4>
		<p>A. Outdated data</p>
		<p>B. Accurate and relevant information</p>
		<p>C. Deleted records</p>
		<p>D. Random data</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>18)</b> Data used by MIS may come from:</h4>
		<p>A. EPOS</p>
		<p>B. CRM</p>
		<p>C. Financial databases</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>19)</b> MIS dashboards present information in:</h4>
		<p>A. Graphical format</p>
		<p>B. BIOS code</p>
		<p>C. RAID configuration</p>
		<p>D. Firmware language</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>20)</b> Structured data in MIS refers to:</h4>
		<p>A. Organised data in predefined formats</p>
		<p>B. Random data</p>
		<p>C. Deleted files</p>
		<p>D. Corrupted records</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>21)</b> Unstructured data may include:</h4>
		<p>A. Emails</p>
		<p>B. Videos</p>
		<p>C. Social media posts</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>22)</b> Data cleaning in MIS ensures:</h4>
		<p>A. Data duplication</p>
		<p>B. Data accuracy</p>
		<p>C. Data deletion</p>
		<p>D. Data encryption removal</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>23)</b> Case studies demonstrate that MIS can:</h4>
		<p>A. Replace managers</p>
		<p>B. Identify operational inefficiencies</p>
		<p>C. Delete production data</p>
		<p>D. Remove reports</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>24)</b> Production downtime analysis helps organisations to:</h4>
		<p>A. Ignore inefficiencies</p>
		<p>B. Improve productivity</p>
		<p>C. Increase delays</p>
		<p>D. Remove staff</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>25)</b> Managers use MIS reports to:</h4>
		<p>A. Make informed decisions</p>
		<p>B. Delete orders</p>
		<p>C. Remove compliance</p>
		<p>D. Disable monitoring</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>26)</b> Trend analysis in MIS helps to:</h4>
		<p>A. Identify patterns over time</p>
		<p>B. Delete customer data</p>
		<p>C. Remove stock</p>
		<p>D. Reduce storage</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>27)</b> CRM data can support:</h4>
		<p>A. Targeted advertising</p>
		<p>B. Random marketing</p>
		<p>C. Reduced engagement</p>
		<p>D. Deleting campaigns</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>28)</b> Segmentation in marketing divides customers into:</h4>
		<p>A. Hardware groups</p>
		<p>B. Groups based on characteristics</p>
		<p>C. RAID arrays</p>
		<p>D. BIOS categories</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>29)</b> Customer lifetime value refers to:</h4>
		<p>A. One purchase</p>
		<p>B. Total revenue generated from a customer</p>
		<p>C. Stock levels</p>
		<p>D. Encryption keys</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>30)</b> Personalised offers increase:</h4>
		<p>A. Customer loyalty</p>
		<p>B. Downtime</p>
		<p>C. Data loss</p>
		<p>D. Risk</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>31)</b> Automated marketing campaigns use:</h4>
		<p>A. CRM systems</p>
		<p>B. BIOS</p>
		<p>C. RAID</p>
		<p>D. Hypervisors</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>32)</b> Sales forecasting is supported by:</h4>
		<p>A. CRM analytics</p>
		<p>B. Firmware</p>
		<p>C. RAID configuration</p>
		<p>D. BIOS</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>33)</b> Accurate MIS reports depend on:</h4>
		<p>A. Clean and validated data</p>
		<p>B. Random inputs</p>
		<p>C. Deleted records</p>
		<p>D. Corrupted files</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>34)</b> MIS systems often include:</h4>
		<p>A. Query tools</p>
		<p>B. Dashboards</p>
		<p>C. Reporting tools</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>35)</b> Customer behaviour data can reveal:</h4>
		<p>A. Hardware performance</p>
		<p>B. Buying patterns</p>
		<p>C. RAID failures</p>
		<p>D. BIOS settings</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>36)</b> Real-time reporting allows managers to:</h4>
		<p>A. React quickly to changes</p>
		<p>B. Ignore trends</p>
		<p>C. Delete information</p>
		<p>D. Remove staff</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>37)</b> CRM integration with EPOS enables:</h4>
		<p>A. Linking sales data with customer profiles</p>
		<p>B. Removing loyalty</p>
		<p>C. Deleting transactions</p>
		<p>D. Disabling marketing</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>38)</b> Data-driven decisions reduce:</h4>
		<p>A. Guesswork</p>
		<p>B. Accuracy</p>
		<p>C. Efficiency</p>
		<p>D. Security</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>39)</b> MIS supports organisational goals by:</h4>
		<p>A. Providing strategic insights</p>
		<p>B. Removing monitoring</p>
		<p>C. Deleting dashboards</p>
		<p>D. Reducing analysis</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>40)</b> The purpose of CRM and MIS systems together is to:</h4>
		<p>A. Improve customer relationships and decision-making</p>
		<p>B. Remove automation</p>
		<p>C. Delete transactions</p>
		<p>D. Reduce compliance</p>
    </section> 
    <section><p><b>Answer: A</b>  <br><a href="<?= $dirBase ?>/IAL_topic_14_B.php">Section B</a></p></section> 
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

