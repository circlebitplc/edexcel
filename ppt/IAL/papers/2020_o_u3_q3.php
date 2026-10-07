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
    <!-- 3 -->
    <section>
        <section>
            <h4><b>3</b> Evaluate the impact of weak IoT security measures on individuals, organisations and data in the context of the new coffee machines. (12 marks)</h4>
        </section>

        <section>
            <p><b>Answer:</b></p>

            <p>Weak security in IoT coffee machines could create serious problems for individuals, organisations, and the data being transferred between devices.</p>
 </section> 
			<section>
            <p>For individuals, one major issue is privacy and home security.</p>
 </section> 
			<section>
            <p>If hackers gain access to the coffee machine, they could monitor usage patterns and determine when the owner is not at home.</p>
 </section> 
			<section>
            <p>This could increase the risk of burglary.</p>
 </section> 
			<section>
            <p>Hackers may also remotely control the machine and force it to operate continuously, wasting electricity, coffee, and supplies.</p>
 </section> 
			<section>
            <p>Since the machine is connected to the home WiFi network, attackers might use it as a way to access other devices such as laptops, smartphones, or smart TVs.</p>
 </section> 
			<section>
            <p>There are also financial risks because the machine can automatically order coffee supplies online.</p>
 </section> 
			<section>
            <p>Attackers may intercept payment information, redirect orders, or make unauthorised purchases, causing financial loss to the customer.</p>
 </section> 
			<section>
            <p>For organisations such as the coffee machine manufacturer and payment providers, weak security could damage reputation and reduce customer trust.</p>
 </section> 
			<section>
            <p>Customers may stop buying the products if they believe the devices are unsafe.</p>
 </section> 
			<section>
            <p>Payment companies and suppliers may also experience financial losses if fraudulent transactions occur.</p>
 </section> 
			<section>
            <p>In some cases, companies may need to compensate customers or spend large amounts of money improving their cybersecurity systems.</p>
 </section> 
			<section>
            <p>Weak security can also affect the data itself.</p>
 </section> 
			<section>
            <p>If attackers intercept the transmitted data, they may alter machine settings such as water temperature or coffee quantity.</p>
 </section> 
			<section>
            <p>Hackers could also suppress warning messages, send fake alerts, or prevent important firmware updates.</p>
 </section> 
			<section>
            <p>Another serious risk is that infected coffee machines could become part of a botnet used for cyber attacks such as DDoS attacks, spam distribution, or cryptocurrency mining.</p>
 </section> 
			<section>
            <p>To reduce these risks, the company should use strong security measures.</p>
 </section> 
			<section>
            <p>WiFi connections should use strong encryption such as WPA2 or WPA3.</p>
 </section> 
			<section>
            <p>All communications between the coffee machine, app, and servers should be encrypted.</p>
 </section> 
			<section>
            <p>Each machine should be given a unique password, and users should be forced to change the password during setup.</p>
 </section> 
			<section>
            <p>Two-factor authentication should also be used for payments and online ordering.</p>
 </section> 
			<section>
            <p>The company should regularly release firmware updates to fix vulnerabilities and employ security experts to test the devices before release.</p>
 </section> 
			<section>
            <p>In conclusion, weak IoT security could lead to privacy breaches, financial losses, data corruption, and damage to company reputation.</p>
 </section> 
			<section>
            <p>Therefore, strong encryption, authentication systems, regular updates, and secure payment methods are essential for protecting both users and organisations.</p>
        </section>
    </section> 
			<section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2020_o_u3_q4.php">next</a></p>
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

