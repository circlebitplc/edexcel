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

	<meta name='description' content='Unit 4 – online googds and services'>
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
					<h4>Unit 4</h4>
					<h2>online googds and services</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>

	<section>
  <section>
    <h4><b>1) </b>Explain what is meant by online services.</h4>
  </section>
  <section>
    <p>Online services are services provided through the internet by organisations. They allow users to access facilities such as shopping, banking, communication and entertainment remotely without visiting a physical location. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>2) </b>State two examples of online services.</h4>
  </section>
  <section>
    <p>Two examples of online services are online shopping websites and online banking services. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>3) </b>Describe how shopping sites help users find products.</h4>
  </section>
  <section>
    <p>Shopping sites help users find products by using a product catalogue where items are organised into categories and sub-categories. They also provide a search facility that allows users to enter keywords. Filters such as price, brand or features help narrow results and make searching quicker. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>4) </b>Explain why shopping sites use secure payment systems.</h4>
  </section>
  <section>
    <p>Shopping sites use secure payment systems to protect customers’ personal and financial data during transactions. Encryption prevents hackers from intercepting sensitive details such as card numbers. This builds customer trust and reduces the risk of fraud. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>5) </b>Explain what is meant by e-ticketing.</h4>
  </section>
  <section>
    <p>E-ticketing is the electronic issuing of tickets instead of paper tickets. Tickets are sent via email or mobile apps and can be displayed on a smartphone or printed. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>6) </b>State two benefits of e-ticketing.</h4>
  </section>
  <section>
    <p>Two benefits of e-ticketing are that tickets are less likely to be lost and customers do not need to visit a booking office to collect them. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>7) </b>Explain one service provided by online banking.</h4>
  </section>
  <section>
    <p>Online banking allows customers to transfer money electronically between accounts, enabling quick payments without visiting a bank branch. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>8) </b>Explain one benefit of online banking to customers.</h4>
  </section>
  <section>
    <p>Online banking saves time because customers can manage their finances at any time without travelling to a bank. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>9) </b>Describe how online learning services benefit students.</h4>
  </section>
  <section>
    <p>Online learning services allow students to study remotely from any location. Materials are available 24/7 so learners can study at their own pace. Students also save time and money by avoiding travel and accommodation costs. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>10) </b>Explain one drawback of online learning services.</h4>
  </section>
  <section>
    <p>A drawback of online learning is reduced face-to-face interaction, which can limit communication skills and immediate teacher support. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>11) </b>Explain how gaming sites operate online.</h4>
  </section>
  <section>
    <p>Gaming sites host games on remote servers accessed through the internet. Player actions are processed in real time. Multiplayer games allow players to interact and compete with others worldwide. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>12) </b>Explain how news services notify users of updates.</h4>
  </section>
  <section>
    <p>News services send notifications or alerts to devices. Users subscribe to topics and receive real-time updates automatically. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>13) </b>Describe how auction sites work.</h4>
  </section>
  <section>
    <p>Auction sites allow sellers to list items for a fixed period. Buyers place bids online. When the auction ends, the highest bidder wins and completes payment. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>14) </b>Explain one benefit of online auction sites to sellers.</h4>
  </section>
  <section>
    <p>Sellers benefit from access to a global audience, increasing the chance of achieving a higher selling price. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>15) </b>Explain one benefit of online services on lifestyle.</h4>
  </section>
  <section>
    <p>Online services save time by allowing tasks such as shopping and banking to be completed quickly, giving people more time for family and leisure. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>16) </b>Explain one negative impact of online services on lifestyle.</h4>
  </section>
  <section>
    <p>Online services can cause social isolation because people may spend excessive time online instead of interacting face to face. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>17) </b>Describe how online services change the way organisations do business.</h4>
  </section>
  <section>
    <p>Online services allow organisations to operate globally. Communication is faster through online tools. Costs are reduced by using online sales and remote working. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>18) </b>Explain what is meant by transactional data.</h4>
  </section>
  <section>
    <p>Transactional data is data generated when an online transaction occurs, such as purchasing goods or transferring money. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>19) </b>State three examples of data stored in cookies.</h4>
  </section>
  <section>
    <p>Cookies can store login details, shopping basket contents and user preferences such as language settings. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>20) </b>Explain the difference between session cookies and persistent cookies.</h4>
  </section>
  <section>
    <p>Session cookies are temporary and deleted when the browser is closed. Persistent cookies remain stored for a set period. They remember user information for future visits. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>21) </b>Explain one risk of third-party cookies.</h4>
  </section>
  <section>
    <p>Third-party cookies can track users across websites, which may invade privacy by collecting browsing behaviour without full user awareness. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>22) </b>Explain what is meant by cloud computing.</h4>
  </section>
  <section>
    <p>Cloud computing is the use of internet-based servers to store data and run software instead of using local storage or installed applications. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>23) </b>Describe one feature of hosted applications.</h4>
  </section>
  <section>
    <p>Hosted applications run on remote servers and are accessed using a web browser without local installation. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>24) </b>State one advantage of hosted applications.</h4>
  </section>
  <section>
    <p>They can be accessed from any device with an internet connection. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>25) </b>State one disadvantage of hosted applications.</h4>
  </section>
  <section>
    <p>They require an internet connection to operate. [1]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>26) </b>Explain what is meant by online data storage.</h4>
  </section>
  <section>
    <p>Online data storage stores files on remote servers accessed through the internet rather than on local devices. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>27) </b>Describe one benefit of online data storage.</h4>
  </section>
  <section>
    <p>Files can be accessed from multiple devices and locations, increasing flexibility. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>28) </b>Describe one drawback of online data storage.</h4>
  </section>
  <section>
    <p>There is a security risk because data may be hacked if protection is weak. [2]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>29) </b>Explain how cloud services benefit organisations.</h4>
  </section>
  <section>
    <p>Cloud services reduce hardware costs, support remote working and allow organisations to scale resources as needed. [3]</p>
  </section>
</section>

<section>
  <section>
    <h4><b>30) </b>State two examples of cloud-based services.</h4>
  </section>
  <section>
    <p>Examples include online storage services and web-based email services. [2]</p>
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

