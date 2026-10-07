<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));

require_once $docRoot . $pptBase . '/_teacher_credit.php';
?>
<!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='Topic 13 MCQ A – Enabling technologies'>
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
					<h4>Topic 13 MCQ A</h4>
					<h2>Enabling technologies</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <section> 
	<section>
		<h4><b>1)</b> Virtualisation is the creation of:</h4>
				<p>A. A physical server</p>
				<p>B. A virtual version of a system or resource</p>
				<p>C. A backup file</p>
				<p>D. A firewall</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>2)</b> A hypervisor is used to:</h4>
				<p>A. Encrypt files</p>
				<p>B. Manage virtual machines</p>
				<p>C. Increase bandwidth</p>
				<p>D. Delete storage</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>3)</b> Desktop virtualisation allows users to:</h4>
				<p>A. Install more RAM</p>
				<p>B. Access a desktop remotely</p>
				<p>C. Remove operating systems</p>
				<p>D. Disable servers</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>4)</b> Storage virtualisation combines:</h4>
				<p>A. Multiple physical storage devices into one logical unit</p>
				<p>B. Multiple CPUs</p>
				<p>C. Multiple keyboards</p>
				<p>D. Multiple firewalls</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>5)</b> Network virtualisation creates:</h4>
				<p>A. Physical cables</p>
				<p>B. Virtual networks independent of hardware</p>
				<p>C. Hard disk drives</p>
				<p>D. BIOS updates</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>6)</b> A key benefit of virtualisation is:</h4>
				<p>A. Reduced flexibility</p>
				<p>B. Improved resource utilisation</p>
				<p>C. Increased hardware waste</p>
				<p>D. Lower scalability</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>7)</b> Isolation in virtualisation ensures that:</h4>
				<p>A. Errors spread across systems</p>
				<p>B. Errors in one VM do not affect others</p>
				<p>C. All systems share memory</p>
				<p>D. All VMs stop together</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>8)</b> Scalability means a system can:</h4>
				<p>A. Reduce performance</p>
				<p>B. Handle increased workload</p>
				<p>C. Delete data</p>
				<p>D. Disable users</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>9)</b> A snapshot is used to:</h4>
				<p>A. Delete a system</p>
				<p>B. Save system state at a point in time</p>
				<p>C. Increase RAM</p>
				<p>D. Encrypt storage</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>10)</b> A virtual machine typically includes:</h4>
				<p>A. A full operating system</p>
				<p>B. Only applications</p>
				<p>C. Only storage</p>
				<p>D. Only BIOS</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>11)</b> Containers differ from VMs because they:</h4>
				<p>A. Include a full OS</p>
				<p>B. Share the host operating system</p>
				<p>C. Require more hardware</p>
				<p>D. Use more memory</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>12)</b> Containers are generally:</h4>
				<p>A. More lightweight than VMs</p>
				<p>B. Slower than VMs</p>
				<p>C. Larger than VMs</p>
				<p>D. Less portable</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>13)</b> A distributed system consists of:</h4>
				<p>A. One standalone computer</p>
				<p>B. Multiple independent nodes working together</p>
				<p>C. One database only</p>
				<p>D. One network cable</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>14)</b> Fault tolerance ensures:</h4>
				<p>A. System shuts down during failure</p>
				<p>B. System continues operating during failure</p>
				<p>C. No replication occurs</p>
				<p>D. Data is deleted</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>15)</b> Replication improves:</h4>
				<p>A. Availability</p>
				<p>B. Latency</p>
				<p>C. Security removal</p>
				<p>D. Storage reduction</p>
	</section> 
	<section><p><b>Answer: A</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>16)</b> Concurrency allows:</h4>
				<p>A. One task at a time</p>
				<p>B. Multiple tasks simultaneously</p>
				<p>C. Reduced processing</p>
				<p>D. Single-thread operation</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>17)</b> Latency refers to:</h4>
				<p>A. Data encryption</p>
				<p>B. Delay in data transmission</p>
				<p>C. File compression</p>
				<p>D. RAM capacity</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>18)</b> Load balancing is used to:</h4>
				<p>A. Delete data</p>
				<p>B. Distribute workload evenly</p>
				<p>C. Encrypt servers</p>
				<p>D. Reduce RAM</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>19)</b> Human Computer Interaction (HCI) focuses on:</h4>
				<p>A. Hardware repair</p>
				<p>B. User interaction with systems</p>
				<p>C. Storage devices</p>
				<p>D. Network cables</p>
	</section> 
	<section><p><b>Answer: B</b></p></section> 
</section>

<section> 
	<section>
		<h4><b>20)</b> User Experience (UX) refers to:</h4>
				<p>A. Processor speed</p>
				<p>B. Overall user satisfaction</p>
				<p>C. Bandwidth</p>
				<p>D. RAM</p>
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

