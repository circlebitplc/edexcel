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

	<meta name='description' content='Topic 14 MCQ – Secction A'>
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
					<h2> Secction A</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

<section> 
    <section>
        <h4><b>1)</b> IT systems in organisations are primarily used to:</h4>
		<p>A. Increase hardware failures</p>
		<p>B. Support business operations</p>
		<p>C. Replace employees completely</p>
		<p>D. Remove data storage</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>2)</b> Operational support helps organisations to:</h4>
		<p>A. Shut down processes</p>
		<p>B. Manage day-to-day activities efficiently</p>
		<p>C. Reduce communication</p>
		<p>D. Remove automation</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>3)</b> Automation in organisations helps to:</h4>
		<p>A. Increase repetitive manual work</p>
		<p>B. Reduce repetitive tasks</p>
		<p>C. Increase downtime</p>
		<p>D. Remove monitoring</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>4)</b> One benefit of automation is:</h4>
		<p>A. Increased labour cost</p>
		<p>B. Improved efficiency</p>
		<p>C. Reduced productivity</p>
		<p>D. Decreased accuracy</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>5)</b> Monitoring systems are used to:</h4>
		<p>A. Increase errors</p>
		<p>B. Detect performance issues</p>
		<p>C. Remove data</p>
		<p>D. Disable servers</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>6)</b> Customer service can be improved using:</h4>
		<p>A. Chatbots and self-service portals</p>
		<p>B. BIOS updates</p>
		<p>C. RAID configuration</p>
		<p>D. Hypervisors</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>7)</b> Data management systems help organisations to:</h4>
		<p>A. Delete important data</p>
		<p>B. Store and organise data efficiently</p>
		<p>C. Increase redundancy</p>
		<p>D. Remove encryption</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>8)</b> Resource management systems assist in managing:</h4>
		<p>A. Only hardware</p>
		<p>B. Staff, finance and supply chain</p>
		<p>C. Only software</p>
		<p>D. Only customers</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>9)</b> Enterprise Resource Planning (ERP) systems integrate:</h4>
		<p>A. Single department only</p>
		<p>B. Multiple business functions</p>
		<p>C. Only finance</p>
		<p>D. Only HR</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>10)</b> Collaboration tools allow employees to:</h4>
		<p>A. Work in isolation</p>
		<p>B. Share information in real time</p>
		<p>C. Delete files</p>
		<p>D. Remove communication</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>11)</b> Project management software helps managers to:</h4>
		<p>A. Ignore deadlines</p>
		<p>B. Plan and track tasks</p>
		<p>C. Remove workflows</p>
		<p>D. Increase errors</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>12)</b> Communication platforms enable:</h4>
		<p>A. Delayed messaging only</p>
		<p>B. Instant messaging and video conferencing</p>
		<p>C. Removal of meetings</p>
		<p>D. Hardware control</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>13)</b> Knowledge management systems support:</h4>
		<p>A. Data deletion</p>
		<p>B. Organising and sharing organisational knowledge</p>
		<p>C. Removing SOPs</p>
		<p>D. Disabling access</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>14)</b> A document management system (DMS) is used to:</h4>
		<p>A. Encrypt CPUs</p>
		<p>B. Store and retrieve documents</p>
		<p>C. Delete records</p>
		<p>D. Remove backups</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>15)</b> Content Management Systems (CMS) are used to:</h4>
		<p>A. Manage website content</p>
		<p>B. Increase hardware</p>
		<p>C. Remove collaboration</p>
		<p>D. Encrypt databases</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>16)</b> Effective IT systems improve decision-making by:</h4>
		<p>A. Removing data</p>
		<p>B. Providing accurate and timely information</p>
		<p>C. Increasing latency</p>
		<p>D. Disabling reports</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>17)</b> Improving communication and collaboration leads to:</h4>
		<p>A. Reduced productivity</p>
		<p>B. Improved organisational performance</p>
		<p>C. Increased isolation</p>
		<p>D. Reduced teamwork</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>18)</b> Monitoring systems can prevent:</h4>
		<p>A. Data storage</p>
		<p>B. Major system failures</p>
		<p>C. User access</p>
		<p>D. Communication</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>19)</b> Automation can reduce:</h4>
		<p>A. Human error</p>
		<p>B. Efficiency</p>
		<p>C. Productivity</p>
		<p>D. Security</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>20)</b> Data analysis supports organisations by:</h4>
		<p>A. Ignoring trends</p>
		<p>B. Identifying patterns and insights</p>
		<p>C. Removing records</p>
		<p>D. Reducing reports</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>21)</b> CRM systems help organisations to:</h4>
		<p>A. Manage customer relationships</p>
		<p>B. Delete customer data</p>
		<p>C. Reduce engagement</p>
		<p>D. Remove marketing</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>22)</b> Synchronous communication includes:</h4>
		<p>A. Email</p>
		<p>B. Video conferencing</p>
		<p>C. Bulletin boards</p>
		<p>D. Blogs</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>23)</b> Asynchronous communication includes:</h4>
		<p>A. Instant messaging</p>
		<p>B. Email</p>
		<p>C. Video calls</p>
		<p>D. Telephone calls</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>24)</b> Digital workflows improve:</h4>
		<p>A. Manual paperwork</p>
		<p>B. Process efficiency</p>
		<p>C. Data redundancy</p>
		<p>D. Errors</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>25)</b> Information systems convert data into:</h4>
		<p>A. Noise</p>
		<p>B. Meaningful information</p>
		<p>C. Hardware</p>
		<p>D. Storage</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>26)</b> Knowledge is created when:</h4>
		<p>A. Data is deleted</p>
		<p>B. Information is analysed and applied</p>
		<p>C. Storage increases</p>
		<p>D. Encryption fails</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>27)</b> An effective IT system should align with:</h4>
		<p>A. Personal hobbies</p>
		<p>B. Organisational goals</p>
		<p>C. BIOS updates</p>
		<p>D. RAID setup</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>28)</b> IT systems can improve customer service by:</h4>
		<p>A. Reducing access</p>
		<p>B. Providing online support</p>
		<p>C. Increasing waiting times</p>
		<p>D. Removing communication</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>29)</b> Collaboration platforms include tools such as:</h4>
		<p>A. Word processors only</p>
		<p>B. Shared document editing</p>
		<p>C. RAID controllers</p>
		<p>D. BIOS configuration</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>30)</b> Remote working is supported by:</h4>
		<p>A. Communication platforms</p>
		<p>B. Hardware removal</p>
		<p>C. Reduced internet</p>
		<p>D. No collaboration</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>31)</b> IT systems reduce costs by:</h4>
		<p>A. Increasing duplication</p>
		<p>B. Improving efficiency</p>
		<p>C. Removing automation</p>
		<p>D. Increasing downtime</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>32)</b> Business intelligence tools help managers to:</h4>
		<p>A. Ignore data</p>
		<p>B. Make informed decisions</p>
		<p>C. Remove dashboards</p>
		<p>D. Reduce reporting</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>33)</b> Dashboards provide:</h4>
		<p>A. Real-time performance information</p>
		<p>B. BIOS updates</p>
		<p>C. RAID configuration</p>
		<p>D. Encryption keys</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>34)</b> Workflow automation improves:</h4>
		<p>A. Speed and consistency</p>
		<p>B. Manual errors</p>
		<p>C. Paper usage</p>
		<p>D. Delays</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>35)</b> An organisation uses IT systems mainly to:</h4>
		<p>A. Increase risk</p>
		<p>B. Improve productivity and performance</p>
		<p>C. Remove employees</p>
		<p>D. Disable systems</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>36)</b> Knowledge repositories store:</h4>
		<p>A. Hardware</p>
		<p>B. Organisational documents and procedures</p>
		<p>C. RAID data</p>
		<p>D. BIOS logs</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>37)</b> Operational goals are achieved by:</h4>
		<p>A. Removing monitoring</p>
		<p>B. Effective IT support</p>
		<p>C. Ignoring automation</p>
		<p>D. Deleting systems</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>38)</b> Cloud-based collaboration tools allow:</h4>
		<p>A. Offline-only access</p>
		<p>B. Real-time shared editing</p>
		<p>C. Reduced teamwork</p>
		<p>D. File deletion</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>39)</b> IT systems improve compliance by:</h4>
		<p>A. Ignoring regulations</p>
		<p>B. Enforcing policies</p>
		<p>C. Deleting records</p>
		<p>D. Removing security</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>40)</b> The main role of IT systems in organisations is to:</h4>
		<p>A. Increase complexity</p>
		<p>B. Support business processes efficiently</p>
		<p>C. Remove data</p>
		<p>D. Reduce collaboration</p>
    </section> 
    <section><p><b>Answer: B</b><br>
				<a href="<?= $dirBase ?>/IAL_topic_14_B.php">Section B</a>
		</p></p>
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

