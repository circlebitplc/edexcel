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

	<meta name='description' content='Topic 14 MCQ – Secction E'>
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
					<h2> Secction E</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

<section> 
    <section>
        <h4><b>1)</b> IT governance ensures that IT systems:</h4>
		<p>A. Operate without control</p>
		<p>B. Align with organisational objectives</p>
		<p>C. Replace management</p>
		<p>D. Ignore compliance</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>2)</b> Strategic alignment in IT governance ensures:</h4>
		<p>A. IT operates independently of business goals</p>
		<p>B. IT supports overall business strategy</p>
		<p>C. Systems ignore risk</p>
		<p>D. Hardware is prioritised over software</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>3)</b> Risk management involves:</h4>
		<p>A. Ignoring threats</p>
		<p>B. Identifying and mitigating potential risks</p>
		<p>C. Removing backups</p>
		<p>D. Disabling monitoring</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>4)</b> Resource management in IT governance ensures:</h4>
		<p>A. Waste of resources</p>
		<p>B. Efficient allocation of resources</p>
		<p>C. Deletion of systems</p>
		<p>D. Removal of compliance</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>5)</b> Performance measurement in IT governance is used to:</h4>
		<p>A. Monitor system effectiveness</p>
		<p>B. Remove KPIs</p>
		<p>C. Delete dashboards</p>
		<p>D. Disable reports</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>6)</b> Compliance ensures organisations follow:</h4>
		<p>A. Internal and external regulations</p>
		<p>B. Personal preferences</p>
		<p>C. BIOS settings</p>
		<p>D. RAID configuration</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>7)</b> Business continuity planning ensures that:</h4>
		<p>A. Systems shut down permanently</p>
		<p>B. Critical services continue during disruptions</p>
		<p>C. Data is deleted</p>
		<p>D. Risk increases</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>8)</b> Disaster recovery focuses on:</h4>
		<p>A. Preventing all risks</p>
		<p>B. Restoring systems after major failure</p>
		<p>C. Removing backups</p>
		<p>D. Deleting policies</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>9)</b> A disaster recovery plan should include:</h4>
		<p>A. Backup strategies</p>
		<p>B. Clear responsibilities</p>
		<p>C. Recovery procedures</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>10)</b> A backup stored off-site protects against:</h4>
		<p>A. Hardware only</p>
		<p>B. Local disasters such as fire</p>
		<p>C. User errors</p>
		<p>D. Password misuse</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>11)</b> Risk assessment identifies:</h4>
		<p>A. Potential threats and their impact</p>
		<p>B. Only hardware issues</p>
		<p>C. Only software updates</p>
		<p>D. BIOS changes</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>12)</b> Probability and impact are used to calculate:</h4>
		<p>A. Risk level</p>
		<p>B. Storage capacity</p>
		<p>C. Encryption strength</p>
		<p>D. CPU speed</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>13)</b> A user policy defines:</h4>
		<p>A. Acceptable use of IT resources</p>
		<p>B. RAID configuration</p>
		<p>C. Firmware updates</p>
		<p>D. Hardware installation</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>14)</b> An Acceptable Use Policy (AUP) outlines:</h4>
		<p>A. User responsibilities</p>
		<p>B. Server capacity</p>
		<p>C. Hypervisor rules</p>
		<p>D. BIOS settings</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>15)</b> Security breaches may result from:</h4>
		<p>A. Strong passwords</p>
		<p>B. Weak user policies</p>
		<p>C. Proper encryption</p>
		<p>D. Effective monitoring</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>16)</b> Changeover refers to:</h4>
		<p>A. System deletion</p>
		<p>B. Transition from old system to new system</p>
		<p>C. Hardware disposal</p>
		<p>D. Data removal</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>17)</b> Direct changeover involves:</h4>
		<p>A. Running old and new systems together</p>
		<p>B. Immediate switch to new system</p>
		<p>C. Testing in small areas first</p>
		<p>D. Removing system entirely</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>18)</b> Parallel changeover involves:</h4>
		<p>A. Immediate replacement</p>
		<p>B. Running old and new systems simultaneously</p>
		<p>C. Removing backups</p>
		<p>D. Disabling monitoring</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>19)</b> Pilot changeover involves:</h4>
		<p>A. Switching entire organisation at once</p>
		<p>B. Testing new system in a small area</p>
		<p>C. Deleting old system immediately</p>
		<p>D. Removing testing</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>20)</b> Phased changeover introduces the new system:</h4>
		<p>A. All at once</p>
		<p>B. In stages</p>
		<p>C. Without testing</p>
		<p>D. With no training</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>21)</b> A benefit of parallel changeover is:</h4>
		<p>A. Lower cost</p>
		<p>B. Reduced risk</p>
		<p>C. Immediate full implementation</p>
		<p>D. Faster transition</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>22)</b> A disadvantage of parallel changeover is:</h4>
		<p>A. High cost and resource usage</p>
		<p>B. No testing</p>
		<p>C. Immediate shutdown</p>
		<p>D. Reduced security</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>23)</b> Direct changeover carries higher risk because:</h4>
		<p>A. No fallback system exists</p>
		<p>B. Both systems run together</p>
		<p>C. It is slower</p>
		<p>D. It increases storage</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>24)</b> Pilot changeover reduces risk by:</h4>
		<p>A. Testing system on a limited group</p>
		<p>B. Removing training</p>
		<p>C. Deleting backups</p>
		<p>D. Ignoring feedback</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>25)</b> Phased changeover is suitable when:</h4>
		<p>A. Systems are simple</p>
		<p>B. Systems are complex and large</p>
		<p>C. No risk exists</p>
		<p>D. Immediate replacement is required</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>26)</b> System maintenance ensures that:</h4>
		<p>A. Systems degrade over time</p>
		<p>B. Systems continue to operate effectively</p>
		<p>C. Data is deleted</p>
		<p>D. Security is removed</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>27)</b> Corrective maintenance is performed to:</h4>
		<p>A. Fix faults</p>
		<p>B. Improve features</p>
		<p>C. Adapt to new regulations</p>
		<p>D. Remove hardware</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>28)</b> Perfective maintenance improves:</h4>
		<p>A. System performance and functionality</p>
		<p>B. Risk level</p>
		<p>C. Data redundancy</p>
		<p>D. System downtime</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>29)</b> Adaptive maintenance occurs when:</h4>
		<p>A. System environment changes</p>
		<p>B. Hardware fails</p>
		<p>C. Data is deleted</p>
		<p>D. RAID fails</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>30)</b> Preventive maintenance aims to:</h4>
		<p>A. Avoid future problems</p>
		<p>B. Remove updates</p>
		<p>C. Increase risk</p>
		<p>D. Delete systems</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>31)</b> Software updates are often part of:</h4>
		<p>A. Maintenance</p>
		<p>B. BIOS only</p>
		<p>C. RAID</p>
		<p>D. Firmware removal</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>32)</b> IT audits are conducted to:</h4>
		<p>A. Increase risk</p>
		<p>B. Evaluate compliance and security</p>
		<p>C. Delete policies</p>
		<p>D. Remove controls</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>33)</b> Encryption supports governance by:</h4>
		<p>A. Protecting sensitive data</p>
		<p>B. Increasing exposure</p>
		<p>C. Removing security</p>
		<p>D. Deleting files</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>34)</b> A contingency plan is used when:</h4>
		<p>A. Everything works normally</p>
		<p>B. Unexpected disruptions occur</p>
		<p>C. No risk exists</p>
		<p>D. Data is deleted</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>35)</b> Incident response procedures help organisations to:</h4>
		<p>A. Ignore breaches</p>
		<p>B. Respond quickly to security incidents</p>
		<p>C. Remove monitoring</p>
		<p>D. Delete policies</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>36)</b> Access controls reduce risk by:</h4>
		<p>A. Limiting user permissions</p>
		<p>B. Increasing public access</p>
		<p>C. Removing passwords</p>
		<p>D. Disabling monitoring</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>37)</b> Training staff reduces risk by:</h4>
		<p>A. Increasing user errors</p>
		<p>B. Improving awareness of policies</p>
		<p>C. Deleting data</p>
		<p>D. Removing compliance</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>38)</b> Strong governance improves:</h4>
		<p>A. Organisational performance</p>
		<p>B. Risk exposure</p>
		<p>C. Downtime</p>
		<p>D. Data loss</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>39)</b> Regular testing of disaster recovery plans ensures:</h4>
		<p>A. Plans are effective</p>
		<p>B. Data is deleted</p>
		<p>C. Backups are removed</p>
		<p>D. Risk increases</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>40)</b> The overall purpose of IT governance and risk management is to:</h4>
		<p>A. Increase complexity</p>
		<p>B. Protect organisational assets and ensure continuity</p>
		<p>C. Remove policies</p>
		<p>D. Reduce compliance</p>
    </section> 
    <section><p><b>Answer: B</b> </p></section> 
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

