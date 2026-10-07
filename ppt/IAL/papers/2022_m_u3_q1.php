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
<!-- 1(a)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(a)(i)</b> State one example of data used to identify a product in an EPOS system. (1 mark)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            An ISBN (International Standard Book Number).
        </p>
    </section>

    <section>
        <p>
            An ISBN is a unique code assigned to books.
            When scanned into an EPOS system,
            it identifies the exact product being sold.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(a)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(a)(ii)</b> State one other piece of information needed for an online purchase. (1 mark)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Customer delivery address.
        </p>
    </section>

    <section>
        <p>
            The delivery address is required so the retailer knows
            where the purchased item should be sent.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(b)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(b)(i)</b> Explain one benefit to the retailer of collecting customer purchase data. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Collecting customer purchase data allows the retailer
            to analyse sales trends.
        </p>
    </section>

    <section>
        <p>
            By identifying popular products,
            the retailer can manage stock levels more effectively
            and ensure popular items remain available.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(b)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(b)(ii)</b> Explain two risks to customers when retailers store customer data. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer 1:</b></p>

        <p>
            Customer personal or financial information may be stolen.
        </p>
    </section>

    <section>
        <p>
            Hackers could access stored payment details
            and use them for fraud or identity theft.
        </p>
    </section>

    <section>
        <p><b>Answer 2:</b></p>

        <p>
            Customers may receive spam or phishing emails.
        </p>
    </section>

    <section>
        <p>
            Criminals may use stored email addresses
            to send fake messages designed to steal passwords
            or banking details.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(c)(i) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(c)(i)</b> State two methods that help maintain data integrity. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Using access controls.
        </p>

        <p>
            Keeping backups of data.
        </p>
    </section>

    <section>
        <p>
            Access controls ensure only authorised users
            can change data.
        </p>

        <p>
            Backups allow lost or damaged data
            to be restored accurately.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(c)(ii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(c)(ii)</b> Describe one feature of archived data. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Archived data is usually stored offline
            or in long-term storage.
        </p>
    </section>

    <section>
        <p>
            Archived data is not regularly used,
            so it is moved to cheaper or slower storage systems
            but can still be recovered later if required.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(c)(iii) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(c)(iii)</b> State one item that should be included in an acceptable use policy. (1 mark)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Rules about password sharing.
        </p>
    </section>

    <section>
        <p>
            Employees should not share passwords with others
            because this helps maintain system security.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(c)(iv) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(c)(iv)</b> State two password requirements. (2 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Password must contain uppercase and lowercase letters.
        </p>

        <p>
            Password must be at least 8 characters long.
        </p>
    </section>

    <section>
        <p>
            These requirements make passwords more difficult
            for attackers to guess or crack.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 1(d) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>1(d)</b> Explain why both local and remote backups are useful. (4 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            Local backups allow data to be restored quickly
            after files become damaged or deleted.
        </p>
    </section>

    <section>
        <p>
            Remote backups stored in another location
            protect data if the local system is damaged
            by fire, theft, or hardware failure.
        </p>
    </section>
</section>			
			
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2022_m_u3_q2.php">next</a></p>
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

