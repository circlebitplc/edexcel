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

	<meta name='description' content='Topic 14 – using it systems in organization'>
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
					<h4>Topic 14</h4>
					<h2>using it systems in organization </h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

	<section> 
	<section>
		<h4><b>1)</b> Define an IT system.</h4>
				<p>An IT system (Information Technology system) is a combination of hardware, software, data, people, and procedures that work together to collect, process, store, and output information.</p>
	</section> 
	<section><p><b>Answer:</b> Combination of hardware, software, data, people and procedures working together.</p></section> 
</section>

<section> 
	<section>
		<h4><b>2)</b> State two reasons why organisations rely on IT systems.</h4></section>
	<section>
				<p>1. Improve Efficiency<br>
IT systems automate tasks (e.g., payroll, stock control), reducing manual work and saving time.
</p></section>
	<section>
<p>2. Increase Accuracy<br>
Computers reduce human error in calculations, data entry, and record keeping.
</p>
	</section>	
	<section>
<p>3. Faster Communication<br>
Email, messaging systems, and video conferencing allow instant communication internally and externally.
</p>
	</section> 
	<section>
<p>4. Better Data Storage & Retrieval<br>
Large amounts of data can be stored electronically and retrieved quickly when needed.
</p>
	</section> 
<section>
<p>5. Support Decision Making<br>
Management Information Systems (MIS) provide reports, charts, and real-time data to help managers make informed decisions.
</p>
	</section>
	<section>
<p>6. Cost Reduction<br>
Automation reduces labour costs, paper use, and administrative expenses.
</p>
	</section>
	<section>
<p>7.  Improve Customer Service<br>
Customer databases allow faster service, personalised communication, and quicker complaint handling.
</p>
	</section> 	
	<section>
<p>8.  Competitive Advantage<br>
Organisations can use e-commerce, online marketing, and data analysis to stay ahead of competitors.
</p>
	</section> 
	<section>
<p>9.  Global Operations<br>
IT systems allow organisations to operate internationally through online platforms and cloud systems.

</p>
	</section>
<section>
<p>10.  Security & Monitoring<br>
Access controls, encryption, and surveillance systems protect sensitive organisational data.
</p>
	</section> 
</section>
<section> 
	<section>
		<h4><b>3)</b> Explain how IT systems improve productivity.</h4>
			 
	</section> 
	<section><p>Automation of Repetitive Tasks<br>
IT systems automate routine activities such as payroll processing, stock updates, invoicing, and data entry.
This reduces the time employees spend on repetitive work, allowing them to focus on more important tasks
.</p></section>  
<section><p>Faster Data Processing
<br>Computers can process large amounts of data quickly compared to manual methods.
For example, financial calculations, report generation, and sales analysis can be completed in seconds rather than hours.

.</p></section>	
			
<section><p>Improved Communication<br>
Email, messaging platforms, and video conferencing allow instant communication between employees, departments, and customers.
This reduces delays and speeds up decision-making.
</p></section> 			
<section><p>Better Access to Information<br>
Databases and cloud systems allow employees to quickly retrieve accurate information when needed.
</p></section> 
<section><p>Reduced Errors<br>
Automated systems reduce human mistakes in calculations and data handling.
Fewer errors mean less time spent correcting problems, which increases overall efficiency.
</p></section> 
<section><p>Remote Working Capabilities<br>
Cloud computing and online collaboration tools allow staff to work from different locations.<br>
Work continues without being limited by physical office space
</p></section> 
<section><p>Integration of Systems<br>
Modern IT systems link departments together (e.g., sales, inventory, accounting).
When one system updates, others update automatically, reducing duplication of work.
</p></section> 			
</section>

<section> 
	<section>
		<h4><b>4)</b> Explain how IT systems support decision-making.</h4>
				 
	</section> 
<section><p>Providing Accurate Information<br>
IT systems collect and store data in databases.
Managers can access up-to-date and accurate information, reducing decisions based on guesswork.<br>
Example: A sales system shows current daily sales figures, helping managers decide whether targets are being met.</p></section>
	
<section><p>Generating Reports and Summaries<br>
Management Information Systems (MIS) automatically create reports, charts, and graphs.These summaries make complex data easier to understand and compare.<br>
Example: A monthly profit and loss report helps management decide whether to reduce expenses.</p></section> 

	<section><p>Providing Accurate Information<br>
IT systems collect and store data in databases.
Managers can access up-to-date and accurate information, reducing decisions based on guesswork.</p></section> 
	
	<section><p>Identifying Trends and Patterns<br>
IT systems use data analysis tools (e.g., spreadsheets, data analytics software) to identify trends.
This helps managers predict future outcomes.
<br>Example: Analysing past customer purchases helps forecast future demand.</p></section> 
	
	<section><p>Real-Time Monitoring<br>
Modern IT systems provide instant updates on operations such as stock levels or performance indicators.
Managers can react quickly to problems.<br>
Example: An automatic alert when stock is low allows quick reordering.</p></section> 
	
	<section><p>Supporting “What-If” Analysis<br>
Some systems allow simulation and modelling to test different scenarios.
This reduces risk before making important decisions.<br>
Example: Testing how increasing prices might affect profit before implementing the change.</p></section> 
</section>

<section><p>Improving Communication of Decisions<br>
IT systems allow decisions to be shared quickly through email, dashboards, and internal networks.This ensures all departments act consistently.</p></section> 
</section>			
			
<section> 
	<section>
		<h4><b>5)</b> Describe how IT systems improve communication and collaboration.</h4>
				<p>Email, messaging, video conferencing and shared documents enable fast communication and teamwork.</p>
	</section> 
	<section><p><b>Answer:</b> Faster communication and real-time collaboration tools.</p></section> 
</section>

<section> 
	<section>
		<h4><b>6)</b> Explain how IT systems help reduce costs and increase profit.</h4>
				<p>Automation reduces labour costs, stock control reduces waste, and data analysis improves business decisions.</p>
	</section> 
	<section><p><b>Answer:</b> Automation lowers costs and improves decision-making to increase profit.</p></section> 
</section>

<section> 
	<section>
		<h4><b>7)</b> Describe how IT systems improve customer service.</h4>
				<p>They provide faster responses, self-service options and accurate order processing.</p>
	</section> 
	<section><p><b>Answer:</b> Faster service and improved customer satisfaction.</p></section> 
</section>

<section> 
	<section>
		<h4><b>8)</b> Explain the importance of security and compliance in IT systems.</h4>
				<p>Security protects sensitive data while compliance ensures legal and regulatory requirements are met.</p>
	</section> 
	<section><p><b>Answer:</b> Protects data and ensures legal requirements are followed.</p></section> 
</section>

<section> 
	<section>
		<h4><b>9)</b> Define automation.</h4>
				<p>Automation is the use of IT systems to perform tasks automatically with minimal human intervention.</p>
	</section> 
	<section><p><b>Answer:</b> Performing tasks automatically using IT systems.</p></section> 
</section>

<section> 
	<section>
		<h4><b>10)</b> Give two examples of automated tasks.</h4>
				<p>1. Payroll processing</p>
				<p>2. Automatic stock reordering</p>
	</section> 
	<section><p><b>Answer:</b> Payroll processing and automatic stock reordering.</p></section> 
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

