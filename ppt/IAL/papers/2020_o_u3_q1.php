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

    <!-- 1(a)(i) -->
    <section>
        <section>
            <h4><b>1(a)(i)</b> Give two ways in which using virtual servers can save money for the company. (2 marks)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Virtual servers reduce hardware costs because multiple virtual servers can run on one physical server.</p>

            <p>This means the company does not need to purchase many separate computers.</p>

            <p>The company also saves money on electricity, cooling systems, maintenance, and physical space because fewer physical machines are required.</p>
        </section>
    </section>

    <!-- 1(a)(ii) -->
    <section>
        <section>
            <h4><b>1(a)(ii)</b> Give two other reasons why the company hosts the websites on virtual servers. (2 marks)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Virtual servers provide flexibility because websites can easily be moved to different hardware or upgraded without major downtime.</p>

            <p>They improve security and reliability because problems such as malware or crashes on one virtual server usually do not affect the others.</p>
        </section>
    </section>

    <!-- 1(b) -->
    <section>
        <section>
            <h4><b>1(b)</b> Give three ways in which a container differs from a virtual machine. (3 marks)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>A virtual machine has its own operating system, while containers share the host operating system.</p>
 </section> 
   <section>
            <p>Containers use fewer resources such as RAM and storage compared to virtual machines.</p>
 </section> 
   <section>
            <p>Containers start up much faster because they do not need to load a complete operating system.</p>
        </section>
    </section>

    <!-- 1(c) -->
    <section>
        <section>
            <h4><b>1(c)</b> Explain how secure and/or flexible storage is achieved in this context. (4 marks)</h4>
        </section>
        <section>
            <p><b>Answer:</b></p>

            <p>Cloud storage is flexible because website owners can quickly increase or decrease storage capacity and bandwidth depending on the needs of their websites.</p>
 </section> 
   <section>
            <p>This means resources can easily be adjusted during periods of high or low traffic.</p>
 </section> 
   <section>
            <p>The cloud also allows data to be accessed from anywhere with an internet connection, making it easier for owners to manage their websites remotely.</p>
 </section> 
   <section>
            <p>Security is improved because cloud providers normally use encryption to protect data during storage and transmission.</p>
 </section> 
   <section>
            <p>This prevents unauthorised users from reading sensitive information.</p>
 </section> 
   <section>
            <p>Cloud providers also use backup systems, anti-malware software, and secure data centres with physical protection, reducing the risk of data loss or cyber attacks.</p>
        </section>
    </section> 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2020_o_u3_q2.php">next</a></p>
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

