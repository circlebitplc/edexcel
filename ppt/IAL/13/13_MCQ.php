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

	<meta name='description' content='Topic 13 – Enabling Technologies'>
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
					<h4>Topic 13</h4>
					<h2> Enabling Technologies</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>
 <section style="text-align: left;">

<section style="text-align: left;">1) Virtualisation is the creation of:<br>
A. A physical server<br>
B. A virtual version of a system or resource<br>
C. A backup file<br>
D. A firewall<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">2) A hypervisor is used to:<br>
A. Encrypt files<br>
B. Manage virtual machines<br>
C. Increase bandwidth<br>
D. Delete storage<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">3) Desktop virtualisation allows users to:<br>
A. Install more RAM<br>
B. Access a desktop remotely<br>
C. Remove operating systems<br>
D. Disable servers<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">4) Storage virtualisation combines:<br>
A. Multiple physical storage devices into one logical unit<br>
B. Multiple CPUs<br>
C. Multiple keyboards<br>
D. Multiple firewalls<br></section>
<section style="text-align: left;">ANSWER: A</section>

<section style="text-align: left;">5) Network virtualisation creates:<br>
A. Physical cables<br>
B. Virtual networks independent of hardware<br>
C. Hard disk drives<br>
D. BIOS updates<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">6) A key benefit of virtualisation is:<br>
A. Reduced flexibility<br>
B. Improved resource utilisation<br>
C. Increased hardware waste<br>
D. Lower scalability<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">7) Isolation in virtualisation ensures that:<br>
A. Errors spread across systems<br>
B. Errors in one VM do not affect others<br>
C. All systems share memory<br>
D. All VMs stop together<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">8) Scalability means a system can:<br>
A. Reduce performance<br>
B. Handle increased workload<br>
C. Delete data<br>
D. Disable users<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">9) A snapshot is used to:<br>
A. Delete a system<br>
B. Save system state at a point in time<br>
C. Increase RAM<br>
D. Encrypt storage<br></section>
<section style="text-align: left;">ANSWER: B</section>

<section style="text-align: left;">10) A virtual machine typically includes:<br>
A. A full operating system<br>
B. Only applications<br>
C. Only storage<br>
D. Only BIOS<br></section>
<section style="text-align: left;">ANSWER: A</section>

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

