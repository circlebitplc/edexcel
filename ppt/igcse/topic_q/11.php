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

	<meta name='description' content='Chapter 11 – Online Services'>
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
					<h4>Chapter 11</h4>
					<h2>Online Services</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h3>Section A — Multiple Choice Questions (MCQs)</h3>
</section>

<section>
  <section><h4><b>1) </b>A shopping site’s product catalogue usually includes:</h4></section>
  <section><p>Answer: B. Images, descriptions, prices, stock levels</p></section>
</section>

<section>
  <section><h4><b>2) </b>E-tickets are usually delivered through:</h4></section>
  <section><p>Answer: C. Email</p></section>
</section>

<section>
  <section><h4><b>3) </b>Online banking allows users to:</h4></section>
  <section><p>Answer: B. Check balances and make transfers</p></section>
</section>

<section>
  <section><h4><b>4) </b>Online learning services provide:</h4></section>
  <section><p>Answer: C. Online journals and assessment materials</p></section>
</section>

<section>
  <section><h4><b>5) </b>Auction sites use ratings to:</h4></section>
  <section><p>Answer: B. Check buyer/seller reliability</p></section>
</section>

<section>
  <section><h4><b>6) </b>A drawback of online services is:</h4></section>
  <section><p>Answer: C. Reduced face-to-face interaction</p></section>
</section>

<section>
  <section><h4><b>7) </b>Transactional data is:</h4></section>
  <section><p>Answer: B. Data sent between devices during activity</p></section>
</section>

<section>
  <section><h4><b>8) </b>Cookies store:</h4></section>
  <section><p>Answer: B. Items in baskets and browsing data</p></section>
</section>

<section>
  <section><h4><b>9) </b>Session cookies are stored:</h4></section>
  <section><p>Answer: B. Only until webpage is closed</p></section>
</section>

<section>
  <section><h4><b>10) </b>Third-party cookies are used for:</h4></section>
  <section><p>Answer: B. Targeted marketing and ad personalisation</p></section>
</section>

<section>
  <h3>Section B — Short Answer Questions</h3>
</section>

<section>
  <section><h4><b>11) </b>Define an online service.</h4></section>
  <section><p>An online service is a service delivered over the internet.</p></section>
  <section><p>It allows users to perform tasks such as shopping, banking, learning, or booking.</p></section>
</section>

<section>
  <section><h4><b>12) </b>Give two examples of shopping site features.</h4></section>
  <section><p>Product catalogue with images and prices.</p></section>
  <section><p>Online shopping basket.</p></section>
</section>

<section>
  <section><h4><b>13) </b>What is an e-ticket?</h4></section>
  <section><p>An e-ticket is a digital ticket sent electronically.</p></section>
  <section><p>It is usually delivered by email and shown on a mobile device or printed.</p></section>
</section>

<section>
  <section><h4><b>14) </b>State two services offered by online banking.</h4></section>
  <section><p>Checking account balances.</p></section>
  <section><p>Transferring money between accounts.</p></section>
</section>

<section>
  <section><h4><b>15) </b>What is the purpose of ratings on auction sites?</h4></section>
  <section><p>Ratings allow users to judge the reliability of buyers and sellers.</p></section>
</section>

<section>
  <section><h4><b>16) </b>Define transactional data.</h4></section>
  <section><p>Transactional data is data generated during an online activity.</p></section>
  <section><p>Examples include purchases, logins, or bookings.</p></section>
</section>

<section>
  <section><h4><b>17) </b>What is a cookie?</h4></section>
  <section><p>A cookie is a small text file stored on a user’s device by a website.</p></section>
  <section><p>It remembers information about the user.</p></section>
</section>

<section>
  <section><h4><b>18) </b>One advantage and one disadvantage of online services.</h4></section>
  <section><p>Advantage: Services are available anytime and anywhere.</p></section>
  <section><p>Disadvantage: Increased risk to privacy and data security.</p></section>
</section>

<section>
  <section><h4><b>19) </b>What is a persistent cookie used for?</h4></section>
  <section><p>A persistent cookie stores user preferences or login details.</p></section>
  <section><p>It remains on the device for future visits.</p></section>
</section>

<section>
  <section><h4><b>20) </b>Define targeted marketing.</h4></section>
  <section><p>Targeted marketing is advertising tailored to users.</p></section>
  <section><p>It is based on interests, behaviour, or browsing history.</p></section>
</section>

<section>
  <h3>Section C — Structured Questions</h3>
</section>

<section>
  <section><h4><b>21) </b>How online shopping has changed consumer behaviour.</h4></section>
  <section><p>Consumers can shop 24/7 without visiting stores.</p></section>
  <section><p>They can compare prices and read reviews.</p></section>
  <section><p>Products are delivered directly to homes.</p></section>
</section>

<section>
  <section><h4><b>22) </b>Features of online booking systems and benefits.</h4></section>
  <section><p>Real-time availability checking.</p></section>
  <section><p>Seat or date selection.</p></section>
  <section><p>E-ticket delivery provides instant confirmation.</p></section>
</section>

<section>
  <section><h4><b>23) </b>Impact of online services on organizations.</h4></section>
  <section><p>Organizations can reach global markets.</p></section>
  <section><p>Processes can be automated.</p></section>
  <section><p>Security and data protection responsibilities increase.</p></section>
</section>

<section>
  <section><h4><b>24) </b>Types of cookies and their purposes.</h4></section>
  <section><p>Session cookies store data temporarily.</p></section>
  <section><p>Persistent cookies remember preferences.</p></section>
  <section><p>Third-party cookies track users for advertising.</p></section>
</section>

<section>
  <section><h4><b>25) </b>How transactional data is collected and stored.</h4></section>
  <section><p>Collected during activities such as purchases or bookings.</p></section>
  <section><p>Stored in databases for processing and analysis.</p></section>
</section>

<section>
  <h3>Section D — Scenario-Based Questions</h3>
</section>

<section>
  <section><h4><b>26) </b>Saved items in an online basket.</h4></section>
  <section><p>A persistent cookie stores basket information.</p></section>
  <section><p>The website reads the cookie when the user returns.</p></section>
</section>

<section>
  <section><h4><b>27) </b>Flight delay notifications.</h4></section>
  <section><p>This uses an online booking and travel management service.</p></section>
  <section><p>Stored booking data allows real-time updates.</p></section>
</section>

<section>
  <section><h4><b>28) </b>Tracking behaviour for marketing.</h4></section>
  <section><p>Cookies record browsing activity.</p></section>
  <section><p>The data is analysed to show targeted advertisements.</p></section>
</section>

<section>
  <section><h4><b>29) </b>Benefits of online educational services.</h4></section>
  <section><p>Access to up-to-date information.</p></section>
  <section><p>Available anytime and anywhere.</p></section>
</section>

<section>
  <section><h4><b>30) </b>Personalised advertisements.</h4></section>
  <section><p>Third-party cookies track browsing behaviour.</p></section>
  <section><p>Advertisers use this data to personalise ads.</p></section>
</section>

<section>
  <h3>Section E — Extended Long Questions</h3>
</section>

<section>
  <section><h4><b>31) </b>Advantages and disadvantages of online services.</h4></section>
  <section><p>Advantages include convenience and time savings.</p></section>
  <section><p>Disadvantages include privacy risks and reduced interaction.</p></section>
</section>

<section>
  <section><h4><b>32) </b>Impact of online services on organizations.</h4></section>
  <section><p>Organizations reach global markets.</p></section>
  <section><p>Remote working becomes possible.</p></section>
  <section><p>Cybersecurity risks increase.</p></section>
</section>

<section>
  <section><h4><b>33) </b>Cookies, transactional data, and privacy.</h4></section>
  <section><p>Cookies personalise user experience.</p></section>
  <section><p>Transactional data enables secure services.</p></section>
  <section><p>Privacy concerns require strong laws and consent.</p></section>
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

