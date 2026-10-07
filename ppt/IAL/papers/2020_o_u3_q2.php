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

	<meta name='description' content='papers'>
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
					<h4>2020 Oct</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>  
 
    <!-- 2(a) -->
    <section>
        <section>
            <h4><b>2(a)</b> Describe two other ways in which the company could use the MIS. (4 marks)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Use 1:</b></p>

            <p>The MIS can be used for inventory management by monitoring stock levels in shops and warehouses.</p>

            <p>This helps the company reorder products before they run out and improves stock control.</p>
 </section> 
			<section>
            <p><b>Use 2:</b></p>

            <p>The MIS can also analyse customer purchasing trends and seasonal buying habits.</p>

            <p>This information helps the company create targeted marketing campaigns and improve sales strategies.</p>
        </section>
    </section>

    <!-- 2(b) -->
    <section>
        <section>
            <h4><b>2(b)</b> Describe two ways in which an ITS can be used to manage the fleet. (4 marks)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p><b>Way 1:</b></p>

            <p>The ITS can use GPS tracking systems to monitor the location of delivery vehicles in real time.</p>

            <p>This helps managers choose faster routes, avoid traffic congestion, and improve delivery efficiency.</p>
 </section> 
			<section>
            <p><b>Way 2:</b></p>

            <p>The ITS can monitor fuel consumption, engine performance, and driving behaviour.</p>

            <p>This allows the company to schedule maintenance earlier and encourage safer driving practices.</p>
        </section>
    </section>

    <!-- 2(c) -->
    <section>
        <section>
            <h4><b>2(c)</b> Discuss how the use of a TP system benefits the company. (6 marks)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>A transaction processing system provides many benefits for the grocery company.</p>
 </section> 
			<section>
            <p>One important advantage is faster processing of customer purchases. Barcode scanners and EPOS systems allow items to be scanned quickly, reducing waiting times at checkouts and improving customer satisfaction.</p>
 </section> 
			<section>
            <p>The system also improves accuracy because prices are automatically retrieved from the database.</p>
 </section> 
			<section>
            <p>This reduces mistakes caused by manual data entry and ensures customers are charged correctly.</p>
 </section> 
			<section>
            <p>Another major benefit is automatic stock control.</p>
 </section> 
			<section>
            <p>When products are sold, the stock database is updated immediately. This allows the company to identify low stock levels and reorder products quickly, reducing shortages and waste.</p>
 </section> 
			<section>
            <p>The TP system can generate reports for management showing sales figures, best-selling products, and shop performance.</p>
 </section> 
			<section>
            <p>Managers can use this information to make better business decisions.</p>
 </section> 
			<section>
            <p>Customer data collected through loyalty cards can also be analysed to identify buying habits and preferences.</p>
 </section> 
			<section>
            <p>This helps the company run targeted marketing campaigns and increase sales.</p>
 </section> 
			<section>
            <p>In addition, the system supports electronic payment methods such as debit and credit cards, making transactions easier and more convenient for customers.</p>
        </section>
    </section> 
			<section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2020_o_u3_q3.php">next</a></p>
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

