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
  <h3>SECTION A – Knowledge and Understanding</h3>
</section>

<section>
  <section>
    <h4><b>1) </b>Define the term IT system.</h4>
  </section>
  <section>
    <p>An IT system (Information Technology system) is a combination of hardware, software, data, people, and procedures that work together to collect, process, store, and output information. [1]</p>
  </section>
 
</section>

<section>
  <section>
    <h4><b>2) </b>State two components of an IT system.</h4>
  </section>
  <section>
    <p>Two components of an IT system are hardware and software. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>3) </b>Explain why organizations rely on IT systems.</h4>
  </section>

  <section>
    <p>Organisations rely on IT systems because they help manage information, improve efficiency, and support effective decision-making. [3]</p>
  </section>

  <section>
    <p>IT systems allow organisations to process large volumes of data quickly and accurately, which would be difficult and time-consuming to do manually. This supports day-to-day operations such as sales processing, payroll, stock control, and customer management.</p>
  </section>

  <section>
    <p>They also improve communication and collaboration through email, shared databases, and online platforms, enabling staff to work together efficiently, even across different locations.</p>
  </section>

  <section>
    <p>In addition, IT systems provide managers with timely and reliable information for planning, monitoring performance, and making informed decisions. By reducing errors, saving time, lowering costs, and improving service quality, IT systems are essential for organisations to operate competitively and effectively.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>4) </b>Explain how IT systems improve organizational efficiency.</h4>
  </section>

  <section>
    <p>IT systems improve organisational efficiency by automating tasks, improving information flow, and reducing errors. [4]</p>
  </section>

  <section>
    <p>IT systems automate routine processes such as data entry, calculations, payroll, and transaction processing. Automation reduces the time and effort required to complete tasks and allows employees to focus on more productive and value-added activities.</p>
  </section>

  <section>
    <p>They also enable faster and more accurate communication and data sharing across departments through databases, networks, and cloud-based platforms. This reduces duplication of work and delays in accessing information.</p>
  </section>

  <section>
    <p>In addition, IT systems improve accuracy by minimising human error and providing real-time monitoring and reporting. Fewer errors mean less rework, lower costs, and smoother operations.</p>
  </section>

  <section>
    <p>Overall, IT systems help organisations work faster, more accurately, and more cost-effectively, leading to higher productivity and improved performance.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>5) </b>Explain how IT systems support decision making in organizations.</h4>
  </section>

  <section>
    <p>IT systems support decision making in organisations by providing accurate, timely, and relevant information to managers and decision-makers. [4]</p>
  </section>

  <section>
    <p>IT systems collect data from different areas of the organisation, such as sales, finance, operations, and human resources. This data is processed and presented as reports, summaries, charts, and dashboards that clearly show performance, trends, and key indicators.</p>
  </section>

  <section>
    <p>By analysing this information, managers can identify problems, predict future trends, compare alternatives, and evaluate outcomes before making decisions. For example, sales reports can help decide stock levels, while financial reports can support budgeting and investment decisions.</p>
  </section>

  <section>
    <p>Overall, IT systems improve decision making by increasing accuracy, speed, consistency, and confidence, reducing reliance on guesswork and enabling organisations to make informed and strategic choices.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>6) </b>Explain the difference between data and information.</h4>
  </section>

  <section>
    <p>The difference between data and information is based on meaning and usefulness. [3]</p>
  </section>

  <section>
    <p>Data refers to raw, unprocessed facts and figures, such as numbers, names, or measurements. On its own, data has little meaning and cannot easily be used for decision-making. For example, a list of numbers like 85, 90, 72 is data.</p>
  </section>

  <section>
    <p>Information is data that has been processed, organised, or analysed so that it becomes meaningful and useful. When data is presented in context, such as average exam score = 82%, it becomes information that can be used to make decisions.</p>
  </section>

  <section>
    <p>In summary, data is raw input, while information is processed output that has meaning and value.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>7) </b>Explain the role of people in an IT system.</h4>
  </section>

  <section>
    <p>People play a crucial role in an IT system because they are responsible for operating, managing, and maintaining the system, as well as making decisions based on the information it produces. [3]</p>
  </section>

  <section>
    <p>People include end users who enter data and use information, IT professionals such as system administrators and technicians who maintain hardware and software, and managers who use system outputs to make decisions. Without people, an IT system cannot function effectively, even if the technology is advanced.</p>
  </section>

  <section>
    <p>People also ensure that procedures are followed correctly, data is entered accurately, and security policies are enforced. Their skills, training, and decision-making directly affect the accuracy, efficiency, and reliability of the IT system.</p>
  </section>

  <section>
    <p>In summary, people are essential because they control how the IT system is used, maintained, and improved, ensuring it meets organisational needs.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>8) </b>Explain the role of procedures in an IT system.</h4>
  </section>

  <section>
    <p>Procedures play an important role in an IT system by providing clear instructions on how tasks and processes should be carried out correctly and consistently. [3]</p>
  </section>

  <section>
    <p>Procedures describe step-by-step actions for activities such as data entry, system use, backups, security checks, and error handling. By following procedures, users know exactly what to do and when to do it, which reduces mistakes and ensures the system is used properly.</p>
  </section>

  <section>
    <p>Procedures also help maintain security and reliability. For example, procedures may specify how to create strong passwords, how often backups should be taken, or how to respond to system failures. This protects data and ensures continuity of operations.</p>
  </section>

  <section>
    <p>Overall, procedures ensure consistency, efficiency, security, and correct use of IT systems, making them essential for smooth and reliable organisational operations.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>9) </b>Explain the importance of data accuracy in IT systems.</h4>
  </section>

  <section>
    <p>Data accuracy is important in IT systems because it ensures that information is correct, reliable, and suitable for decision-making. [3]</p>
  </section>

  <section>
    <p>Accurate data allows organisations to make effective decisions based on trustworthy information. If data is inaccurate or outdated, decisions such as stock ordering, financial planning, or customer management may be wrong, leading to financial loss or operational problems.</p>
  </section>

  <section>
    <p>Data accuracy is also essential for efficient operations and customer trust. Accurate records ensure correct billing, reliable reports, and consistent services. Inaccurate data can result in errors such as incorrect payments, wrong deliveries, or poor customer service.</p>
  </section>

  <section>
    <p>In addition, maintaining accurate data helps organisations comply with legal and regulatory requirements, particularly in areas such as finance, healthcare, and data protection. Overall, data accuracy is critical for system reliability, organisational performance, and credibility.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>10) </b>Explain how IT systems improve communication within organizations.</h4>
  </section>

  <section>
    <p>IT systems improve communication within organisations by making information sharing faster, more reliable, and more accessible. [4]</p>
  </section>

  <section>
    <p>IT systems such as email, instant messaging platforms, video conferencing tools, and intranets allow employees to communicate instantly regardless of location. This supports remote working and enables teams in different departments or branches to collaborate effectively in real time.</p>
  </section>

  <section>
    <p>Shared digital platforms (for example, cloud-based documents and internal portals) ensure that employees can access the same up-to-date information, reducing misunderstandings and duplication of work. Messages, files, and updates can be distributed quickly to many people at once, improving coordination and decision-making.</p>
  </section>

  <section>
    <p>Overall, IT systems enhance communication by improving speed, accuracy, collaboration, and accessibility, leading to more efficient organisational operations.</p>
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

