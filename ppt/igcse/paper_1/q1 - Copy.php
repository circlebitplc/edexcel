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

	<meta name='description' content='Paper 1 – Q'>
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
					<h4>Paper 1</h4>
					<h2>Q</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
<section>
  <h2>QUESTION 1 – Tablet Computer</h2>
</section>

<section>
  <section><h4><b>1(a)</b> Which one of these is a type of wireless connectivity? (1)</h4></section>
  <section><p>A. Bluetooth</p></section>
</section>

<section>
  <section><h4><b>1(b)</b> Give two benefits of using wired connectivity with a tablet computer. (2)</h4></section>
  <section><p>Wired connections provide a stable and reliable connection because they are not affected by interference.</p></section>
  <section><p>They usually offer faster data transfer speeds than wireless connections.</p></section>
</section>

<section>
  <section><h4><b>1(c)</b> Give two benefits of using wireless connectivity with a tablet computer. (2)</h4></section>
  <section><p>Wireless connectivity allows mobility and flexibility.</p></section>
  <section><p>No physical cables are required, improving portability.</p></section>
</section>

<section>
  <section><h4><b>1(d)</b> Give two sensors used in a tablet computer. (2)</h4></section>
  <section><p>Accelerometer – detects movement and orientation.</p></section>
  <section><p>Light sensor – adjusts screen brightness automatically.</p></section>
</section>

<section>
  <section><h4><b>1(e)(i)</b> Diagram showing smartphone providing internet to tablet. (3)</h4></section>
  <section><p>Smartphone connects to mobile network (4G/5G).</p></section>
  <section><p>Smartphone shares internet via Wi-Fi hotspot.</p></section>
  <section><p>Tablet connects using Wi-Fi.</p></section>
</section>

<section>
  <section><h4><b>1(e)(ii)</b> Explain one negative effect on the smartphone’s connectivity. (2)</h4></section>
  <section><p>Sharing the connection reduces available bandwidth.</p></section>
  <section><p>This causes slower internet speeds on the smartphone.</p></section>
</section>

<section>
  <section><h4><b>1(e)(iii)</b> Name the permanent unique address given by the manufacturer. (1)</h4></section>
  <section><p>MAC address</p></section>
</section>

<section>
  <section><h4><b>1(f)</b> Describe what is meant by a clock speed of 2.4 GHz. (2)</h4></section>
  <section><p>The processor performs 2.4 billion cycles per second.</p></section>
  <section><p>This affects how quickly instructions are processed.</p></section>
</section>

<section>
  <section><h4><b>1(g)(i)</b> Purpose of utility software. (1)</h4></section>
  <section><p>Utility software maintains, manages, and protects systems.</p></section>
</section>

<section>
  <section><h4><b>1(g)(ii)</b> Software that prevents access to source code. (1)</h4></section>
  <section><p>D. Proprietary</p></section>
</section>

<section>
  <section><h4><b>1(h)</b> Why magnetic storage is not used in tablets. (2)</h4></section>
  <section><p>Magnetic storage is large and fragile.</p></section>
  <section><p>It uses more power and is slower than solid-state storage.</p></section>
</section>

<section>
  <h2>QUESTION 2 – Types of Computer and Software</h2>
</section>

<section>
  <section><h4><b>2(a)</b> Best choice for complex tasks. (1)</h4></section>
  <section><p>C. Mainframe</p></section>
</section>

<section>
  <section><h4><b>2(b)</b> Two examples of an embedded system. (2)</h4></section>
  <section><p>Washing machine controller.</p></section>
  <section><p>Microwave oven controller.</p></section>
</section>

<section>
  <section><h4><b>2(c)</b> Why some computers do not need application software. (2)</h4></section>
  <section><p>They perform a single dedicated task.</p></section>
  <section><p>The software is built into the system.</p></section>
</section>

<section>
  <section><h4><b>2(d)</b> Not a type of personal computer. (1)</h4></section>
  <section><p>B. Media player</p></section>
</section>

<section>
  <section><h4><b>2(e)</b> Two benefits of using a plotter. (2)</h4></section>
  <section><p>Produces large-scale accurate drawings.</p></section>
  <section><p>Provides high precision and fine detail.</p></section>
</section>

<section>
  <section><h4><b>2(f)</b> Why routers contain an IP address table. (2)</h4></section>
  <section><p>To identify destination devices.</p></section>
  <section><p>To forward data along the correct path.</p></section>
</section>

<section>
  <section><h4><b>2(g)</b> Expression for bits in 256 GiB. (3)</h4></section>
  <section><p>256 × 1024 × 1024 × 1024 × 8</p></section>
</section>

<section>
  <section><h4><b>2(h)</b> Two types of application software. (2)</h4></section>
  <section><p>Word processing software.</p></section>
  <section><p>Spreadsheet software.</p></section>
</section>

<section>
  <section><h4><b>2(i)</b> Match device types. (3)</h4></section>
  <section><p>Input → Webcam</p></section>
  <section><p>Storage → Hard disk</p></section>
  <section><p>Output → Data projector</p></section>
</section>

<section>
  <section><h4><b>2(j)</b> Define convergence. (2)</h4></section>
  <section><p>Convergence combines multiple technologies into one device.</p></section>
</section>

<section>
  <section><h4><b>2(k)</b> Order storage capacities. (3)</h4></section>
  <section><p>kibibyte → mebibyte → gibibyte → tebibyte</p></section>
</section>

<section>
  <section><h4><b>2(l)</b> Why operating systems use print spooling. (2)</h4></section>
  <section><p>Documents are queued for printing.</p></section>
  <section><p>Users can continue working while printing.</p></section>
</section>

<section>
  <section><h4><b>2(m)</b> Two benefits of proprietary software. (4)</h4></section>
  <section><p>Professionally developed and tested, making it reliable.</p></section>
  <section><p>Includes technical support and regular updates.</p></section>
</section>

<section>
  <h2>QUESTION 3 – Internet, Data, and Entertainment</h2>
</section>

<section>
  <section><h4><b>3(a)</b> Two risks to data. (2)</h4></section>
  <section><p>Hacking.</p></section>
  <section><p>Accidental data deletion.</p></section>
</section>

<section>
  <section><h4><b>3(b)</b> How authentication protects data. (2)</h4></section>
  <section><p>Passwords restrict system access.</p></section>
  <section><p>Only authorised users can access data.</p></section>
</section>

<section>
  <section><h4><b>3(c)(i)</b> Files storing browsing habits. (1)</h4></section>
  <section><p>Cookies</p></section>
</section>

<section>
  <section><h4><b>3(c)(ii)</b> One benefit of transactional data. (2)</h4></section>
  <section><p>Provides personalised recommendations.</p></section>
  <section><p>Improves online experience.</p></section>
</section>

<section>
  <section><h4><b>3(d)</b> Two positive impacts of operating online. (4)</h4></section>
  <section><p>Reaches a global audience.</p></section>
  <section><p>Increases revenue.</p></section>
  <section><p>Reduces distribution costs.</p></section>
  <section><p>Lowers operating expenses.</p></section>
</section>

<section>
  <section><h4><b>3(e)</b> Two effective search engine techniques. (4)</h4></section>
  <section><p>Using specific keywords.</p></section>
  <section><p>Using quotation marks for exact phrases.</p></section>
</section>

<section>
  <h2>QUESTION 4 – ICT in Transport and Employment</h2>
</section>

<section>
  <section><h4><b>4(a)</b> How a sensor improves vehicle safety. (2)</h4></section>
  <section><p>Proximity sensors detect obstacles.</p></section>
  <section><p>They warn drivers to prevent collisions.</p></section>
</section>

<section>
  <section><h4><b>4(b)</b> Wireless communication used. (1)</h4></section>
  <section><p>GPS</p></section>
</section>

<section>
  <section><h4><b>4(c)</b> Two impacts of the Internet on taxi business. (4)</h4></section>
  <section><p>Online booking improves efficiency.</p></section>
  <section><p>GPS tracking improves route planning.</p></section>
</section>

<section>
  <section><h4><b>4(d)</b> Why data protection laws give control to employees. (2)</h4></section>
  <section><p>Ensure data is used fairly.</p></section>
  <section><p>Prevent misuse by employers.</p></section>
</section>

<section>
  <section><h4><b>4(e)</b> One impact of Internet on employment. (2)</h4></section>
  <section><p>Supports remote working.</p></section>
</section>

<section>
  <section><h4><b>4(f)</b> Ethical impacts of monitoring communication. (8)</h4></section>
  <section><p>Improves security and productivity.</p></section>
  <section><p>Prevents data breaches.</p></section>
  <section><p>Raises privacy concerns.</p></section>
  <section><p>May reduce employee trust.</p></section>
  <section><p>Risk of data misuse.</p></section>
  <section><p>Requires transparency.</p></section>
  <section><p>Clear policies are needed.</p></section>
  <section><p>Balance between security and privacy.</p></section>
</section>

<section>
  <h2>QUESTION 5 – Online Communities and the Environment</h2>
</section>

<section>
  <section><h4><b>5(a)</b> Two features of video sharing sites. (2)</h4></section>
  <section><p>Video uploading.</p></section>
  <section><p>Commenting and rating.</p></section>
</section>

<section>
  <section><h4><b>5(b)</b> Two risks of online communities. (2)</h4></section>
  <section><p>Cyberbullying.</p></section>
  <section><p>Identity theft.</p></section>
</section>

<section>
  <section><h4><b>5(c)</b> How acceptable use policies keep communities safe. (2)</h4></section>
  <section><p>Set rules on behaviour.</p></section>
  <section><p>Prevent misuse.</p></section>
</section>

<section>
  <section><h4><b>5(d)</b> Action after reading inappropriate post. (2)</h4></section>
  <section><p>Report the post to moderators.</p></section>
</section>

<section>
  <section><h4><b>5(e)</b> One way to evaluate fitness for purpose. (2)</h4></section>
  <section><p>Check relevance to topic and audience.</p></section>
</section>

<section>
  <section><h4><b>5(f)</b> Positive impacts of ICT on the environment. (8)</h4></section>
  <section><p>Reduces resource wastage using sensors.</p></section>
  <section><p>Supports remote working.</p></section>
  <section><p>Reduces travel emissions.</p></section>
  <section><p>Centralised cloud computing.</p></section>
  <section><p>Lower energy consumption.</p></section>
  <section><p>Reduces electronic waste.</p></section>
  <section><p>Digital documents reduce paper use.</p></section>
  <section><p>Lowers deforestation and pollution.</p></section>
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

