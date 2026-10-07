<?php
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/');
$pptBase = substr(str_replace('\\', '/', realpath(dirname(__DIR__, 2))), strlen($docRoot));
$dirBase = substr(str_replace('\\', '/', realpath(__DIR__)), strlen($docRoot));


require_once $docRoot . $pptBase . '/_teacher_credit.php';
?><!doctype html>
<html lang='en'>
<head>
  	<meta charset='utf-8'>
 <title class='hightlight-blue'>Enidu Batuwanthudawe</title> 

	<meta name='description' content='IGCSE Paper 1 – Question 1: Tablet Computer'>
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
					<h2>QUESTION 1</h2>
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
	<section><h4><a href='<?= $dirBase ?>/q2.php'>Q2</a></h4> </section>
   </section>

			</div>
		</div>

			</div>
		</div>

		<script src="<?= $pptBase ?>/lib/js/head.min.js"></script>
		<script src="<?= $pptBase ?>/js/reveal.min.js"></script>

		<script>
 
			Reveal.initialize({
				controls: true,
				progress: true,
				history: true,
				center: true,

				theme: Reveal.getQueryHash().theme,  
				transition: Reveal.getQueryHash().transition || 'default',  
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

