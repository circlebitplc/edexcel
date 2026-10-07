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

	<meta name='description' content='Topic 14 MCQ – Secction B'>
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
					<h2> Secction B</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

<section> 
    <section>
        <h4><b>1)</b> Transaction Processing Systems (TPS) are used to:</h4>
		<p>A. Manage long-term strategy</p>
		<p>B. Process day-to-day transactions</p>
		<p>C. Design products</p>
		<p>D. Manage hardware</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>2)</b> A transaction is best described as:</h4>
		<p>A. A system update</p>
		<p>B. An exchange of goods or services</p>
		<p>C. A hardware installation</p>
		<p>D. A network configuration</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>3)</b> The main goal of TPS is to maintain:</h4>
		<p>A. Latency</p>
		<p>B. Data integrity</p>
		<p>C. Screen brightness</p>
		<p>D. Hardware performance</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>4)</b> Atomicity in ACID means that:</h4>
		<p>A. Transactions can be partially completed</p>
		<p>B. Transactions are all-or-nothing</p>
		<p>C. Data is encrypted</p>
		<p>D. Storage is reduced</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>5)</b> Consistency in ACID ensures that:</h4>
		<p>A. Data remains valid before and after a transaction</p>
		<p>B. Transactions are deleted</p>
		<p>C. Data is stored in RAM only</p>
		<p>D. Replication is removed</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>6)</b> Isolation in ACID ensures that:</h4>
		<p>A. Transactions interfere with each other</p>
		<p>B. Simultaneous transactions do not affect each other</p>
		<p>C. Data is duplicated</p>
		<p>D. Storage is reduced</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>7)</b> Durability in ACID means that:</h4>
		<p>A. Transactions are temporary</p>
		<p>B. Completed transactions are permanently stored</p>
		<p>C. Data is deleted after processing</p>
		<p>D. RAM is cleared</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>8)</b> An Electronic Point of Sale (EPOS) system is used to:</h4>
		<p>A. Encrypt customer data</p>
		<p>B. Process retail transactions</p>
		<p>C. Configure BIOS</p>
		<p>D. Manage hypervisors</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>9)</b> An EPOS system links sales to:</h4>
		<p>A. Payroll only</p>
		<p>B. Inventory management</p>
		<p>C. RAID</p>
		<p>D. Firmware</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>10)</b> When a sale is made, EPOS systems automatically:</h4>
		<p>A. Increase stock levels</p>
		<p>B. Reduce stock levels</p>
		<p>C. Delete data</p>
		<p>D. Remove transactions</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>11)</b> EPOS systems improve customer experience through:</h4>
		<p>A. Self-service checkouts</p>
		<p>B. Increased waiting times</p>
		<p>C. Reduced payment options</p>
		<p>D. Removing loyalty cards</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>12)</b> Sales processing in EPOS includes:</h4>
		<p>A. Printing receipts</p>
		<p>B. Encrypting BIOS</p>
		<p>C. Configuring RAID</p>
		<p>D. Installing firmware</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>13)</b> Inventory management systems help to:</h4>
		<p>A. Ignore stock levels</p>
		<p>B. Track stock automatically</p>
		<p>C. Delete sales records</p>
		<p>D. Remove pricing</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>14)</b> Financial systems must follow ACID principles to ensure:</h4>
		<p>A. Security and accuracy</p>
		<p>B. Reduced encryption</p>
		<p>C. Increased errors</p>
		<p>D. Data deletion</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>15)</b> High availability in financial systems ensures that systems:</h4>
		<p>A. Shut down frequently</p>
		<p>B. Are accessible at all times</p>
		<p>C. Remove data</p>
		<p>D. Disable transactions</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>16)</b> Scalability in financial systems allows:</h4>
		<p>A. Handling increased transaction volumes</p>
		<p>B. Removing users</p>
		<p>C. Deleting data</p>
		<p>D. Reducing performance</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>17)</b> Fraud prevention measures protect against:</h4>
		<p>A. Authorised transactions</p>
		<p>B. Unauthorised access</p>
		<p>C. Valid payments</p>
		<p>D. Stock increases</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>18)</b> Compliance with regulations ensures organisations:</h4>
		<p>A. Ignore legal requirements</p>
		<p>B. Follow financial and data laws</p>
		<p>C. Remove policies</p>
		<p>D. Disable encryption</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>19)</b> Bacs Payment Schemes Limited operates primarily in the:</h4>
		<p>A. USA</p>
		<p>B. UK</p>
		<p>C. Japan</p>
		<p>D. Australia</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>20)</b> Bacs processes:</h4>
		<p>A. Direct debits and direct credits</p>
		<p>B. RAID configurations</p>
		<p>C. Firmware updates</p>
		<p>D. Network protocols</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>21)</b> Direct debit allows organisations to:</h4>
		<p>A. Take agreed payments automatically</p>
		<p>B. Delete customer accounts</p>
		<p>C. Increase stock</p>
		<p>D. Remove encryption</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>22)</b> Direct credit is commonly used for:</h4>
		<p>A. Salary payments</p>
		<p>B. BIOS updates</p>
		<p>C. RAID management</p>
		<p>D. Data deletion</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>23)</b> Automated Clearing House (ACH) is similar to Bacs in the:</h4>
		<p>A. UK</p>
		<p>B. USA</p>
		<p>C. France</p>
		<p>D. Germany</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>24)</b> Single Euro Payments Area (SEPA) operates in:</h4>
		<p>A. USA</p>
		<p>B. Europe</p>
		<p>C. Asia</p>
		<p>D. Africa</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>25)</b> Order processing ensures that:</h4>
		<p>A. Transactions are ignored</p>
		<p>B. Orders are tracked from purchase to delivery</p>
		<p>C. Stock is deleted</p>
		<p>D. Encryption is disabled</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>26)</b> Order details must be:</h4>
		<p>A. Temporary</p>
		<p>B. Permanently stored after confirmation</p>
		<p>C. Deleted immediately</p>
		<p>D. Stored in RAM only</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>27)</b> Consistency in order processing ensures:</h4>
		<p>A. Stock levels are updated correctly</p>
		<p>B. Orders are duplicated</p>
		<p>C. Transactions are partial</p>
		<p>D. Systems shut down</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>28)</b> Financial transactions require:</h4>
		<p>A. Weak authentication</p>
		<p>B. Secure authentication</p>
		<p>C. No verification</p>
		<p>D. Public access</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>29)</b> Security measures in financial systems include:</h4>
		<p>A. Encryption</p>
		<p>B. Password protection</p>
		<p>C. Multi-factor authentication</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>30)</b> Transaction logs are used to:</h4>
		<p>A. Track completed transactions</p>
		<p>B. Delete payments</p>
		<p>C. Reduce stock</p>
		<p>D. Remove encryption</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>31)</b> Audit trails help organisations to:</h4>
		<p>A. Monitor financial activity</p>
		<p>B. Increase downtime</p>
		<p>C. Delete records</p>
		<p>D. Remove security</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>32)</b> Payment systems must ensure:</h4>
		<p>A. Data loss</p>
		<p>B. Confidentiality and integrity</p>
		<p>C. Reduced compliance</p>
		<p>D. Increased risk</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>33)</b> A financial system failure could result in:</h4>
		<p>A. Accurate reporting</p>
		<p>B. Financial loss</p>
		<p>C. Increased security</p>
		<p>D. Reduced downtime</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>34)</b> Compliance reduces the risk of:</h4>
		<p>A. Legal action</p>
		<p>B. Improved profits</p>
		<p>C. Data accuracy</p>
		<p>D. Customer satisfaction</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>35)</b> Secure transaction systems must prevent:</h4>
		<p>A. Fraud</p>
		<p>B. Valid payments</p>
		<p>C. Reporting</p>
		<p>D. Inventory updates</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>36)</b> A transaction processing system is most critical in:</h4>
		<p>A. Retail</p>
		<p>B. Education only</p>
		<p>C. Hardware manufacturing only</p>
		<p>D. BIOS configuration</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>37)</b> Order confirmation ensures:</h4>
		<p>A. Transaction deletion</p>
		<p>B. Customer assurance</p>
		<p>C. Reduced storage</p>
		<p>D. Increased errors</p>
    </section> 
    <section><p><b>Answer: B</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>38)</b> Stock control systems help prevent:</h4>
		<p>A. Overordering</p>
		<p>B. Stock shortages</p>
		<p>C. Stock overflows</p>
		<p>D. All of the above</p>
    </section> 
    <section><p><b>Answer: D</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>39)</b> Financial compliance ensures transparency in:</h4>
		<p>A. Transactions</p>
		<p>B. Hardware</p>
		<p>C. BIOS</p>
		<p>D. RAID</p>
    </section> 
    <section><p><b>Answer: A</b></p></section> 
</section>


<section> 
    <section>
        <h4><b>40)</b> The purpose of ACID is to ensure:</h4>
		<p>A. Data integrity in transactions</p>
		<p>B. Faster screen updates</p>
		<p>C. Hardware performance</p>
		<p>D. Network speed</p>
    </section> 
    <section><p><b>Answer: A</b> <br><a href="<?= $dirBase ?>/IAL_topic_14_C.php">Section C</a></p></section> 
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

