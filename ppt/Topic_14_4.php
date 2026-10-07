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
  <h3>SECTION D – Enterprise Systems (ERP, EPOS, CRM, MIS)</h3>
</section>

<section>
  <section>
    <h4><b>37) </b>Define the term Enterprise Resource Planning (ERP) system.</h4>
  </section>
  <section>
    <p>An Enterprise Resource Planning (ERP) system is an integrated computer-based system that manages and coordinates an organisation’s core business processes, such as finance, human resources, sales, and inventory, using a single shared database. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>38) </b>Explain two benefits of using ERP systems in organizations.</h4>
  </section>

  <section>
    <p><b>Improved Integration and Data Consistency</b></p>
  </section>

  <section>
    <p>ERP systems integrate different business functions such as finance, human resources, sales, and inventory into a single system. This ensures that all departments use the same, up-to-date data, reducing duplication and errors. As a result, information is more accurate and consistent across the organisation.</p>
  </section>

  <section>
    <p><b>Increased Efficiency and Better Decision-Making</b></p>
  </section>

  <section>
    <p>By automating routine processes and providing real-time access to information, ERP systems improve operational efficiency and reduce manual work. Managers can generate comprehensive reports and analyse data more easily, which supports faster and more informed decision-making. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>39) </b>Explain two limitations of using Enterprise Resource Planning (ERP) systems.</h4>
  </section>

  <section>
    <p><b>High Cost and Complex Implementation</b></p>
  </section>

  <section>
    <p>ERP systems are expensive to purchase, customise, and implement. Organisations often need to invest in specialised software, hardware, consultancy services, and staff training. Implementation can take a long time and may disrupt normal business operations.</p>
  </section>

  <section>
    <p><b>Lack of Flexibility</b></p>
  </section>

  <section>
    <p>ERP systems are usually designed around standard business processes. This can limit an organisation’s ability to customise the system to meet specific or changing needs. Adapting the system may require costly customisation, and organisations may need to change their processes to fit the system. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>40) </b>Define the term Electronic Point of Sale (EPOS) system.</h4>
  </section>
  <section>
    <p>An Electronic Point of Sale (EPOS) system is a computer-based system used in retail to process sales transactions by recording items sold, calculating totals, handling payments, and automatically updating stock and sales records. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>41) </b>Explain how EPOS systems benefit retail organizations.</h4>
  </section>

  <section>
    <p>EPOS (Electronic Point of Sale) systems benefit retail organisations by improving efficiency, accuracy, and management of sales operations. [4]</p>
  </section>

  <section>
    <p>EPOS systems speed up the checkout process by automatically scanning items, calculating totals, and processing payments. This reduces queues, improves customer experience, and minimises human errors in pricing and billing.</p>
  </section>

  <section>
    <p>They also automatically record sales data and update stock levels in real time. This helps retailers monitor inventory, identify fast- and slow-moving products, and reorder stock efficiently.</p>
  </section>

  <section>
    <p>In addition, EPOS systems generate detailed sales reports that support better decision-making, pricing strategies, and financial control.</p>
  </section>

  <section>
    <p>Overall, EPOS systems help retail organisations operate more efficiently, reduce costs, and improve customer service.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>42) </b>Explain two disadvantages of using EPOS systems.</h4>
  </section>

  <section>
    <p><b>High Initial and Maintenance Costs</b></p>
  </section>

  <section>
    <p>EPOS systems require investment in hardware such as terminals, barcode scanners, receipt printers, and software licences. Ongoing costs are also involved for maintenance, updates, and staff training.</p>
  </section>

  <section>
    <p><b>Dependence on Technology</b></p>
  </section>

  <section>
    <p>EPOS systems rely on electricity, networks, and computer systems. If there is a system failure or power cut, sales transactions may be disrupted, leading to delays, loss of sales, and customer dissatisfaction. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>43) </b>Define the term Customer Relationship Management (CRM) system.</h4>
  </section>
  <section>
    <p>A Customer Relationship Management (CRM) system is a computer-based system used by organisations to collect, store, manage, and analyse customer information in order to improve customer relationships, sales, marketing, and customer service. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>44) </b>Explain how CRM systems improve customer service.</h4>
  </section>

  <section>
    <p>CRM systems improve customer service by providing organisations with a centralised and up-to-date view of customer information. [4]</p>
  </section>

  <section>
    <p>A CRM system stores customer contact details, purchase history, preferences, and previous interactions. This allows staff to respond quickly and accurately without customers repeating information.</p>
  </section>

  <section>
    <p>CRM systems track customer issues and service requests, ensuring they are followed up and resolved efficiently. Automated reminders reduce response times.</p>
  </section>

  <section>
    <p>As a result, customers receive more personalised, consistent, and reliable service, leading to higher satisfaction and loyalty.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>45) </b>Explain two benefits of using CRM systems in organizations.</h4>
  </section>

  <section>
    <p><b>Improved Customer Relationships and Satisfaction</b></p>
  </section>

  <section>
    <p>CRM systems store customer information such as contact details, purchase history, preferences, and interactions. This allows organisations to understand customer needs better and provide personalised services.</p>
  </section>

  <section>
    <p><b>Increased Sales and Marketing Effectiveness</b></p>
  </section>

  <section>
    <p>CRM systems help analyse customer data to target marketing campaigns, track leads, and identify sales opportunities, improving conversion rates and use of marketing resources. [4]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>46) </b>Define the term Management Information System (MIS).</h4>
  </section>
  <section>
    <p>A Management Information System (MIS) is a computer-based system that collects, processes, stores, and presents data as useful information to support management planning, monitoring, control, and decision-making within an organisation. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>47) </b>Explain how MIS supports management decision making.</h4>
  </section>

  <section>
    <p>A Management Information System (MIS) supports management decision-making by providing accurate, timely, and relevant information. [4]</p>
  </section>

  <section>
    <p>MIS collects data from different areas of the organisation and processes it into structured reports, summaries, and charts.</p>
  </section>

  <section>
    <p>These reports help managers monitor performance, identify trends, and compare results with targets.</p>
  </section>

  <section>
    <p>By presenting information clearly, MIS improves planning, control, and the quality of managerial decisions.</p>
  </section>
</section>

<section>
  <section>
    <h4><b>48) </b>Explain two limitations of MIS.</h4>
  </section>

  <section>
    <p><b>Dependence on Data Quality</b></p>
  </section>

  <section>
    <p>If input data is inaccurate or incomplete, the information produced by the MIS will be unreliable, leading to poor management decisions.</p>
  </section>

  <section>
    <p><b>Limited Flexibility</b></p>
  </section>

  <section>
    <p>MIS produces routine, structured reports and may not support complex or non-routine decisions, requiring additional systems for strategic analysis. [4]</p>
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

