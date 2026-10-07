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
  <h3>SECTION C – Transaction Processing Systems (TPS) & ACID Properties</h3>
</section>

<section>
  <section>
    <h4><b>24) </b>Define the term Transaction Processing System (TPS).</h4>
  </section>
  <section>
    <p>A Transaction Processing System (TPS) is a computer-based system that records, processes, and stores routine day-to-day business transactions accurately and efficiently, such as sales, payments, and bookings. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>25) </b>State two examples of transactions processed by a TPS.</h4>
  </section>
  <section>
    <p>Recording a sales transaction at a retail checkout, such as processing payment and updating stock levels.</p>
  </section>
  <section>
    <p>Processing a bank transaction, such as a deposit, withdrawal, or funds transfer. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>26) </b>Explain why TPS are important in organizations.</h4>
  </section>

  <section>
    <p>Transaction Processing Systems (TPS) are important in organisations because they handle routine, day-to-day transactions accurately, efficiently, and reliably. [4]</p>
  </section>

  <section>
    <p>TPS record and process large volumes of transactions such as sales, payments, bookings, and inventory updates. By automating these processes, organisations reduce human error, increase speed, and ensure that transactions are processed consistently.</p>
  </section>

  <section>
    <p>TPS also ensure that organisational data is accurate and up to date, which is essential for operational activities and customer service. For example, correct stock levels, account balances, and order records help prevent errors such as overselling or incorrect billing.</p>
  </section>

  <section>
    <p>In addition, TPS provide reliable data that can be used by other systems such as MIS and ERP for reporting, monitoring performance, and decision-making. This makes TPS a critical foundation for efficient business operations.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>27) </b>Explain the difference between batch processing and real-time processing.</h4>
  </section>

  <section>
    <p>The difference between batch processing and real-time processing lies in when and how data is processed. [4]</p>
  </section>

  <section>
    <p>Batch processing involves collecting transactions over a period of time and processing them together at set intervals. The results are not immediate, so data may be temporarily outdated. Batch processing is often used for tasks such as payroll processing or billing.</p>
  </section>

  <section>
    <p>Real-time processing processes each transaction immediately as it occurs. Records are updated instantly, ensuring that data is always current. This is essential for systems such as online banking, ticket booking, and EPOS systems.</p>
  </section>

  <section>
    <p>In summary, batch processing is suitable for non-urgent tasks, while real-time processing is used when immediate updates and responses are necessary.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>28) </b>Explain two advantages of real-time transaction processing.</h4>
  </section>

  <section>
    <p><b>Immediate Data Accuracy</b></p>
  </section>

  <section>
    <p>Real-time transaction processing updates records instantly as transactions occur. This ensures that data such as account balances, stock levels, and bookings are always up to date, helping organisations avoid errors such as overselling or invalid transactions.</p>
  </section>

  <section>
    <p><b>Faster Decision-Making and Improved Customer Service</b></p>
  </section>

  <section>
    <p>Because data is processed immediately, organisations can respond quickly to events and customer requests. This leads to quicker service, reduced waiting times, and improved customer satisfaction. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>29) </b>Explain two disadvantages of real-time transaction processing.</h4>
  </section>

  <section>
    <p><b>High System and Infrastructure Costs</b></p>
  </section>

  <section>
    <p>Real-time transaction processing requires powerful hardware, fast networks, and highly reliable systems. Backup systems and failover mechanisms are also needed, making implementation and maintenance expensive.</p>
  </section>

  <section>
    <p><b>System Complexity and Risk of Failure</b></p>
  </section>

  <section>
    <p>Real-time systems are complex to design and manage. Any system fault or network issue can disrupt operations immediately, affecting many transactions at once and increasing the risk of service interruptions. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>30) </b>Define the term ACID in relation to database transactions.</h4>
  </section>

  <section>
    <p>ACID refers to a set of four properties that ensure reliable and accurate database transactions:</p>
  </section>

  <section>
    <p>Atomicity – a transaction is completed fully or not at all.</p>
  </section>

  <section>
    <p>Consistency – a transaction maintains database rules and integrity.</p>
  </section>

  <section>
    <p>Isolation – transactions do not interfere with each other.</p>
  </section>

  <section>
    <p>Durability – completed transactions are permanently stored. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>31) </b>Explain the purpose of Atomicity in ACID.</h4>
  </section>

  <section>
    <p>The purpose of Atomicity in ACID is to ensure that a transaction is treated as a single, indivisible unit. [3]</p>
  </section>

  <section>
    <p>Atomicity guarantees that either all parts of a transaction are completed successfully or none of them are applied at all. If an error or system failure occurs, the system rolls back any changes made.</p>
  </section>

  <section>
    <p>This prevents inconsistencies such as money being deducted from one account without being credited to another.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>32) </b>Explain the purpose of Consistency in ACID.</h4>
  </section>

  <section>
    <p>The purpose of Consistency in ACID is to ensure that a database remains in a valid and correct state before and after each transaction. [3]</p>
  </section>

  <section>
    <p>Consistency guarantees that all rules, constraints, and integrity checks are followed when a transaction is processed.</p>
  </section>

  <section>
    <p>This prevents invalid or corrupted data from being stored and ensures reliable information.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>33) </b>Explain the purpose of Isolation in ACID.</h4>
  </section>

  <section>
    <p>The purpose of Isolation in ACID is to ensure that multiple transactions occurring at the same time do not interfere with each other. [3]</p>
  </section>

  <section>
    <p>Each transaction appears to run independently, preventing issues such as reading incomplete data or incorrect balances.</p>
  </section>

  <section>
    <p>This maintains accuracy and consistency in systems such as banking and online payments.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>34) </b>Explain the purpose of Durability in ACID.</h4>
  </section>

  <section>
    <p>The purpose of Durability in ACID is to ensure that once a transaction is successfully completed, its results are permanently saved. [3]</p>
  </section>

  <section>
    <p>Durability guarantees that committed data will not be lost even if there is a system crash or power failure.</p>
  </section>

  <section>
    <p>This ensures data reliability in critical transaction-based systems.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>35) </b>Explain how ACID properties improve reliability in TPS.</h4>
  </section>

  <section>
    <p>ACID properties improve reliability in a TPS by ensuring transactions are processed correctly and safely. [6]</p>
  </section>

  <section>
    <p>Atomicity prevents partial updates, Consistency ensures valid data states, Isolation avoids transaction conflicts, and Durability ensures completed transactions are permanently saved.</p>
  </section>

  <section>
    <p>Together, these properties make TPS reliable, accurate, and trustworthy.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>36) </b>Explain why ACID compliance is essential for financial transaction systems.</h4>
  </section>

  <section>
    <p>ACID compliance is essential because it ensures financial transactions are processed accurately and securely. [6]</p>
  </section>

  <section>
    <p>Atomicity prevents incomplete money transfers, Consistency enforces financial rules, Isolation prevents transaction conflicts, and Durability ensures records are not lost.</p>
  </section>

  <section>
    <p>Together, ACID compliance prevents financial loss and maintains trust in systems such as banking and online payments.</p>
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

