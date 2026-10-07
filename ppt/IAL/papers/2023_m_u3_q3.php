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
					<h4>2022 May</h4>
					<h2> Unit 3</h2>
					<?= ppt_teacher_credit_markup() ?>
 </section>   
 <!-- ====================================================== -->
<!-- 3(a)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>3(a)(i)</b> Give one non-general item found in a certificate. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            The public key of the certificate owner.
        </p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            Digital certificates contain information
            used to verify identity
            and support secure communication.
        </p>

        <p>
            The public key allows encrypted communication
            between systems
            while ensuring data security.
        </p>
    </section>

    <section>
        <p>
            Other possible certificate contents include:
        </p>

        <ul>
            <li>Certificate serial number</li>
            <li>Issuer identity</li>
            <li>Digital signature</li>
            <li>Expiry date</li>
        </ul>
    </section>
</section>

<!-- ====================================================== -->
<!-- 3(a)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>3(a)(ii)</b> Describe the role of a Certificate Authority. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            A Certificate Authority (CA)
            verifies identities
            and issues digital certificates.
        </p>
    </section>

    <section>
        <p><b>Detailed Explanation:</b></p>

        <p>
            The CA checks whether an organisation
            or website is genuine
            before issuing a certificate.
        </p>
    </section>

    <section>
        <p>
            The certificate:
        </p>

        <ul>
            <li>Confirms authenticity</li>
            <li>Enables secure encrypted communication</li>
            <li>Helps users trust websites and services</li>
        </ul>
    </section>

    <section>
        <p>
            The CA also:
        </p>

        <ul>
            <li>Keeps records of certificates</li>
            <li>Manages revoked certificates</li>
            <li>Verifies certificate validity</li>
        </ul>
    </section>

    <section>
        <p>
            Without Certificate Authorities,
            users could not reliably trust
            encrypted websites.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 3(b) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>3(b)</b>Symmetric and asymmetric are two types of encryption.
Alice sends a message to Bob using symmetric encryption.
Bob sends a message to Alice using asymmetric encryption.
Complete the diagram to show the steps required for the messages to be
exchanged.
You must include plaintext, ciphertext and types of keys.(9 marks)</h4>
    </section>

    
    <section>
         <img src="<?= $dirBase ?>/2023_m_u3_q3.png">
    </section>
   
</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2023_m_u3_q4.php">next</a></p>
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

