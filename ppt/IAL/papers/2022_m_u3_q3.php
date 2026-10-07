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
<!-- 3(a) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>3(a)</b> Draw an IoT system diagram. (5 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

<pre>

        +------------------+
        |   Sound Sensor   |
        +------------------+
                  |
                  v
        +----------------------+
        | Voice Control Device |
        +----------------------+

                  |
                  v

        +------------------+
        |    IoT Server    |
        +------------------+
          /       |       \
         /        |        \
        v         v         v

+-------------+ +-------------+ +-------------+
|    Lights   | | Door Lock   | | Mobile App  |
+-------------+ +-------------+ +-------------+

        ^
        |
+------------------+
|  Motion Sensor   |
+------------------+

</pre>

    </section>

    <section>
        <p><b>Explanation:</b></p>

        <p>
            The IoT server acts as the central controller
            that receives data from sensors
            and sends commands to connected devices.
        </p>
    </section>
</section>

<!-- ====================================================== -->
<!-- 3(b) -->
<!-- ====================================================== -->

<section>
    <section>
        <h4><b>3(b)</b> Discuss security risks in an IoT system. (6 marks)</h4>
    </section>

    <section>
        <p><b>Answer:</b></p>

        <p>
            One major risk is the use of weak default passwords
            on IoT devices.
        </p>
    </section>

    <section>
        <p>
            Hackers often know factory default passwords
            and can use them to access devices
            such as cameras or smart locks.
        </p>
    </section>

    <section>
        <p>
            Another issue is that some IoT devices
            do not receive regular security updates.
        </p>
    </section>

    <section>
        <p>
            This leaves known vulnerabilities unpatched
            and easier for attackers to exploit.
        </p>
    </section>

    <section>
        <p><b>To improve security:</b></p>

        <ul>
            <li>Use strong passwords</li>
            <li>Regularly install updates</li>
            <li>Disable unused wireless features</li>
            <li>Physically secure devices</li>
        </ul>
    </section>

    <section>
        <p><b>Conclusion:</b></p>

        <p>
            IoT systems provide convenience
            but can create serious security risks
            if devices are not properly configured
            and maintained.
        </p>
    </section>
</section>
 
   <section>    
			<p style='text-align: center'><a href="<?= $dirBase ?>/2022_m_u3_q4.php">next</a></p>
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

